<?php
// modules/reportes.php — Dashboard de reportes
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico', 'auditor');

$pdo = pdoActual();

// === KPIs principales (procedimiento con OUT) ===
$pdo->query("CALL sp_reporte_resumen_dashboard(@vh, @ih, @vm, @im, @ct, @sb, @tm)")->closeCursor();
$kpis = $pdo->query("
    SELECT @vh AS ventas_hoy, @ih AS ingreso_hoy,
           @vm AS ventas_mes, @im AS ingreso_mes,
           @ct AS clientes_total, @sb AS stock_bajo,
           @tm AS ticket_mes
")->fetch();

// === Ventas últimos 7 días (procedimiento con IN) ===
$stmt = $pdo->prepare("CALL sp_reporte_ventas_periodo(?, ?)");
$stmt->execute([date('Y-m-d', strtotime('-6 days')), date('Y-m-d')]);
$ventasSemana = $stmt->fetchAll();
$stmt->closeCursor();

// === Top 5 medicamentos ===
$stmt = $pdo->prepare("CALL sp_reporte_top_medicamentos(5)");
$stmt->execute();
$topMedicamentos = $stmt->fetchAll();
$stmt->closeCursor();

// === Stock bajo (primeros 5) ===
$stmt = $pdo->prepare("CALL sp_reporte_stock_bajo()");
$stmt->execute();
$stockBajo = array_slice($stmt->fetchAll(), 0, 5);
$stmt->closeCursor();

// === Top 5 clientes ===
$stmt = $pdo->prepare("CALL sp_reporte_clientes_top(5)");
$stmt->execute();
$topClientes = $stmt->fetchAll();
$stmt->closeCursor();

// === Preparar datos para el gráfico de barras ===
$dias = [];
foreach (array_reverse($ventasSemana) as $v) {
    $dias[] = [
        'label' => date('d/m', strtotime($v['fecha'])),
        'valor' => (float)$v['ingreso_total'],
    ];
}
$maxIngreso = max(array_column($dias, 'valor') ?: [1]);
if ($maxIngreso <= 0) $maxIngreso = 1;

$tituloPagina = 'Reportes';
require __DIR__ . '/../includes/header.php';
?>

<!-- Encabezado -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard de reportes</h1>
        <p class="text-sm text-gray-500 mt-1">
            Análisis del negocio · <?= date('d/m/Y H:i') ?>
        </p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="/modules/reportes/ventas.php"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-primary-700 hover:bg-primary-800 text-white rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Ventas
        </a>
        <a href="/modules/reportes/productos.php"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Productos
        </a>
        <a href="/modules/reportes/clientes.php"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Clientes
        </a>
        <a href="/modules/reportes/comparativa.php"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Comparativa
        </a>
    </div>
</div>

<!-- KPIs principales -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <span class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</span>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?= (int)$kpis['ventas_hoy'] ?></div>
        <div class="text-sm text-primary-600 font-medium mt-1">
            $<?= number_format((float)$kpis['ingreso_hoy'], 2) ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <span class="text-xs text-emerald-700 uppercase tracking-wide">Mes actual</span>
        </div>
        <div class="text-3xl font-bold text-emerald-600"><?= (int)$kpis['ventas_mes'] ?></div>
        <div class="text-sm text-emerald-600 font-medium mt-1">
            $<?= number_format((float)$kpis['ingreso_mes'], 2) ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <span class="text-xs text-gray-500 uppercase tracking-wide">Clientes</span>
        </div>
        <div class="text-3xl font-bold text-gray-900"><?= (int)$kpis['clientes_total'] ?></div>
        <div class="text-xs text-gray-500 mt-1">
            Ticket promedio mes: $<?= number_format((float)$kpis['ticket_mes'], 2) ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-5">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <span class="text-xs text-amber-700 uppercase tracking-wide">Stock bajo</span>
        </div>
        <div class="text-3xl font-bold text-amber-600"><?= (int)$kpis['stock_bajo'] ?></div>
        <div class="text-xs text-amber-700 mt-1">productos por reabastecer</div>
    </div>
</div>

<!-- Gráfico de barras: últimos 7 días -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-5">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Ingresos últimos 7 días
            </h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Total: $<?= number_format(array_sum(array_column($dias, 'valor')), 2) ?>
            </p>
        </div>
        <a href="/modules/reportes/ventas.php" class="text-xs text-primary-600 hover:underline">
            Ver detalle →
        </a>
    </div>
    <div class="flex items-end justify-between gap-2 h-40">
        <?php foreach ($dias as $d):
            $altura = $maxIngreso > 0 ? max(4, ($d['valor'] / $maxIngreso) * 100) : 4;
        ?>
            <div class="flex-1 flex flex-col items-center gap-2">
                <div class="w-full flex items-end justify-center h-full">
                    <div class="w-full max-w-[40px] bg-gradient-to-t from-primary-600 to-primary-400
                                rounded-t-lg relative group transition-all hover:from-primary-700 hover:to-primary-500"
                         style="height: <?= $altura ?>%">
                        <div class="absolute -top-7 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100
                                    bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap
                                    transition-opacity">
                            $<?= number_format($d['valor'], 0) ?>
                        </div>
                    </div>
                </div>
                <span class="text-xs text-gray-500"><?= $d['label'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Grid: Top productos + Stock bajo -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">

    <!-- Top 5 medicamentos -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Top 5 medicamentos vendidos
            </h2>
            <a href="/modules/reportes/productos.php" class="text-xs text-primary-600 hover:underline">
                Ver más
            </a>
        </div>
        <?php if (empty($topMedicamentos)): ?>
            <div class="p-8 text-center text-sm text-gray-400">Sin datos</div>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($topMedicamentos as $i => $m): ?>
                    <li class="px-5 py-3 flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-primary-100 text-primary-700
                                    flex items-center justify-center text-xs font-bold flex-shrink-0">
                            <?= $i + 1 ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate"><?= e($m['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($m['categoria']) ?></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-medium text-gray-900"><?= (int)$m['unidades_vendidas'] ?> u</div>
                            <div class="text-xs text-emerald-600">$<?= number_format((float)$m['ingreso_generado'], 0) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Stock bajo -->
    <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-amber-200 bg-amber-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-amber-800 uppercase tracking-wide">
                Requieren reabastecimiento
            </h2>
            <a href="/modules/reportes/productos.php?tab=stock" class="text-xs text-amber-700 hover:underline">
                Ver todos
            </a>
        </div>
        <?php if (empty($stockBajo)): ?>
            <div class="p-8 text-center text-sm text-gray-400">
                ✓ Todos los productos tienen stock suficiente
            </div>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($stockBajo as $m): ?>
                    <li class="px-5 py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600
                                    flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate"><?= e($m['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($m['categoria']) ?></div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-bold text-red-600"><?= (int)$m['stock'] ?> / <?= (int)$m['stock_minimo'] ?></div>
                            <div class="text-xs text-amber-700">faltan <?= (int)$m['faltante'] ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Top clientes -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
            Top 5 clientes por compras
        </h2>
        <a href="/modules/reportes/clientes.php" class="text-xs text-primary-600 hover:underline">
            Ver más
        </a>
    </div>
    <?php if (empty($topClientes)): ?>
        <div class="p-8 text-center text-sm text-gray-400">Sin datos</div>
    <?php else: ?>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Cliente</th>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">País</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Compras</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Puntos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($topClientes as $i => $c): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-sm font-bold text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-5 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($c['cliente']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($c['email']) ?></div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600"><?= e($c['pais']) ?></td>
                        <td class="px-5 py-3 text-sm text-right"><?= (int)$c['num_compras'] ?></td>
                        <td class="px-5 py-3 text-sm text-right font-medium text-emerald-600">
                            $<?= number_format((float)$c['total_gastado'], 2) ?>
                        </td>
                        <td class="px-5 py-3 text-sm text-right text-purple-600 font-medium">
                            <?= number_format((int)$c['puntos_lealtad']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>