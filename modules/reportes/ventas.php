<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereRol('admin', 'farmaceutico', 'auditor');

$pdo = pdoActual();

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Reporte principal con procedimiento
$stmt = $pdo->prepare("CALL sp_reporte_ventas_periodo(?, ?)");
$stmt->execute([$desde, $hasta]);
$ventasDia = $stmt->fetchAll();
$stmt->closeCursor();

// Ventas por categoría
$stmt = $pdo->prepare("CALL sp_reporte_ventas_categoria(?, ?)");
$stmt->execute([$desde, $hasta]);
$porCategoria = $stmt->fetchAll();
$stmt->closeCursor();

// Ventas por usuario
$stmt = $pdo->prepare("CALL sp_reporte_ventas_usuario(?, ?)");
$stmt->execute([$desde, $hasta]);
$porUsuario = $stmt->fetchAll();
$stmt->closeCursor();

// Totales del período
$totalVentas  = array_sum(array_column($ventasDia, 'num_ventas'));
$totalIngreso = array_sum(array_column($ventasDia, 'ingreso_total'));
$ticketProm   = $totalVentas > 0 ? $totalIngreso / $totalVentas : 0;

$tituloPagina = 'Reporte de ventas';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/reportes.php" class="hover:text-primary-600">Reportes</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Ventas</span>
</nav>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Reporte de ventas</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= date('d/m/Y', strtotime($desde)) ?> — <?= date('d/m/Y', strtotime($hasta)) ?>
        </p>
    </div>
</div>

<!-- Selector de fechas -->
<form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
            <input type="date" name="desde" value="<?= e($desde) ?>"
                   class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
            <input type="date" name="hasta" value="<?= e($hasta) ?>"
                   class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none">
        </div>
        <div class="flex gap-2">
            <button class="bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                Aplicar
            </button>
            <a href="?desde=<?= date('Y-m-d', strtotime('-7 days')) ?>&hasta=<?= date('Y-m-d') ?>"
               class="px-3 py-2 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition">
                7 días
            </a>
            <a href="?desde=<?= date('Y-m-01') ?>&hasta=<?= date('Y-m-d') ?>"
               class="px-3 py-2 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition">
                Mes
            </a>
        </div>
    </div>
</form>

<!-- Totales del período -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Ventas del período</div>
        <div class="text-3xl font-bold text-gray-900 mt-1"><?= number_format($totalVentas) ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
        <div class="text-xs text-emerald-700 uppercase tracking-wide">Ingreso total</div>
        <div class="text-3xl font-bold text-emerald-600 mt-1">$<?= number_format($totalIngreso, 2) ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Ticket promedio</div>
        <div class="text-3xl font-bold text-gray-900 mt-1">$<?= number_format($ticketProm, 2) ?></div>
    </div>
</div>

<!-- Ventas por día -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Ventas por día</h2>
    </div>
    <?php if (empty($ventasDia)): ?>
        <div class="p-8 text-center text-sm text-gray-400">No hay ventas en este período.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ventas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Local</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Online</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Clientes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ticket prom.</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ingreso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($ventasDia as $v): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <?= date('d/m/Y', strtotime($v['fecha'])) ?>
                                <div class="text-xs text-gray-400"><?= date('l', strtotime($v['fecha'])) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium"><?= (int)$v['num_ventas'] ?></td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= (int)$v['ventas_local'] ?></td>
                            <td class="px-4 py-3 text-sm text-right text-sky-600"><?= (int)$v['ventas_online'] ?></td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= (int)$v['clientes_unicos'] ?></td>
                            <td class="px-4 py-3 text-sm text-right">$<?= number_format((float)$v['ticket_promedio'], 2) ?></td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-emerald-600">
                                $<?= number_format((float)$v['ingreso_total'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Dos columnas: categoría y usuario -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Por categoría -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Ingresos por categoría</h2>
        </div>
        <?php if (empty($porCategoria)): ?>
            <div class="p-8 text-center text-sm text-gray-400">Sin datos</div>
        <?php else: ?>
            <div class="p-5 space-y-3">
                <?php foreach ($porCategoria as $c): 
                    $pct = (float)$c['porcentaje_ingreso'];
                ?>
                    <div>
                        <div class="flex items-center justify-between mb-1 text-sm">
                            <span class="font-medium text-gray-900"><?= e($c['categoria']) ?></span>
                            <span class="text-gray-600">$<?= number_format((float)$c['ingreso_total'], 2) ?></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-primary-600 to-primary-400 rounded-full"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <?= $pct ?>% del ingreso · <?= (int)$c['unidades'] ?> unidades
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Por usuario -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Ventas por empleado</h2>
        </div>
        <?php if (empty($porUsuario)): ?>
            <div class="p-8 text-center text-sm text-gray-400">Sin datos</div>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($porUsuario as $u): ?>
                    <li class="px-5 py-3 flex items-center gap-3">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900"><?= e($u['nombre_completo']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($u['rol']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900">$<?= number_format((float)$u['ingreso_total'], 2) ?></div>
                            <div class="text-xs text-gray-500">
                                <?= (int)$u['num_ventas'] ?> ventas · ticket $<?= number_format((float)$u['ticket_promedio'], 0) ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>