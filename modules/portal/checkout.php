<?php
// modules/portal/checkout.php — Confirmar el pedido desde el carrito
require_once __DIR__ . '/../../includes/auth.php';

if ($_SESSION['tipo'] !== 'cliente') {
    flash('error', 'Esta sección es exclusiva del portal de clientes.');
    redirect('/index.php');
}

$pdo = pdoActual();
$idCliente = (int)$_SESSION['usuario_id'];
$carrito   = carrito();

if (empty($carrito)) {
    flash('error', 'Tu carrito está vacío.');
    redirect('/modules/portal/carrito.php');
}

// ============================================================
// Reconstruir items con datos actuales de la BD
// ============================================================
$ids = array_keys($carrito);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("
    SELECT m.id_medicamento, m.nombre, m.presentacion, m.concentracion,
           m.precio_venta, m.stock, m.requiere_receta, m.activo
    FROM medicamentos m
    WHERE m.id_medicamento IN ($placeholders)
");
$stmt->execute($ids);
$meds = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

$items = [];
$subtotal = 0;
$erroresStock = [];

foreach ($carrito as $id => $cantidad) {
    if (!isset($meds[$id])) continue;
    $m = $meds[$id];

    if ((int)$m['activo'] !== 1) {
        $erroresStock[] = "\"{$m['nombre']}\" ya no está disponible.";
        continue;
    }
    if ((int)$m['requiere_receta'] === 1) {
        $erroresStock[] = "\"{$m['nombre']}\" requiere receta y no se puede comprar en línea.";
        continue;
    }
    if ((int)$m['stock'] < $cantidad) {
        $erroresStock[] = "\"{$m['nombre']}\": solo quedan {$m['stock']} unidades (pediste $cantidad).";
        continue;
    }

    $subtotalItem = round($m['precio_venta'] * $cantidad, 2);
    $items[] = [
        'id'           => $id,
        'nombre'       => $m['nombre'],
        'presentacion' => $m['presentacion'],
        'cantidad'     => $cantidad,
        'precio'       => (float)$m['precio_venta'],
        'subtotal'     => $subtotalItem,
    ];
    $subtotal += $subtotalItem;
}

if (!empty($erroresStock)) {
    $_SESSION['flash']['error'] = implode(' ', $erroresStock) . ' Corrige tu carrito antes de continuar.';
    redirect('/modules/portal/carrito.php');
}

// ============================================================
// Direcciones del cliente
// ============================================================
$stmt = $pdo->prepare("
    SELECT d.*, p.nombre AS pais
    FROM direcciones_cliente d
    INNER JOIN paises p ON d.id_pais = p.id_pais
    WHERE d.id_cliente = ? AND d.activa = 1
    ORDER BY d.es_predeterminada DESC, d.fecha_creacion DESC
");
$stmt->execute([$idCliente]);
$direcciones = $stmt->fetchAll();

if (empty($direcciones)) {
    flash('error', 'Necesitas registrar al menos una dirección de envío.');
    redirect('/modules/portal/mis_direcciones.php?accion=crear');
}

// ============================================================
// Procesar POST
// ============================================================
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idDireccion = (int)($_POST['id_direccion'] ?? 0);
    $metodoPago  = $_POST['metodo_pago'] ?? 'tarjeta';
    $costoEnvio  = max(0, (float)($_POST['costo_envio'] ?? 0));
    $referencia  = trim($_POST['referencia'] ?? '');

    // Validar dirección
    $stmt = $pdo->prepare("
        SELECT id_direccion FROM direcciones_cliente
        WHERE id_direccion = ? AND id_cliente = ? AND activa = 1
    ");
    $stmt->execute([$idDireccion, $idCliente]);
    if (!$stmt->fetch()) {
        $errores[] = 'Selecciona una dirección válida.';
    }

    if (!in_array($metodoPago, ['tarjeta','transferencia','paypal','oxxo','efectivo'], true)) {
        $errores[] = 'Método de pago inválido.';
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $iva   = round($subtotal * 0.16, 2);
            $total = round($subtotal + $iva + $costoEnvio, 2);

            // Folio
            $anio = date('Y');
            $stmtFolio = $pdo->prepare("SELECT COUNT(*) + 1 FROM ventas WHERE YEAR(fecha_venta) = ?");
            $stmtFolio->execute([$anio]);
            $consec = (int)$stmtFolio->fetchColumn();
            $folio  = 'V-' . $anio . '-' . str_pad($consec, 4, '0', STR_PAD_LEFT);

            // 1. INSERT venta (usa portal_user_id como id_usuario)
            $pdo->prepare("
                INSERT INTO ventas
                    (folio, canal, id_cliente, id_usuario, id_direccion_envio,
                     subtotal, iva, descuento, costo_envio, total, estado)
                VALUES (?, 'online', ?, ?, ?, ?, ?, 0, ?, ?, 'completada')
            ")->execute([
                $folio, $idCliente, idUsuarioPortal(), $idDireccion,
                $subtotal, $iva, $costoEnvio, $total,
            ]);
            $idVenta = (int)$pdo->lastInsertId();

            // 2. INSERT detalles (los triggers validan stock y descuentan)
            $stmtDetalle = $pdo->prepare("
                INSERT INTO detalle_ventas
                    (id_venta, id_medicamento, cantidad, precio_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmtDetalle->execute([
                    $idVenta, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal'],
                ]);
            }

            // 3. INSERT envío
            $pdo->prepare("
                INSERT INTO envios (id_venta, empresa_paqueteria, estado)
                VALUES (?, 'Por asignar', 'pendiente')
            ")->execute([$idVenta]);

            // 4. INSERT pago
            $pdo->prepare("
                INSERT INTO pagos (id_venta, monto, metodo, referencia)
                VALUES (?, ?, ?, ?)
            ")->execute([$idVenta, $total, $metodoPago, $referencia ?: null]);

            $pdo->commit();

            carritoVaciar();
            $_SESSION['flash']['exito'] = "¡Pedido $folio confirmado! Te contactaremos para el envío.";
            redirect('/modules/portal/ver_pedido.php?id=' . $idVenta);

        } catch (PDOException $ex) {
            $pdo->rollBack();
            $msg = $ex->getMessage();

            if (str_contains($msg, 'Stock insuficiente')) {
                $errores[] = 'Algún producto ya no tiene stock suficiente. Actualiza tu carrito.';
            } elseif (str_contains($msg, '1142')) {
                $errores[] = 'Error de configuración del sistema. Contacta al administrador.';
                error_log('Checkout 1142: ' . $msg);
            } elseif (str_contains($msg, 'receta')) {
                $errores[] = 'Algún producto requiere receta y no se puede vender en línea.';
            } else {
                $errores[] = 'Error al procesar el pedido. Intenta de nuevo.';
                error_log('Checkout error: ' . $msg);
            }
        }
    }
}

$iva   = round($subtotal * 0.16, 2);
$total = round($subtotal + $iva, 2);

$tituloPagina = 'Confirmar pedido';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/portal/carrito.php" class="hover:text-primary-600">Carrito</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Confirmar pedido</span>
</nav>

<h1 class="text-2xl font-bold text-gray-900 mb-5">Confirmar pedido</h1>

<?php if (!empty($errores)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
            <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="lg:col-span-2 space-y-4">

        <!-- Dirección -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Dirección de envío
            </h2>

            <div class="space-y-2">
                <?php foreach ($direcciones as $i => $d): ?>
                    <label class="block cursor-pointer">
                        <input type="radio" name="id_direccion" value="<?= (int)$d['id_direccion'] ?>"
                               <?= $i === 0 ? 'checked' : '' ?> class="peer sr-only">
                        <div class="p-4 rounded-lg border-2 border-gray-200 peer-checked:border-primary-500
                                    peer-checked:bg-primary-50 transition">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-700
                                            flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <strong class="text-sm text-gray-900"><?= e($d['alias']) ?></strong>
                                        <?php if ($d['es_predeterminada']): ?>
                                            <span class="text-[10px] font-semibold uppercase tracking-wider
                                                         bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded">
                                                Predeterminada
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <?= e($d['calle']) ?>
                                        <?php if ($d['colonia']): ?> · Col. <?= e($d['colonia']) ?><?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?= e($d['ciudad'] . ', ' . $d['estado_provincia']) ?>
                                        · CP <?= e($d['codigo_postal']) ?> · <?= e($d['pais']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>

                <a href="/modules/portal/mis_direcciones.php?accion=crear"
                   class="block text-center text-sm text-primary-600 hover:underline py-2">
                    + Agregar otra dirección
                </a>
            </div>
        </div>

        <!-- Método de pago -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Método de pago
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                <?php
                $metodos = [
                    'tarjeta'       => 'Tarjeta',
                    'transferencia' => 'Transferencia',
                    'paypal'        => 'PayPal',
                    'oxxo'          => 'OXXO',
                    'efectivo'      => 'Contra entrega',
                ];
                foreach ($metodos as $key => $label):
                ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="metodo_pago" value="<?= $key ?>"
                               <?= $key === 'tarjeta' ? 'checked' : '' ?> class="peer sr-only">
                        <div class="px-3 py-2.5 rounded-lg border-2 border-gray-200 peer-checked:border-primary-500
                                    peer-checked:bg-primary-50 text-center text-sm font-medium transition">
                            <?= e($label) ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Referencia (opcional)
                </label>
                <input type="text" name="referencia"
                       placeholder="Ej. últimos 4 dígitos de tarjeta o folio"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 outline-none transition">
            </div>
        </div>

        <!-- Costo de envío -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Costo de envío
            </h2>
            <div class="relative max-w-xs">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                <input type="number" name="costo_envio" id="inp_costo_envio"
                       value="80.00" step="0.01" min="0" oninput="actualizarTotal()"
                       class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 outline-none transition">
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Envío estándar: $80.00 MXN (3-5 días hábiles).
            </p>
        </div>
    </div>

    <!-- Resumen -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-20">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                Resumen del pedido
            </h2>

            <ul class="divide-y divide-gray-100 mb-4 max-h-56 overflow-y-auto">
                <?php foreach ($items as $item): ?>
                    <li class="py-2 flex justify-between text-sm">
                        <div class="flex-1 min-w-0 pr-2">
                            <div class="text-gray-900 truncate"><?= e($item['nombre']) ?></div>
                            <div class="text-xs text-gray-500">x<?= (int)$item['cantidad'] ?></div>
                        </div>
                        <div class="text-gray-900 whitespace-nowrap">
                            $<?= number_format($item['subtotal'], 2) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="space-y-2 text-sm mb-4 pt-3 border-t border-gray-100">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="text-gray-900">$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">IVA (16%)</span>
                    <span class="text-gray-900">$<?= number_format($iva, 2) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Envío</span>
                    <span class="text-gray-900" id="lbl_envio">$80.00</span>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-3 mb-4">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-gray-900">Total</span>
                    <span class="text-2xl font-bold text-gray-900" id="lbl_total">
                        $<?= number_format($total + 80, 2) ?>
                    </span>
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-primary-700 hover:bg-primary-800 text-white text-center
                           font-semibold py-2.5 rounded-lg transition shadow-sm">
                Confirmar pedido
            </button>

            <p class="text-xs text-gray-400 text-center mt-3">
                Al confirmar aceptas los términos y condiciones.
            </p>
        </div>
    </div>
</form>

<script>
const SUBTOTAL = <?= (float)$subtotal ?>;
const IVA = <?= (float)$iva ?>;

function actualizarTotal() {
    const costo = parseFloat(document.getElementById('inp_costo_envio').value) || 0;
    const total = SUBTOTAL + IVA + costo;
    document.getElementById('lbl_envio').textContent = '$' + costo.toFixed(2);
    document.getElementById('lbl_total').textContent = '$' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', actualizarTotal);
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>