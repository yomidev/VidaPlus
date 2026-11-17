<?php
// modules/compras_nueva.php
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico');

$pdo = pdoActual();

$proveedores = $pdo->query("SELECT id_proveedor, nombre FROM proveedores ORDER BY nombre")->fetchAll();
$medicamentos = $pdo->query("
    SELECT id_medicamento, codigo_barras, nombre, presentacion, precio_compra, stock
    FROM medicamentos WHERE activo = 1 ORDER BY nombre
")->fetchAll();

$errores = [];
$datos = [
    'id_proveedor' => $_POST['id_proveedor'] ?? '',
    'items'        => $_POST['items'] ?? [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int)$datos['id_proveedor'] <= 0) {
        $errores[] = 'Selecciona un proveedor.';
    }
    if (empty($datos['items']) || !is_array($datos['items'])) {
        $errores[] = 'Agrega al menos un medicamento.';
    }

    $itemsValidos = [];
    $subtotal = 0;

    if (empty($errores)) {
        foreach ($datos['items'] as $item) {
            $idMed = (int)($item['id'] ?? 0);
            $cant  = (int)($item['cantidad'] ?? 0);
            $precio = (float)($item['precio'] ?? 0);
            if ($idMed <= 0 || $cant <= 0 || $precio <= 0) continue;

            $stmt = $pdo->prepare("SELECT nombre FROM medicamentos WHERE id_medicamento = ? AND activo = 1");
            $stmt->execute([$idMed]);
            if (!$stmt->fetch()) continue;

            $itemsValidos[] = [
                'id_medicamento' => $idMed,
                'cantidad'       => $cant,
                'precio'         => $precio,
                'subtotal'       => round($precio * $cant, 2),
            ];
            $subtotal += $precio * $cant;
        }

        if (empty($itemsValidos)) {
            $errores[] = 'No hay items válidos en la compra.';
        }
    }

    $subtotal = round($subtotal, 2);
    $iva = round($subtotal * 0.16, 2);
    $total = round($subtotal + $iva, 2);

    $folio = null;
    if (empty($errores)) {
        $anio = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM compras WHERE YEAR(fecha_compra) = ?");
        $stmt->execute([$anio]);
        $folio = 'C-' . $anio . '-' . str_pad((int)$stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO compras (folio, id_proveedor, id_usuario, subtotal, iva, total, estado)
                VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
            ")->execute([
                $folio, (int)$datos['id_proveedor'], (int)$_SESSION['usuario_id'],
                $subtotal, $iva, $total,
            ]);
            $idCompra = (int)$pdo->lastInsertId();

            $stmtDetalle = $pdo->prepare("
                INSERT INTO detalle_compras (id_compra, id_medicamento, cantidad, precio_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($itemsValidos as $item) {
                $stmtDetalle->execute([
                    $idCompra, $item['id_medicamento'], $item['cantidad'], $item['precio'], $item['subtotal'],
                ]);
            }

            $pdo->commit();
            flash('exito', "Compra $folio creada. Marca 'recibir' cuando llegue la mercancía para sumar al stock.");
            redirect('/modules/compras.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errores[] = 'Error: ' . $ex->getMessage();
        }
    }
}

$tituloPagina = 'Nueva compra';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/compras.php" class="hover:text-primary-600">Compras</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Nueva</span>
</nav>

<h1 class="text-2xl font-bold text-gray-900 mb-5">Nueva compra a proveedor</h1>

<?php if (!empty($errores)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
            <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" id="formCompra" class="space-y-4">

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor <span class="text-red-500">*</span></label>
        <select name="id_proveedor" required class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
            <option value="">— Selecciona —</option>
            <?php foreach ($proveedores as $p): ?>
                <option value="<?= (int)$p['id_proveedor'] ?>" <?= (int)$datos['id_proveedor'] === (int)$p['id_proveedor'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Medicamentos a comprar</h2>
            <button type="button" onclick="agregarFila()"
                    class="text-xs px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white transition inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Agregar
            </button>
        </div>
        <div id="items" class="p-4 space-y-2"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <div class="max-w-xs ml-auto space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="text-gray-900 font-medium" id="lblSubtotal">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-500">IVA (16%)</span><span class="text-gray-900 font-medium" id="lblIva">$0.00</span></div>
                <div class="flex justify-between pt-2 border-t border-gray-200 mt-2"><span class="font-semibold text-gray-900">Total</span><span class="font-bold text-lg text-gray-900" id="lblTotal">$0.00</span></div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="/modules/compras.php" class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Cancelar</a>
        <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold px-6 py-2 rounded-lg transition shadow-sm">Registrar compra</button>
    </div>
</form>

<template id="filaTpl">
    <div class="fila-item grid grid-cols-12 gap-2 items-center p-2 rounded-lg bg-gray-50">
        <div class="col-span-6">
            <select name="items[__IDX__][id]" onchange="onMedChange(this)"
                    class="w-full px-2 py-1.5 text-sm bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                <option value="">— Selecciona —</option>
                <?php foreach ($medicamentos as $m): ?>
                    <option value="<?= (int)$m['id_medicamento'] ?>" data-precio="<?= (float)$m['precio_compra'] ?>">
                        <?= e($m['nombre'] . ' — ' . $m['presentacion']) ?> (stock: <?= (int)$m['stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-2">
            <input type="number" name="items[__IDX__][cantidad]" min="1" value="1" oninput="recalc()"
                   class="w-full px-2 py-1.5 text-sm bg-white border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-primary-500 outline-none">
        </div>
        <div class="col-span-3">
            <input type="number" name="items[__IDX__][precio]" step="0.01" min="0.01" oninput="recalc()"
                   class="inp-precio w-full px-2 py-1.5 text-sm bg-white border border-gray-300 rounded-lg text-right focus:ring-2 focus:ring-primary-500 outline-none">
        </div>
        <div class="col-span-1 text-center">
            <button type="button" onclick="this.closest('.fila-item').remove(); recalc();"
                    class="text-red-600 hover:text-red-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
let idx = 0;
function agregarFila() {
    const html = document.getElementById('filaTpl').content.cloneNode(true).querySelector('.fila-item').outerHTML.replace(/__IDX__/g, idx++);
    document.getElementById('items').insertAdjacentHTML('beforeend', html);
}
function onMedChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const precio = opt.dataset.precio || '';
    const fila = sel.closest('.fila-item');
    fila.querySelector('.inp-precio').value = precio;
    recalc();
}
function recalc() {
    let sub = 0;
    document.querySelectorAll('.fila-item').forEach(f => {
        const cant = parseFloat(f.querySelector('input[name*="cantidad"]').value) || 0;
        const precio = parseFloat(f.querySelector('.inp-precio').value) || 0;
        sub += cant * precio;
    });
    const iva = sub * 0.16;
    document.getElementById('lblSubtotal').textContent = '$' + sub.toFixed(2);
    document.getElementById('lblIva').textContent = '$' + iva.toFixed(2);
    document.getElementById('lblTotal').textContent = '$' + (sub + iva).toFixed(2);
}
document.addEventListener('DOMContentLoaded', () => { agregarFila(); recalc(); });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>