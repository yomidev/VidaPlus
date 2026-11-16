<?php
// modules/ventas_nueva.php
// Crear una venta completa con transacción (PHP) + triggers (MySQL).

require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico', 'cajero');

$pdo = pdoActual();

// ============================================================
// Datos para el formulario
// ============================================================
$clientes     = $pdo->query("SELECT id_cliente, nombre, apellido, email FROM clientes WHERE activo=1 ORDER BY apellido, nombre")->fetchAll();
$medicamentos = $pdo->query("
    SELECT id_medicamento, codigo_barras, nombre, presentacion, precio_venta, stock, requiere_receta
    FROM medicamentos
    WHERE activo = 1 AND stock > 0
    ORDER BY nombre
")->fetchAll();

// Direcciones de todos los clientes (para filtrar por JS)
$direcciones = $pdo->query("
    SELECT id_direccion, id_cliente, alias, calle, ciudad, estado_provincia, es_predeterminada
    FROM direcciones_cliente
    WHERE activa = 1
    ORDER BY es_predeterminada DESC, alias
")->fetchAll();

// ============================================================
// Procesar POST
// ============================================================
$errores = [];
$datos = [
    'canal'         => $_POST['canal'] ?? 'local',
    'id_cliente'    => $_POST['id_cliente'] ?? '',
    'id_direccion'  => $_POST['id_direccion'] ?? '',
    'metodo_pago'   => $_POST['metodo_pago'] ?? 'efectivo',
    'referencia'    => trim($_POST['referencia'] ?? ''),
    'costo_envio'   => $_POST['costo_envio'] ?? '0',
    'empresa_envio' => $_POST['empresa_envio'] ?? '',
    'items'         => $_POST['items'] ?? [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Validaciones básicas ---
    if (!in_array($datos['canal'], ['local','online'])) {
        $errores[] = 'Canal inválido.';
    }
    if (!in_array($datos['metodo_pago'], ['efectivo','tarjeta','transferencia','paypal','oxxo'])) {
        $errores[] = 'Método de pago inválido.';
    }
    if ($datos['canal'] === 'online') {
        if ((int)$datos['id_cliente'] <= 0) $errores[] = 'Debes seleccionar un cliente para venta online.';
        if ((int)$datos['id_direccion'] <= 0) $errores[] = 'Debes seleccionar una dirección de envío.';
    }
    if (empty($datos['items']) || !is_array($datos['items'])) {
        $errores[] = 'Agrega al menos un medicamento.';
    }

    // --- Preparar los items validados ---
    $itemsValidos = [];
    $subtotal = 0;

    if (empty($errores)) {
        foreach ($datos['items'] as $item) {
            $idMed  = (int)($item['id'] ?? 0);
            $cant   = (int)($item['cantidad'] ?? 0);
            if ($idMed <= 0 || $cant <= 0) continue;

            // Verificar el medicamento en la BD (no confiar en precios del cliente)
            $stmt = $pdo->prepare("
                SELECT id_medicamento, nombre, precio_venta, stock, requiere_receta
                FROM medicamentos
                WHERE id_medicamento = ? AND activo = 1
            ");
            $stmt->execute([$idMed]);
            $med = $stmt->fetch();

            if (!$med) {
                $errores[] = "Medicamento ID $idMed no existe o está inactivo.";
                continue;
            }
            if ((int)$med['stock'] < $cant) {
                $errores[] = "Stock insuficiente para '{$med['nombre']}' (disponible: {$med['stock']}).";
                continue;
            }
            if ($datos['canal'] === 'online' && (int)$med['requiere_receta'] === 1) {
                $errores[] = "'{$med['nombre']}' requiere receta y no se puede vender en línea.";
                continue;
            }

            $precio = (float)$med['precio_venta'];
            $itemsValidos[] = [
                'id_medicamento'  => $idMed,
                'cantidad'        => $cant,
                'precio_unitario' => $precio,
                'subtotal'        => round($precio * $cant, 2),
            ];
            $subtotal += $precio * $cant;
        }

        if (empty($itemsValidos) && empty($errores)) {
            $errores[] = 'No hay medicamentos válidos en la venta.';
        }
    }

    // --- Cálculos ---
    $subtotal = round($subtotal, 2);
    $iva      = round($subtotal * 0.16, 2);
    $descuento = 0; // Se puede implementar puntos de lealtad en el futuro
    $costoEnvio = $datos['canal'] === 'online' ? (float)$datos['costo_envio'] : 0;
    $total    = round($subtotal + $iva + $costoEnvio - $descuento, 2);

    // --- Generar folio ---
    $folio = null;
    if (empty($errores)) {
        $anio = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM ventas WHERE YEAR(fecha_venta) = ?");
        $stmt->execute([$anio]);
        $consecutivo = (int)$stmt->fetchColumn();
        $folio = 'V-' . $anio . '-' . str_pad($consecutivo, 4, '0', STR_PAD_LEFT);
    }

    // --- Transacción ---
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Paso 1: INSERT venta
            $stmt = $pdo->prepare("
                INSERT INTO ventas
                    (folio, canal, id_cliente, id_usuario, id_direccion_envio,
                     subtotal, iva, descuento, costo_envio, total, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completada')
            ");
            $stmt->execute([
                $folio,
                $datos['canal'],
                $datos['canal'] === 'online' ? (int)$datos['id_cliente'] : ((int)$datos['id_cliente'] ?: null),
                (int)$_SESSION['usuario_id'],
                $datos['canal'] === 'online' ? (int)$datos['id_direccion'] : null,
                $subtotal, $iva, $descuento, $costoEnvio, $total,
            ]);
            $idVenta = (int)$pdo->lastInsertId();

            // Paso 2: INSERT detalle_ventas (dispara trigger de stock)
            $stmt = $pdo->prepare("
                INSERT INTO detalle_ventas
                    (id_venta, id_medicamento, cantidad, precio_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($itemsValidos as $item) {
                $stmt->execute([
                    $idVenta,
                    $item['id_medicamento'],
                    $item['cantidad'],
                    $item['precio_unitario'],
                    $item['subtotal'],
                ]);
            }

            // Paso 3: Si es online, crear el envío (1:1)
            if ($datos['canal'] === 'online') {
                $stmt = $pdo->prepare("
                    INSERT INTO envios (id_venta, empresa_paqueteria, estado)
                    VALUES (?, ?, 'pendiente')
                ");
                $stmt->execute([
                    $idVenta,
                    $datos['empresa_envio'] !== '' ? $datos['empresa_envio'] : 'Por definir',
                ]);
            }

            // Paso 4: INSERT pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos (id_venta, monto, metodo, referencia)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $idVenta,
                $total,
                $datos['metodo_pago'],
                $datos['referencia'] !== '' ? $datos['referencia'] : null,
            ]);

            // Paso 5: COMMIT
            $pdo->commit();

            flash('exito', "Venta $folio registrada correctamente.");
            redirect('/modules/ventas.php?accion=ver&id=' . $idVenta);

        } catch (PDOException $ex) {
            $pdo->rollBack();
            if (str_contains($ex->getMessage(), 'Stock insuficiente')) {
                preg_match("/Stock insuficiente[^']*'.*?'.*?(\d+)/", $ex->getMessage(), $m);
                $errores[] = $ex->getMessage();
            } elseif (str_contains($ex->getMessage(), 'receta')) {
                $errores[] = 'Los medicamentos con receta no se pueden vender en línea.';
            } elseif (str_contains($ex->getMessage(), '1062')) {
                $errores[] = 'Error de folio duplicado. Intenta de nuevo.';
            } else {
                $errores[] = 'Error al registrar la venta: ' . $ex->getMessage();
            }
        }
    }
}

$tituloPagina = 'Nueva venta';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/ventas.php" class="hover:text-primary-600 transition">Ventas</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Nueva venta</span>
</nav>

<div class="mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Nueva venta</h1>
    <p class="text-sm text-gray-500 mt-1">Registra una venta local o en línea</p>
</div>

<?php if (!empty($errores)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                <?php foreach ($errores as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<form method="POST" id="formVenta" class="space-y-4">

    <!-- Sección: Configuración -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
            Configuración de la venta
        </h2>

        <!-- Canal -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">Canal de venta</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" name="canal" value="local"
                           <?= $datos['canal'] === 'local' ? 'checked' : '' ?>
                           onchange="toggleCanal()"
                           class="peer sr-only">
                    <div class="p-3 rounded-lg border-2 border-gray-200 peer-checked:border-primary-500
                                peer-checked:bg-primary-50 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span class="font-medium text-sm">Local</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Venta en mostrador</p>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="canal" value="online"
                           <?= $datos['canal'] === 'online' ? 'checked' : '' ?>
                           onchange="toggleCanal()"
                           class="peer sr-only">
                    <div class="p-3 rounded-lg border-2 border-gray-200 peer-checked:border-primary-500
                                peer-checked:bg-primary-50 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                            <span class="font-medium text-sm">Online</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Envío a domicilio</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Cliente -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Cliente <span class="text-red-500" id="req-cliente">(opcional)</span>
                </label>
                <select name="id_cliente" id="selCliente" onchange="filtrarDirecciones()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <option value="">— Público general —</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id_cliente'] ?>"
                            <?= (int)$datos['id_cliente'] === (int)$c['id_cliente'] ? 'selected' : '' ?>>
                            <?= e($c['nombre'] . ' ' . $c['apellido']) ?> (<?= e($c['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dirección (solo online) -->
            <div id="campoDireccion" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Dirección de envío <span class="text-red-500">*</span>
                </label>
                <select name="id_direccion" id="selDireccion"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <option value="">— Selecciona un cliente primero —</option>
                </select>
            </div>
        </div>

        <!-- Método de pago -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                <select name="metodo_pago"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg
                               focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <option value="efectivo"      <?= $datos['metodo_pago'] === 'efectivo'      ? 'selected' : '' ?>>Efectivo</option>
                    <option value="tarjeta"       <?= $datos['metodo_pago'] === 'tarjeta'       ? 'selected' : '' ?>>Tarjeta</option>
                    <option value="transferencia" <?= $datos['metodo_pago'] === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                    <option value="paypal"        <?= $datos['metodo_pago'] === 'paypal'        ? 'selected' : '' ?>>PayPal</option>
                    <option value="oxxo"          <?= $datos['metodo_pago'] === 'oxxo'          ? 'selected' : '' ?>>OXXO</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Referencia</label>
                <input type="text" name="referencia"
                       value="<?= e($datos['referencia']) ?>"
                       placeholder="Opcional (folio de autorización)"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
            </div>
        </div>

        <!-- Envío (solo online) -->
        <div id="campoEnvio" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa de paquetería</label>
                <input type="text" name="empresa_envio"
                       value="<?= e($datos['empresa_envio']) ?>"
                       placeholder="Ej. DHL, FedEx, Estafeta"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Costo de envío</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                    <input type="number" name="costo_envio" id="inpCostoEnvio"
                           value="<?= e($datos['costo_envio']) ?>"
                           step="0.01" min="0" oninput="recalcularTotal()"
                           class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>
    </div>

    <!-- Sección: Carrito -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Medicamentos
            </h2>
            <button type="button" onclick="agregarFila()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700
                           text-white transition inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Agregar
            </button>
        </div>

        <div id="carrito" class="p-4 space-y-2">
            <!-- Las filas se insertan con JS -->
        </div>

        <!-- Totales -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <div class="max-w-xs ml-auto space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="text-gray-900 font-medium" id="lblSubtotal">$0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">IVA (16%)</span>
                    <span class="text-gray-900 font-medium" id="lblIva">$0.00</span>
                </div>
                <div class="flex justify-between hidden" id="rowEnvioTotal">
                    <span class="text-gray-500">Envío</span>
                    <span class="text-gray-900 font-medium" id="lblEnvio">$0.00</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-200 mt-2">
                    <span class="font-semibold text-gray-900">Total</span>
                    <span class="font-bold text-lg text-gray-900" id="lblTotal">$0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones -->
    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
        <a href="/modules/ventas.php"
           class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
            Cancelar
        </a>
        <button type="submit"
                class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                       px-6 py-2 rounded-lg transition shadow-sm">
            Registrar venta
        </button>
    </div>
</form>

<!-- Template de fila (oculto) -->
<template id="filaTemplate">
    <div class="fila-item grid grid-cols-12 gap-2 items-center p-2 rounded-lg bg-gray-50">
        <div class="col-span-7 sm:col-span-6">
            <select name="items[__IDX__][id]" class="sel-medicamento w-full px-2 py-1.5 text-sm bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none" onchange="recalcularFila(this)">
                <option value="">— Selecciona medicamento —</option>
                <?php foreach ($medicamentos as $m): ?>
                    <option value="<?= (int)$m['id_medicamento'] ?>"
                            data-precio="<?= (float)$m['precio_venta'] ?>"
                            data-stock="<?= (int)$m['stock'] ?>"
                            data-receta="<?= (int)$m['requiere_receta'] ?>">
                        <?= e($m['nombre'] . ' — ' . $m['presentacion']) ?> (stock: <?= (int)$m['stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-3 sm:col-span-2">
            <input type="number" name="items[__IDX__][cantidad]" min="1" value="1"
                   class="inp-cantidad w-full px-2 py-1.5 text-sm bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none text-center"
                   oninput="recalcularFila(this)">
        </div>
        <div class="col-span-1 text-sm text-right font-medium">
            <span class="lbl-subtotal text-gray-900">$0.00</span>
        </div>
        <div class="col-span-1 text-center">
            <button type="button" onclick="eliminarFila(this)"
                    class="text-red-600 hover:text-red-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
// Datos de direcciones desde PHP (para filtrado en cliente)
const DIRECCIONES = <?= json_encode($direcciones) ?>;
const TASA_IVA = 0.16;
let idxActual = 0;

// Toggle canal local/online
function toggleCanal() {
    const canal = document.querySelector('input[name="canal"]:checked').value;
    const campoDir = document.getElementById('campoDireccion');
    const campoEnv = document.getElementById('campoEnvio');
    const reqCliente = document.getElementById('req-cliente');
    const rowEnvioTotal = document.getElementById('rowEnvioTotal');

    if (canal === 'online') {
        campoDir.classList.remove('hidden');
        campoEnv.classList.remove('hidden');
        reqCliente.textContent = '*';
        reqCliente.classList.remove('text-gray-500');
        reqCliente.classList.add('text-red-500');
        rowEnvioTotal.classList.remove('hidden');
    } else {
        campoDir.classList.add('hidden');
        campoEnv.classList.add('hidden');
        reqCliente.textContent = '(opcional)';
        reqCliente.classList.add('text-gray-500');
        reqCliente.classList.remove('text-red-500');
        rowEnvioTotal.classList.add('hidden');
    }
    filtrarDirecciones();
    recalcularTotal();
}

// Filtrar direcciones por cliente
function filtrarDirecciones() {
    const clienteId = parseInt(document.getElementById('selCliente').value) || 0;
    const sel = document.getElementById('selDireccion');
    sel.innerHTML = '<option value="">— Selecciona una dirección —</option>';

    if (clienteId <= 0) {
        sel.innerHTML = '<option value="">— Selecciona un cliente primero —</option>';
        return;
    }

    const filtradas = DIRECCIONES.filter(d => parseInt(d.id_cliente) === clienteId);
    if (filtradas.length === 0) {
        sel.innerHTML = '<option value="">— Este cliente no tiene direcciones —</option>';
        return;
    }

    filtradas.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id_direccion;
        opt.textContent = `${d.alias} — ${d.calle}, ${d.ciudad}` + (d.es_predeterminada == 1 ? ' ⭐' : '');
        sel.appendChild(opt);
    });
}

// Agregar fila al carrito
function agregarFila() {
    const tpl = document.getElementById('filaTemplate');
    const clone = tpl.content.cloneNode(true);
    const html = clone.querySelector('.fila-item').outerHTML.replace(/__IDX__/g, idxActual++);
    document.getElementById('carrito').insertAdjacentHTML('beforeend', html);
}

// Eliminar fila
function eliminarFila(btn) {
    btn.closest('.fila-item').remove();
    recalcularTotal();
}

// Recalcular subtotal de una fila
function recalcularFila(el) {
    const fila = el.closest('.fila-item');
    const sel = fila.querySelector('.sel-medicamento');
    const cant = parseInt(fila.querySelector('.inp-cantidad').value) || 0;
    const opt = sel.options[sel.selectedIndex];
    const precio = parseFloat(opt.dataset.precio) || 0;
    const stock = parseInt(opt.dataset.stock) || 0;
    const subtotal = precio * cant;

    // Validar stock en cliente (el servidor también valida)
    if (cant > stock) {
        fila.querySelector('.inp-cantidad').classList.add('border-red-500');
    } else {
        fila.querySelector('.inp-cantidad').classList.remove('border-red-500');
    }

    fila.querySelector('.lbl-subtotal').textContent = '$' + subtotal.toFixed(2);
    recalcularTotal();
}

// Recalcular totales
function recalcularTotal() {
    let subtotal = 0;
    document.querySelectorAll('.fila-item').forEach(fila => {
        const sel = fila.querySelector('.sel-medicamento');
        const cant = parseInt(fila.querySelector('.inp-cantidad').value) || 0;
        const opt = sel.options[sel.selectedIndex];
        const precio = parseFloat(opt?.dataset?.precio) || 0;
        subtotal += precio * cant;
    });

    const iva = subtotal * TASA_IVA;
    const costoEnvio = parseFloat(document.getElementById('inpCostoEnvio')?.value) || 0;
    const total = subtotal + iva + costoEnvio;

    document.getElementById('lblSubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('lblIva').textContent = '$' + iva.toFixed(2);
    document.getElementById('lblEnvio').textContent = '$' + costoEnvio.toFixed(2);
    document.getElementById('lblTotal').textContent = '$' + total.toFixed(2);
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    toggleCanal();
    agregarFila();  // Primera fila por defecto
});
</script>

<?php
require __DIR__ . '/../includes/footer.php';
?>