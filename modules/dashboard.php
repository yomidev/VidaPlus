<?php
// modules/dashboard.php — Dashboard unificado con reportes por rol
require_once __DIR__ . '/../includes/auth.php';

$tituloPagina = 'Panel';
require_once __DIR__ . '/../includes/header.php';

$pdo = pdoActual();
$rol = $_SESSION['rol_nombre'];
$nombre = $_SESSION['nombre'];
$esEmpleado = $_SESSION['tipo'] === 'empleado';

// Saludo según la hora
$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
?>

<!-- Bienvenida -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">
        <?= $saludo ?>, <?= e($nombre) ?> 👋
    </h1>
    <p class="text-gray-500 mt-1">
        Estás conectado como
        <span class="inline-block bg-primary-100 text-primary-700 text-xs font-semibold
                     px-2 py-1 rounded-full uppercase tracking-wide">
            <?= e($rol) ?>
        </span>
        · <?= date('l, d \d\e F \d\e Y') ?>
    </p>
</div>

<?php if ($rol === 'admin' || $rol === 'farmaceutico'): ?>
    <?php
    // ============================================================
    // DASHBOARD ADMIN / FARMACÉUTICO
    // ============================================================

    // KPIs con procedimiento OUT
    $pdo->query("CALL sp_reporte_resumen_dashboard(@vh, @ih, @vm, @im, @ct, @sb, @tm)")->closeCursor();
    $kpis = $pdo->query("
        SELECT @vh AS ventas_hoy, @ih AS ingreso_hoy,
               @vm AS ventas_mes, @im AS ingreso_mes,
               @ct AS clientes_total, @sb AS stock_bajo,
               @tm AS ticket_mes
    ")->fetch();

    // Ventas últimos 7 días
    $stmt = $pdo->prepare("CALL sp_reporte_ventas_periodo(?, ?)");
    $stmt->execute([date('Y-m-d', strtotime('-6 days')), date('Y-m-d')]);
    $ventasSemana = $stmt->fetchAll();
    $stmt->closeCursor();

    // Top 5 medicamentos
    $stmt = $pdo->prepare("CALL sp_reporte_top_medicamentos(5)");
    $stmt->execute();
    $topMedicamentos = $stmt->fetchAll();
    $stmt->closeCursor();

    // Stock bajo (5 más críticos)
    $stmt = $pdo->prepare("CALL sp_reporte_stock_bajo()");
    $stmt->execute();
    $stockBajo = array_slice($stmt->fetchAll(), 0, 5);
    $stmt->closeCursor();

    // Top 5 clientes
    $stmt = $pdo->prepare("CALL sp_reporte_clientes_top(5)");
    $stmt->execute();
    $topClientes = $stmt->fetchAll();
    $stmt->closeCursor();

    // Datos del gráfico
    $dias = [];
    foreach (array_reverse($ventasSemana) as $v) {
        $dias[] = [
            'label' => date('d/m', strtotime($v['fecha'])),
            'valor' => (float)$v['ingreso_total'],
            'num'   => (int)$v['num_ventas'],
        ];
    }
    $maxIngreso = max(array_column($dias, 'valor') ?: [1]);
    if ($maxIngreso <= 0) $maxIngreso = 1;
    ?>

    <!-- KPIs principales -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</span>
                <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900"><?= (int)$kpis['ventas_hoy'] ?></div>
            <div class="text-sm text-primary-600 font-medium mt-1">
                $<?= number_format((float)$kpis['ingreso_hoy'], 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-emerald-700 uppercase tracking-wide">Mes actual</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-emerald-600"><?= (int)$kpis['ventas_mes'] ?></div>
            <div class="text-sm text-emerald-600 font-medium mt-1">
                $<?= number_format((float)$kpis['ingreso_mes'], 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-500 uppercase tracking-wide">Clientes</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900"><?= (int)$kpis['clientes_total'] ?></div>
            <div class="text-xs text-gray-500 mt-1">
                Ticket mes: $<?= number_format((float)$kpis['ticket_mes'], 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-amber-700 uppercase tracking-wide">Stock bajo</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-amber-600"><?= (int)$kpis['stock_bajo'] ?></div>
            <div class="text-xs text-amber-700 mt-1">
                <a href="/modules/reportes/productos.php?tab=stock" class="hover:underline">
                    Ver productos →
                </a>
            </div>
        </div>
    </div>

    <!-- Gráfico de ventas últimos 7 días -->
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

        <?php if (empty($dias) || array_sum(array_column($dias, 'valor')) === 0.0): ?>
            <div class="h-40 flex items-center justify-center text-sm text-gray-400">
                Sin ventas en los últimos 7 días
            </div>
        <?php else: ?>
            <div class="flex items-end justify-between gap-2 h-40">
                <?php foreach ($dias as $d):
                    $altura = max(4, ($d['valor'] / $maxIngreso) * 100);
                ?>
                    <div class="flex-1 flex flex-col items-center gap-2">
                        <div class="w-full flex items-end justify-center h-full">
                            <div class="w-full max-w-[40px] bg-gradient-to-t from-primary-600 to-primary-400
                                        rounded-t-lg relative group transition-all
                                        hover:from-primary-700 hover:to-primary-500"
                                 style="height: <?= $altura ?>%">
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100
                                            bg-gray-900 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap
                                            transition-opacity pointer-events-none z-10">
                                    $<?= number_format($d['valor'], 0) ?> · <?= $d['num'] ?> ventas
                                </div>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500"><?= $d['label'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Grid: Top medicamentos + Stock bajo -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">

        <!-- Top 5 medicamentos -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                    Top 5 medicamentos
                </h2>
                <a href="/modules/reportes/productos.php" class="text-xs text-primary-600 hover:underline">
                    Ver más
                </a>
            </div>
            <?php if (empty($topMedicamentos)): ?>
                <div class="p-8 text-center text-sm text-gray-400">Sin ventas registradas</div>
            <?php else: ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($topMedicamentos as $i => $m): ?>
                        <li class="px-5 py-3 flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center
                                        text-xs font-bold flex-shrink-0
                                        <?= $i === 0 ? 'bg-amber-100 text-amber-700'
                                            : ($i === 1 ? 'bg-gray-200 text-gray-700'
                                                       : 'bg-primary-100 text-primary-700') ?>">
                                <?= $i + 1 ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    <?= e($m['nombre']) ?>
                                </div>
                                <div class="text-xs text-gray-400"><?= e($m['categoria']) ?></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= (int)$m['unidades_vendidas'] ?> u
                                </div>
                                <div class="text-xs text-emerald-600">
                                    $<?= number_format((float)$m['ingreso_generado'], 0) ?>
                                </div>
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
                <div class="p-8 text-center text-sm text-emerald-600">
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
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    <?= e($m['nombre']) ?>
                                </div>
                                <div class="text-xs text-gray-400"><?= e($m['categoria']) ?></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold text-red-600">
                                    <?= (int)$m['stock'] ?> / <?= (int)$m['stock_minimo'] ?>
                                </div>
                                <div class="text-xs text-amber-700">faltan <?= (int)$m['faltante'] ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 5 clientes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-5">
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
            <div class="overflow-x-auto">
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
            </div>
        <?php endif; ?>
    </div>

    <!-- Accesos rápidos -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="/modules/ventas.php?accion=nueva"
           class="bg-primary-700 hover:bg-primary-800 text-white rounded-xl p-4
                  flex flex-col items-center gap-2 transition shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-sm font-medium">Nueva venta</span>
        </a>
        <a href="/modules/medicamentos.php?accion=crear"
           class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 rounded-xl p-4
                  flex flex-col items-center gap-2 transition">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            <span class="text-sm font-medium">Nuevo medicamento</span>
        </a>
        <a href="/modules/reportes.php"
           class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 rounded-xl p-4
                  flex flex-col items-center gap-2 transition">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span class="text-sm font-medium">Reportes</span>
        </a>
        <a href="/modules/auditoria.php"
           class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 rounded-xl p-4
                  flex flex-col items-center gap-2 transition">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span class="text-sm font-medium">Auditoría</span>
        </a>
    </div>

<?php elseif ($rol === 'cajero'): ?>
    <?php
    // ============================================================
    // DASHBOARD CAJERO
    // ============================================================
    $idUsuario = (int)$_SESSION['usuario_id'];

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(DATE(fecha_venta)=CURDATE() AND estado='completada'), 0) AS ventas_hoy,
            COALESCE(SUM(CASE WHEN DATE(fecha_venta)=CURDATE() AND estado='completada' THEN total ELSE 0 END), 0) AS ingreso_hoy,
            COALESCE(SUM(DATE_FORMAT(fecha_venta,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND estado='completada'), 0) AS ventas_mes,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(fecha_venta,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND estado='completada' THEN total ELSE 0 END), 0) AS ingreso_mes
        FROM ventas
        WHERE id_usuario = ?
    ");
    $stmt->execute([$idUsuario]);
    $mis = $stmt->fetch();

    // Últimas 5 ventas del cajero
    $stmt = $pdo->prepare("
        SELECT id_venta, folio, fecha_venta, total, estado, canal
        FROM ventas
        WHERE id_usuario = ?
        ORDER BY fecha_venta DESC
        LIMIT 5
    ");
    $stmt->execute([$idUsuario]);
    $ultimas = $stmt->fetchAll();
    ?>

    <!-- Mis KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Mis ventas hoy</div>
            <div class="text-3xl font-bold text-gray-900 mt-1"><?= (int)$mis['ventas_hoy'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <div class="text-xs text-emerald-700 uppercase tracking-wide">Ingreso hoy</div>
            <div class="text-3xl font-bold text-emerald-600 mt-1">
                $<?= number_format((float)$mis['ingreso_hoy'], 2) ?>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Mis ventas del mes</div>
            <div class="text-3xl font-bold text-gray-900 mt-1"><?= (int)$mis['ventas_mes'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-primary-200 p-5">
            <div class="text-xs text-primary-700 uppercase tracking-wide">Ingreso del mes</div>
            <div class="text-3xl font-bold text-primary-600 mt-1">
                $<?= number_format((float)$mis['ingreso_mes'], 2) ?>
            </div>
        </div>
    </div>

    <!-- CTA principal -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <a href="/modules/ventas_nueva.php"
           class="bg-primary-700 hover:bg-primary-800 text-white rounded-xl p-6
                  flex items-center gap-4 transition shadow-md">
            <div class="w-14 h-14 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <div class="text-lg font-bold">Nueva venta</div>
                <div class="text-sm text-white/80">Registrar una venta local u online</div>
            </div>
        </a>

        <a href="/modules/ventas.php?mis=1"
           class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 rounded-xl p-6
                  flex items-center gap-4 transition shadow-sm">
            <div class="w-14 h-14 rounded-xl bg-primary-50 text-primary-600
                        flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="text-lg font-bold">Mis ventas</div>
                <div class="text-sm text-gray-500">Ver historial de ventas propias</div>
            </div>
        </a>
    </div>

    <!-- Últimas ventas -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Mis últimas 5 ventas
            </h2>
            <a href="/modules/ventas.php?mis=1" class="text-xs text-primary-600 hover:underline">
                Ver todas →
            </a>
        </div>
        <?php if (empty($ultimas)): ?>
            <div class="p-8 text-center text-sm text-gray-400">
                Aún no has registrado ventas.
                <a href="/modules/ventas_nueva.php" class="text-primary-600 hover:underline ml-1">
                    Registrar la primera
                </a>
            </div>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Folio</th>
                        <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-5 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Canal</th>
                        <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                        <th class="px-5 py-2 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($ultimas as $v): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-sm font-mono text-gray-900"><?= e($v['folio']) ?></td>
                            <td class="px-5 py-3 text-sm text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                            <?= $v['canal'] === 'online'
                                                ? 'bg-sky-100 text-sky-700'
                                                : 'bg-gray-100 text-gray-600' ?>">
                                    <?= e(ucfirst($v['canal'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-right font-medium text-gray-900">
                                $<?= number_format((float)$v['total'], 2) ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                            <?= match($v['estado']) {
                                                'completada' => 'bg-green-100 text-green-700',
                                                'cancelada'  => 'bg-red-100 text-red-700',
                                                default      => 'bg-amber-100 text-amber-700',
                                            } ?>">
                                    <?= e(ucfirst($v['estado'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="/modules/ventas.php?accion=ver&id=<?= (int)$v['id_venta'] ?>"
                                   class="text-xs text-primary-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($rol === 'auditor'): ?>
    <?php
    // ============================================================
    // DASHBOARD AUDITOR
    // ============================================================
    $statsAudit = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(DATE(fecha_evento)=CURDATE()), 0) AS hoy,
            COUNT(DISTINCT usuario_bd) AS usuarios,
            COUNT(DISTINCT tabla_afectada) AS tablas
        FROM auditoria
    ")->fetch();

    // Últimos 5 registros
    $ultimos = $pdo->query("
        SELECT * FROM auditoria ORDER BY fecha_evento DESC LIMIT 5
    ")->fetchAll();

    // Ventas del mes (para revisión)
    $ventasMes = $pdo->query("
        SELECT COUNT(*) AS num, COALESCE(SUM(total),0) AS ingreso
        FROM ventas
        WHERE DATE_FORMAT(fecha_venta,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
          AND estado='completada'
    ")->fetch();
    ?>

    <!-- KPIs auditoría -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Registros totales</div>
            <div class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((int)$statsAudit['total']) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-5">
            <div class="text-xs text-blue-700 uppercase tracking-wide">Hoy</div>
            <div class="text-3xl font-bold text-blue-600 mt-1"><?= (int)$statsAudit['hoy'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <div class="text-xs text-emerald-700 uppercase tracking-wide">Usuarios BD</div>
            <div class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)$statsAudit['usuarios'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-5">
            <div class="text-xs text-purple-700 uppercase tracking-wide">Tablas auditadas</div>
            <div class="text-3xl font-bold text-purple-600 mt-1"><?= (int)$statsAudit['tablas'] ?></div>
        </div>
    </div>

    <!-- Resumen del mes -->
    <div class="bg-gradient-to-br from-primary-700 to-primary-500 text-white rounded-xl p-6 mb-5 shadow-md">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-white/70">Ventas del mes</div>
                <div class="text-4xl font-bold mt-1"><?= (int)$ventasMes['num'] ?></div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-white/70">Ingreso del mes</div>
                <div class="text-4xl font-bold mt-1">$<?= number_format((float)$ventasMes['ingreso'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Accesos rápidos -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <a href="/modules/auditoria.php"
           class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl p-6
                  flex items-center gap-4 transition shadow-sm">
            <div class="w-14 h-14 rounded-xl bg-slate-100 text-slate-600
                        flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900">Ver bitácora completa</div>
                <div class="text-sm text-gray-500">Revisar todos los registros</div>
            </div>
        </a>

        <a href="/modules/reportes.php"
           class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl p-6
                  flex items-center gap-4 transition shadow-sm">
            <div class="w-14 h-14 rounded-xl bg-primary-50 text-primary-600
                        flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900">Reportes</div>
                <div class="text-sm text-gray-500">Análisis del negocio</div>
            </div>
        </a>
    </div>

    <!-- Últimos 5 registros de auditoría -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Últimos 5 eventos
            </h2>
            <a href="/modules/auditoria.php" class="text-xs text-primary-600 hover:underline">
                Ver todos →
            </a>
        </div>
        <?php if (empty($ultimos)): ?>
            <div class="p-8 text-center text-sm text-gray-400">Sin registros</div>
        <?php else: ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($ultimos as $r):
                    $badgeClass = match(true) {
                        str_contains($r['accion'], 'INSERT')      => 'bg-emerald-100 text-emerald-700',
                        str_contains($r['accion'], 'DELETE')      => 'bg-red-100 text-red-700',
                        str_contains($r['accion'], 'CANCELACION') => 'bg-red-100 text-red-700',
                        str_contains($r['accion'], 'UPDATE')      => 'bg-amber-100 text-amber-700',
                        default                                    => 'bg-gray-100 text-gray-700',
                    };
                ?>
                    <li class="px-5 py-3 flex items-center gap-3">
                        <div class="text-xs text-gray-400 whitespace-nowrap w-24 flex-shrink-0">
                            <?= date('d/m H:i', strtotime($r['fecha_evento'])) ?>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0 <?= $badgeClass ?>">
                            <?= e($r['accion']) ?>
                        </span>
                        <div class="flex-1 min-w-0 text-sm text-gray-700 truncate">
                            <?= e($r['detalle'] ?: $r['tabla_afectada']) ?>
                        </div>
                        <div class="text-xs text-gray-400 font-mono flex-shrink-0">
                            <?= e($r['usuario_bd']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

<?php elseif ($rol === 'cliente'): ?>
    <?php
    // ============================================================
    // DASHBOARD CLIENTE (PORTAL)
    // ============================================================
    $productos = (int)$pdo->query("SELECT COUNT(*) FROM v_catalogo_online")->fetchColumn();
    $categorias = (int)$pdo->query("SELECT COUNT(DISTINCT categoria) FROM v_catalogo_online")->fetchColumn();

    // Mis compras (si tiene historial)
    $idCliente = (int)$_SESSION['usuario_id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS num, COALESCE(SUM(total),0) AS total
        FROM ventas
        WHERE id_cliente = ? AND estado='completada'
    ");
    $stmt->execute([$idCliente]);
    $misCompras = $stmt->fetch();

    // Puntos de lealtad
    $stmt = $pdo->prepare("SELECT puntos_lealtad FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$idCliente]);
    $puntos = (int)$stmt->fetchColumn();
    ?>

    <!-- Bienvenida cliente -->
    <div class="bg-gradient-to-br from-primary-700 to-primary-500 text-white rounded-2xl p-8 mb-6 shadow-lg">
        <h2 class="text-2xl font-bold mb-2">¡Bienvenido a VidaPlus!</h2>
        <p class="text-white/80 mb-6">Tu farmacia en línea, siempre disponible.</p>
        <a href="/modules/medicamentos.php"
           class="inline-flex items-center gap-2 bg-white text-primary-700 font-semibold
                  px-5 py-2.5 rounded-lg hover:bg-gray-50 transition shadow">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Ver catálogo
        </a>
    </div>

    <!-- Info del cliente -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Productos disponibles</div>
            <div class="text-3xl font-bold text-gray-900 mt-1"><?= $productos ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Categorías</div>
            <div class="text-3xl font-bold text-gray-900 mt-1"><?= $categorias ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-5">
            <div class="text-xs text-purple-700 uppercase tracking-wide">Mis puntos</div>
            <div class="text-3xl font-bold text-purple-600 mt-1"><?= number_format($puntos) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <div class="text-xs text-emerald-700 uppercase tracking-wide">Mis compras</div>
            <div class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)$misCompras['num'] ?></div>
            <?php if ($misCompras['num'] > 0): ?>
                <div class="text-xs text-emerald-700 mt-1">
                    $<?= number_format((float)$misCompras['total'], 2) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nota informativa -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div class="text-sm text-amber-800">
            <strong>Recuerda:</strong> solo mostramos medicamentos sin receta médica.
            Los medicamentos controlados deben adquirirse en sucursal.
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>