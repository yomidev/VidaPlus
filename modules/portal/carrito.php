<?php
// modules/portal/carrito.php
// Carrito de compras del cliente (en sesión).

require_once __DIR__ . '/../../includes/auth.php';

if ($_SESSION['tipo'] !== 'cliente') {
    flash('error', 'Esta sección es exclusiva del portal de clientes.');
    redirect('/index.php');
}

$pdo = pdoActual();
$accion = $_GET['accion'] ?? 'ver';

// ============================================================
// ACCIÓN: AGREGAR (POST)
// ============================================================
if ($accion === 'agregar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMed = (int)($_POST['id_medicamento'] ?? 0);

    if ($idMed <= 0) {
        flash('error', 'Medicamento inválido.');
        redirect('/modules/medicamentos.php');
    }

    // Validar que exista, no requiera receta, y tenga stock
    $stmt = $pdo->prepare("
        SELECT id_medicamento, nombre, stock, requiere_receta, activo
        FROM medicamentos WHERE id_medicamento = ?
    ");
    $stmt->execute([$idMed]);
    $med = $stmt->fetch();

    if (!$med || (int)$med['activo'] !== 1) {
        flash('error', 'El medicamento no está disponible.');
        redirect('/modules/medicamentos.php');
    }
    if ((int)$med['requiere_receta'] === 1) {
        flash('error', 'Los medicamentos con receta no se pueden comprar en línea.');
        redirect('/modules/medicamentos.php');
    }
    if ((int)$med['stock'] <= 0) {
        flash('error', 'Sin stock disponible.');
        redirect('/modules/medicamentos.php');
    }

    // Agregar o incrementar en el carrito
    $carrito = carrito();
    $cantidadActual = (int)($carrito[$idMed] ?? 0);

    if ($cantidadActual + 1 > (int)$med['stock']) {
        flash('error', "Stock insuficiente para \"{$med['nombre']}\". Disponible: {$med['stock']}.");
        redirect('/modules/medicamentos.php');
    }

    $carrito[$idMed] = $cantidadActual + 1;
    $_SESSION['carrito'] = $carrito;

    flash('exito', "\"{$med['nombre']}\" agregado al carrito.");
    redirect('/modules/medicamentos.php');
}

// ============================================================
// ACCIÓN: ACTUALIZAR CANTIDAD (POST)
// ============================================================
if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMed = (int)($_POST['id_medicamento'] ?? 0);
    $cantidad = (int)($_POST['cantidad'] ?? 0);

    if ($idMed <= 0) {
        flash('error', 'Datos inválidos.');
        redirect('/modules/portal/carrito.php');
    }

    if ($cantidad <= 0) {
        // Eliminar del carrito
        $carrito = carrito();
        unset($carrito[$idMed]);
        $_SESSION['carrito'] = $carrito;
        flash('exito', 'Producto eliminado del carrito.');
        redirect('/modules/portal/carrito.php');
    }

    // Validar stock
    $stmt = $pdo->prepare("SELECT nombre, stock FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$idMed]);
    $med = $stmt->fetch();

    if (!$med) {
        flash('error', 'Medicamento no encontrado.');
        redirect('/modules/portal/carrito.php');
    }
    if ($cantidad > (int)$med['stock']) {
        flash('error', "Solo hay {$med['stock']} unidades disponibles de \"{$med['nombre']}\".");
        redirect('/modules/portal/carrito.php');
    }

    $carrito = carrito();
    $carrito[$idMed] = $cantidad;
    $_SESSION['carrito'] = $carrito;

    redirect('/modules/portal/carrito.php');
}

// ============================================================
// ACCIÓN: ELIMINAR
// ============================================================
if ($accion === 'eliminar') {
    $idMed = (int)($_GET['id'] ?? 0);
    $carrito = carrito();
    unset($carrito[$idMed]);
    $_SESSION['carrito'] = $carrito;
    flash('exito', 'Producto eliminado del carrito.');
    redirect('/modules/portal/carrito.php');
}

// ============================================================
// ACCIÓN: VACIAR
// ============================================================
if ($accion === 'vaciar') {
    carritoVaciar();
    flash('exito', 'Carrito vaciado.');
    redirect('/modules/portal/carrito.php');
}

// ============================================================
// ACCIÓN: VER
// ============================================================
$carrito = carrito();
$items = [];
$subtotal = 0;

if (!empty($carrito)) {
    $ids = array_keys($carrito);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT m.id_medicamento, m.nombre, m.presentacion, m.concentracion,
               m.precio_venta, m.stock, c.nombre AS categoria
        FROM medicamentos m
        INNER JOIN categorias c ON m.id_categoria = c.id_categoria
        WHERE m.id_medicamento IN ($placeholders)
          AND m.activo = 1
          AND m.requiere_receta = 0
    ");
    $stmt->execute($ids);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

    // Reconstruir en el orden del carrito
    foreach ($carrito as $id => $cantidad) {
        if (!isset($medicamentos[$id])) continue;
        $m = $medicamentos[$id];
        $subtotalItem = round($m['precio_venta'] * $cantidad, 2);
        $items[] = [
            'id'        => $id,
            'nombre'    => $m['nombre'],
            'presentacion' => $m['presentacion'],
            'concentracion' => $m['concentracion'],
            'categoria' => $m['categoria'],
            'precio'    => (float)$m['precio_venta'],
            'stock'     => (int)$m['stock'],
            'cantidad'  => (int)$cantidad,
            'subtotal'  => $subtotalItem,
        ];
        $subtotal += $subtotalItem;
    }
}

$iva = round($subtotal * 0.16, 2);
$total = round($subtotal + $iva, 2);

$tituloPagina = 'Mi carrito';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <span class="text-gray-900 font-medium">Carrito</span>
</nav>

<div class="mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Mi carrito</h1>
    <p class="text-sm text-gray-500 mt-1">
        <?= count($items) ?> producto<?= count($items) !== 1 ? 's' : '' ?> en tu carrito
    </p>
</div>

<?php if ($msg = flash('exito')): ?>
    <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
        <?= e($msg) ?>
    </div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
        <?= e($msg) ?>
    </div>
<?php endif; ?>

<?php if (empty($items)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-4">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <h2 class="font-semibold text-gray-900 mb-1">Tu carrito está vacío</h2>
        <p class="text-gray-500 mb-4">Agrega productos desde el catálogo para comenzar.</p>
        <a href="/modules/medicamentos.php"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-700 hover:bg-primary-800
                  text-white font-semibold rounded-lg transition shadow-sm">
            Ver catálogo
        </a>
    </div>
<?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Lista de items -->
        <div class="lg:col-span-2 space-y-3">
            <?php foreach ($items as $item): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-start gap-4">
                        <!-- Icono -->
                        <div class="w-16 h-16 rounded-lg bg-primary-50 text-primary-600
                                    flex items-center justify-center flex-shrink-0">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate"><?= e($item['nombre']) ?></h3>
                            <p class="text-xs text-gray-500">
                                <?= e($item['presentacion']) ?>
                                <?php if ($item['concentracion']): ?> · <?= e($item['concentracion']) ?><?php endif; ?>
                            </p>
                            <p class="text-xs text-primary-600 mt-1">
                                <?= e($item['categoria']) ?>
                            </p>
                            <p class="text-sm text-gray-900 font-medium mt-2">
                                $<?= number_format($item['precio'], 2) ?> c/u
                            </p>
                        </div>

                        <!-- Controles -->
                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                                <form method="POST" action="/modules/portal/carrito.php?accion=actualizar" class="inline">
                                    <input type="hidden" name="id_medicamento" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="cantidad" value="<?= $item['cantidad'] - 1 ?>">
                                    <button class="px-2 py-1 text-gray-600 hover:bg-gray-100 transition
                                                   <?= $item['cantidad'] <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            <?= $item['cantidad'] <= 1 ? 'disabled' : '' ?>>−</button>
                                </form>
                                <span class="px-3 py-1 text-sm font-medium border-x border-gray-300 bg-white">
                                    <?= (int)$item['cantidad'] ?>
                                </span>
                                <form method="POST" action="/modules/portal/carrito.php?accion=actualizar" class="inline">
                                    <input type="hidden" name="id_medicamento" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="cantidad" value="<?= $item['cantidad'] + 1 ?>">
                                    <button class="px-2 py-1 text-gray-600 hover:bg-gray-100 transition
                                                   <?= $item['cantidad'] >= $item['stock'] ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            <?= $item['cantidad'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                                </form>
                            </div>

                            <p class="text-sm font-bold text-gray-900">
                                $<?= number_format($item['subtotal'], 2) ?>
                            </p>

                            <a href="/modules/portal/carrito.php?accion=eliminar&id=<?= (int)$item['id'] ?>"
                               class="text-xs text-red-600 hover:text-red-800 transition">
                                Eliminar
                            </a>
                        </div>
                    </div>

                    <?php if ($item['cantidad'] >= $item['stock']): ?>
                        <div class="mt-3 px-3 py-1.5 rounded bg-amber-50 border border-amber-200 text-xs text-amber-700">
                            Alcanzaste el máximo disponible (<?= $item['stock'] ?> unidades).
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="flex justify-between pt-2">
                <a href="/modules/medicamentos.php"
                   class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Seguir comprando
                </a>
                <a href="/modules/portal/carrito.php?accion=vaciar"
                   onclick="return confirm('¿Vaciar el carrito completamente?')"
                   class="text-sm text-red-600 hover:underline">
                    Vaciar carrito
                </a>
            </div>
        </div>

        <!-- Resumen -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-20">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                    Resumen del pedido
                </h2>

                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900">$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">IVA (16%)</span>
                        <span class="text-gray-900">$<?= number_format($iva, 2) ?></span>
                    </div>
                    <div class="text-xs text-gray-400">
                        El costo de envío se calcula en el siguiente paso.
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3 mb-4">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-gray-900">$<?= number_format($total, 2) ?></span>
                    </div>
                </div>

                <a href="/modules/portal/checkout.php"
                   class="block w-full bg-primary-700 hover:bg-primary-800 text-white text-center
                          font-semibold py-2.5 rounded-lg transition shadow-sm">
                    Ir a pagar
                </a>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>