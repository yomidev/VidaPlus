<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereRol('admin', 'farmaceutico', 'auditor');

$pdo = pdoActual();
$tab = $_GET['tab'] ?? 'top';
$limite = (int)($_GET['limite'] ?? 20);
if ($limite < 5)  $limite = 5;
if ($limite > 100) $limite = 100;

// Datos según tab
if ($tab === 'top') {
    $stmt = $pdo->prepare("CALL sp_reporte_top_medicamentos(?)");
    $stmt->execute([$limite]);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
} elseif ($tab === 'stock') {
    $stmt = $pdo->prepare("CALL sp_reporte_stock_bajo()");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
} else { // sin_venta
    $stmt = $pdo->prepare("CALL sp_reporte_productos_sin_venta()");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
}

$tituloPagina = 'Reporte de productos';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/reportes.php" class="hover:text-primary-600">Reportes</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Productos</span>
</nav>

<h1 class="text-2xl font-bold text-gray-900 mb-5">Análisis de productos</h1>

<!-- Tabs -->
<div class="border-b border-gray-200 mb-5">
    <nav class="flex gap-1">
        <?php
        $tabs = [
            'top'       => ['label' => 'Top vendidos',   'icon' => 'chart'],
            'stock'     => ['label' => 'Stock bajo',     'icon' => 'warning'],
            'sin_venta' => ['label' => 'Sin ventas',     'icon' => 'x'],
        ];
        foreach ($tabs as $key => $t):
            $activo = $tab === $key;
        ?>
            <a href="?tab=<?= $key ?><?= $key === 'top' ? "&limite=$limite" : '' ?>"
               class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                      <?= $activo
                          ? 'border-primary-600 text-primary-700'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <?= $t['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if ($tab === 'top'): ?>
    <!-- Selector de límite -->
    <div class="mb-4 flex items-center gap-2">
        <span class="text-sm text-gray-500">Mostrar:</span>
        <?php foreach ([10, 20, 50, 100] as $n): ?>
            <a href="?tab=top&limite=<?= $n ?>"
               class="text-xs px-3 py-1 rounded-lg transition
                      <?= $limite === $n
                          ? 'bg-primary-700 text-white'
                          : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                Top <?= $n ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Unidades</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ventas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ingreso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin datos</td></tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-bold text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($r['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($r['presentacion']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">
                                <?= e($r['categoria']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-medium"><?= (int)$r['unidades_vendidas'] ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600"><?= (int)$r['num_ventas'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-emerald-600">
                            $<?= number_format((float)$r['ingreso_generado'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'stock'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-amber-200 bg-amber-50">
            <p class="text-sm text-amber-800">
                <strong><?= count($rows) ?> productos</strong> por debajo del stock mínimo.
                La inversión para reponerlos es
                <strong>$<?= number_format(array_sum(array_column($rows, 'inversion_reposicion')), 2) ?></strong>.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Stock</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Mínimo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Faltan</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Inversión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-emerald-600">
                        ✓ Todos los productos tienen stock suficiente
                    </td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($r['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($r['presentacion']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['categoria']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-red-600"><?= (int)$r['stock'] ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600"><?= (int)$r['stock_minimo'] ?></td>
                        <td class="px-4 py-3 text-sm text-right text-amber-700 font-medium"><?= (int)$r['faltante'] ?></td>
                        <td class="px-4 py-3 text-sm text-right">$<?= number_format((float)$r['inversion_reposicion'], 2) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <p class="text-sm text-gray-500">
                Productos activos sin ventas registradas:
                <strong class="text-gray-900"><?= count($rows) ?></strong>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Laboratorio</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Stock</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Precio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-emerald-600">
                        ✓ Todos los productos activos tienen al menos una venta
                    </td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($r['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($r['presentacion']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['categoria']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['laboratorio']) ?></td>
                        <td class="px-4 py-3 text-sm text-right"><?= (int)$r['stock'] ?></td>
                        <td class="px-4 py-3 text-sm text-right">$<?= number_format((float)$r['precio_venta'], 2) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>