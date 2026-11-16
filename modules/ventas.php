<?php
// modules/ventas.php
// Listado de ventas + ver ticket + cancelar.
// La creación está en ventas_nueva.php (archivo aparte por complejidad).

require_once __DIR__ . '/../includes/auth.php';

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {
    case 'nueva':
        requiereRol('admin', 'farmaceutico', 'cajero');
        header('Location: /modules/ventas_nueva.php');
        exit;
    case 'cancelar':
        requiereRol('admin');   // Solo admin puede cancelar
        break;
    case 'ver':
    case 'listar':
    default:
        if (!esRol('admin','farmaceutico','cajero','auditor')) {
            requiereRol('admin');
        }
        break;
}

match($accion) {
    'ver'      => accionVer(),
    'cancelar' => accionCancelar(),
    default    => accionListar(),
};

// ============================================================
// ACCIÓN: LISTAR
// ============================================================
function accionListar(): void
{
    $pdo = pdoActual();

    // Stats del día y del mes
    $stats = $pdo->query("
        SELECT
            COALESCE(SUM(DATE(fecha_venta) = CURDATE() AND estado='completada'), 0) AS hoy_num,
            COALESCE(SUM(CASE WHEN DATE(fecha_venta)=CURDATE() AND estado='completada' THEN total ELSE 0 END), 0) AS hoy_total,
            COALESCE(SUM(DATE_FORMAT(fecha_venta,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND estado='completada'), 0) AS mes_num,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(fecha_venta,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND estado='completada' THEN total ELSE 0 END), 0) AS mes_total
        FROM ventas
    ")->fetch();

    // Filtros
    $busqueda  = trim($_GET['q'] ?? '');
    $canalF    = $_GET['canal'] ?? '';
    $estadoF   = $_GET['estado'] ?? '';
    $desde     = $_GET['desde'] ?? '';
    $hasta     = $_GET['hasta'] ?? '';
    $misVentas = ($_GET['mis'] ?? '') === '1';

    $ordenMap = ['fecha' => 'fecha_venta', 'folio' => 'folio',
                 'total' => 'total', 'estado' => 'estado'];
    $orden = $ordenMap[$_GET['orden'] ?? 'fecha'] ?? 'fecha_venta';
    $dir   = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // WHERE dinámico
    $where  = [];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(v.folio LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ? OR u.nombre_completo LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    if (in_array($canalF, ['local','online'])) {
        $where[] = "v.canal = ?";
        $params[] = $canalF;
    }
    if (in_array($estadoF, ['pendiente','completada','cancelada'])) {
        $where[] = "v.estado = ?";
        $params[] = $estadoF;
    }
    if ($desde !== '') { $where[] = "DATE(v.fecha_venta) >= ?"; $params[] = $desde; }
    if ($hasta !== '') { $where[] = "DATE(v.fecha_venta) <= ?"; $params[] = $hasta; }
    if ($misVentas)    { $where[] = "v.id_usuario = ?"; $params[] = (int)$_SESSION['usuario_id']; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT v.*,
               COALESCE(CONCAT(c.nombre, ' ', c.apellido), 'Público general') AS cliente_nombre,
               u.nombre_completo AS usuario_nombre,
               (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) AS num_items,
               (SELECT COALESCE(SUM(dv.cantidad),0) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) AS total_unidades
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
        $whereSql
        ORDER BY $orden $dir
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $hayFiltros = $busqueda !== '' || $canalF !== '' || $estadoF !== ''
                || $desde !== '' || $hasta !== '' || $misVentas;

    $tituloPagina = 'Ventas';
    require __DIR__ . '/../includes/header.php';
    ?>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ventas</h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= count($rows) ?> venta<?= count($rows) !== 1 ? 's' : '' ?>
                <?= $hayFiltros ? 'encontrada' . (count($rows) !== 1 ? 's' : '') . ' · <a href="/modules/ventas.php" class="text-primary-600 hover:underline">Limpiar filtros</a>' : '' ?>
            </p>
        </div>
        <?php if (esRol('admin','farmaceutico','cajero')): ?>
            <a href="/modules/ventas_nueva.php"
               class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-800
                      text-white font-semibold px-4 py-2 rounded-lg transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva venta
            </a>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$stats['hoy_num'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-4">
            <div class="text-xs text-emerald-700 uppercase tracking-wide">Ingreso hoy</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">$<?= number_format((float)$stats['hoy_total'], 2) ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Ventas del mes</div>
            <div class="text-2xl font-bold text-gray-900 mt-1"><?= (int)$stats['mes_num'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-primary-200 p-4">
            <div class="text-xs text-primary-700 uppercase tracking-wide">Ingreso del mes</div>
            <div class="text-2xl font-bold text-primary-600 mt-1">$<?= number_format((float)$stats['mes_total'], 2) ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-4">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" name="q" value="<?= e($busqueda) ?>"
                           placeholder="Buscar por folio, cliente o cajero..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                  focus:bg-white focus:border-primary-500 outline-none transition">
                </div>
            </div>
            <div class="lg:col-span-2">
                <select name="canal" class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Canal</option>
                    <option value="local"  <?= $canalF === 'local'  ? 'selected' : '' ?>>Local</option>
                    <option value="online" <?= $canalF === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <select name="estado" class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Estado</option>
                    <option value="completada" <?= $estadoF === 'completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="pendiente"  <?= $estadoF === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
                    <option value="cancelada"  <?= $estadoF === 'cancelada'  ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <input type="date" name="desde" value="<?= e($desde) ?>" placeholder="Desde"
                       class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none transition">
            </div>
            <div class="lg:col-span-2">
                <input type="date" name="hasta" value="<?= e($hasta) ?>" placeholder="Hasta"
                       class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none transition">
            </div>
            <div class="lg:col-span-12 flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="mis" value="1" <?= $misVentas ? 'checked' : '' ?>
                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    Solo mis ventas
                </label>
                <button type="submit" class="ml-auto bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    Aplicar filtros
                </button>
            </div>
        </div>
    </form>

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

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Folio</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cajero</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Canal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Items</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 mb-3">
                                <?= $hayFiltros ? 'No hay ventas que coincidan.' : 'Aún no hay ventas registradas.' ?>
                            </p>
                            <?php if (!$hayFiltros && esRol('admin','farmaceutico','cajero')): ?>
                                <a href="/modules/ventas_nueva.php"
                                   class="inline-block px-4 py-2 text-sm bg-primary-700 hover:bg-primary-800
                                          text-white rounded-lg transition">
                                    + Registrar la primera venta
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $v): ?>
                    <tr class="hover:bg-gray-50 transition <?= $v['estado'] === 'cancelada' ? 'opacity-60' : '' ?>">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= e($v['folio']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= e($v['cliente_nombre']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($v['usuario_nombre']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                        <?= $v['canal'] === 'online'
                                            ? 'bg-sky-100 text-sky-700'
                                            : 'bg-gray-100 text-gray-600' ?>">
                                <?= e(ucfirst($v['canal'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            <?= (int)$v['num_items'] ?> (<?= (int)$v['total_unidades'] ?> u)
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">
                            $<?= number_format((float)$v['total'], 2) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                        <?= match($v['estado']) {
                                            'completada' => 'bg-green-100 text-green-700',
                                            'cancelada'  => 'bg-red-100 text-red-700',
                                            default      => 'bg-amber-100 text-amber-700',
                                        } ?>">
                                <?= e(ucfirst($v['estado'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <a href="/modules/ventas.php?accion=ver&id=<?= (int)$v['id_venta'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                      bg-primary-700 hover:bg-primary-800 text-white transition">
                                Ver
                            </a>
                            <?php if (esRol('admin') && $v['estado'] !== 'cancelada'): ?>
                                <a href="/modules/ventas.php?accion=cancelar&id=<?= (int)$v['id_venta'] ?>"
                                   class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                          border border-red-300 text-red-700 hover:bg-red-50 transition">
                                    Cancelar
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: VER (ticket)
// ============================================================
function accionVer(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/ventas.php'); }

    $pdo = pdoActual();

    $stmt = $pdo->prepare("
        SELECT v.*,
               COALESCE(CONCAT(c.nombre,' ',c.apellido), 'Público general') AS cliente_nombre,
               c.email AS cliente_email,
               c.telefono AS cliente_telefono,
               u.nombre_completo AS usuario_nombre,
               d.alias AS dir_alias, d.calle AS dir_calle, d.colonia AS dir_colonia,
               d.ciudad AS dir_ciudad, d.estado_provincia AS dir_estado,
               d.codigo_postal AS dir_cp, p.nombre AS dir_pais,
               e.empresa_paqueteria, e.numero_guia, e.estado AS envio_estado,
               e.fecha_envio, e.fecha_entrega_real
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
        LEFT JOIN direcciones_cliente d ON v.id_direccion_envio = d.id_direccion
        LEFT JOIN paises p ON d.id_pais = p.id_pais
        LEFT JOIN envios e ON v.id_venta = e.id_venta
        WHERE v.id_venta = ?
    ");
    $stmt->execute([$id]);
    $venta = $stmt->fetch();

    if (!$venta) { flash('error', 'Venta no encontrada.'); redirect('/modules/ventas.php'); }

    $stmt = $pdo->prepare("
        SELECT dv.*, m.nombre AS medicamento, m.presentacion, m.codigo_barras
        FROM detalle_ventas dv
        INNER JOIN medicamentos m ON dv.id_medicamento = m.id_medicamento
        WHERE dv.id_venta = ?
        ORDER BY dv.id_detalle_venta
    ");
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM pagos WHERE id_venta = ? ORDER BY fecha_pago");
    $stmt->execute([$id]);
    $pagos = $stmt->fetchAll();

    $totalPagado = array_sum(array_column($pagos, 'monto'));

    $tituloPagina = 'Venta ' . $venta['folio'];
    require __DIR__ . '/../includes/header.php';
    ?>

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/ventas.php" class="hover:text-primary-600 transition">Ventas</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= e($venta['folio']) ?></span>
    </nav>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <?= e($venta['folio']) ?>
                <span class="text-xs px-2 py-1 rounded-full
                            <?= match($venta['estado']) {
                                'completada' => 'bg-green-100 text-green-700',
                                'cancelada'  => 'bg-red-100 text-red-700',
                                default      => 'bg-amber-100 text-amber-700',
                            } ?>">
                    <?= e(ucfirst($venta['estado'])) ?>
                </span>
                <span class="text-xs px-2 py-1 rounded-full
                            <?= $venta['canal'] === 'online'
                                ? 'bg-sky-100 text-sky-700'
                                : 'bg-gray-100 text-gray-600' ?>">
                    <?= e(ucfirst($venta['canal'])) ?>
                </span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Registrada el <?= date('d/m/Y \a \l\a\s H:i', strtotime($venta['fecha_venta'])) ?>
                por <?= e($venta['usuario_nombre']) ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/modules/ventas.php"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300
                      text-gray-700 rounded-lg hover:bg-gray-50 transition">
                ← Volver
            </a>
            <?php if (esRol('admin') && $venta['estado'] !== 'cancelada'): ?>
                <a href="/modules/ventas.php?accion=cancelar&id=<?= $id ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-red-300
                          text-red-700 rounded-lg hover:bg-red-50 transition">
                    Cancelar venta
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg = flash('exito')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Columna izquierda: Detalle -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Items -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                        Productos (<?= count($detalles) ?>)
                    </h2>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Medicamento</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Cant.</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Precio</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($detalles as $d): ?>
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="text-sm font-medium text-gray-900"><?= e($d['medicamento']) ?></div>
                                    <div class="text-xs text-gray-400"><?= e($d['presentacion']) ?></div>
                                </td>
                                <td class="px-5 py-3 text-sm text-right"><?= (int)$d['cantidad'] ?></td>
                                <td class="px-5 py-3 text-sm text-right">$<?= number_format((float)$d['precio_unitario'], 2) ?></td>
                                <td class="px-5 py-3 text-sm text-right font-medium">$<?= number_format((float)$d['subtotal'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="text-gray-900">$<?= number_format((float)$venta['subtotal'], 2) ?></span>
                        </div>
                        <?php if ((float)$venta['descuento'] > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Descuento</span>
                                <span>-$<?= number_format((float)$venta['descuento'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">IVA</span>
                            <span class="text-gray-900">$<?= number_format((float)$venta['iva'], 2) ?></span>
                        </div>
                        <?php if ((float)$venta['costo_envio'] > 0): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Envío</span>
                                <span class="text-gray-900">$<?= number_format((float)$venta['costo_envio'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between pt-2 border-t border-gray-200 mt-2">
                            <span class="font-semibold text-gray-900">Total</span>
                            <span class="font-bold text-lg text-gray-900">$<?= number_format((float)$venta['total'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagos -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                        Pagos (<?= count($pagos) ?>)
                    </h2>
                    <?php if ($totalPagado != $venta['total']): ?>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                            Pendiente: $<?= number_format((float)$venta['total'] - $totalPagado, 2) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                            Pagado completo
                        </span>
                    <?php endif; ?>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Método</th>
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Referencia</th>
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td class="px-5 py-3 text-sm">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                                        <?= e(ucfirst($p['metodo'])) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm font-mono text-gray-600"><?= e($p['referencia'] ?: '—') ?></td>
                                <td class="px-5 py-3 text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($p['fecha_pago'])) ?></td>
                                <td class="px-5 py-3 text-sm text-right font-medium">$<?= number_format((float)$p['monto'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Columna derecha: Cliente + Envío -->
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Cliente
                </h2>
                <div class="text-sm font-medium text-gray-900"><?= e($venta['cliente_nombre']) ?></div>
                <?php if ($venta['cliente_email']): ?>
                    <div class="text-sm text-gray-500 mt-1"><?= e($venta['cliente_email']) ?></div>
                <?php endif; ?>
                <?php if ($venta['cliente_telefono']): ?>
                    <div class="text-sm text-gray-500"><?= e($venta['cliente_telefono']) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($venta['canal'] === 'online' && $venta['dir_calle']): ?>
                <div class="bg-white rounded-xl shadow-sm border border-sky-200 p-5">
                    <h2 class="text-sm font-semibold text-sky-700 uppercase tracking-wide mb-3">
                        Dirección de envío
                    </h2>
                    <div class="text-sm text-gray-900 font-medium"><?= e($venta['dir_alias']) ?></div>
                    <div class="text-sm text-gray-600 mt-1"><?= e($venta['dir_calle']) ?></div>
                    <?php if ($venta['dir_colonia']): ?>
                        <div class="text-sm text-gray-500">Col. <?= e($venta['dir_colonia']) ?></div>
                    <?php endif; ?>
                    <div class="text-sm text-gray-600"><?= e($venta['dir_ciudad']) ?>, <?= e($venta['dir_estado']) ?></div>
                    <div class="text-sm text-gray-500">CP <?= e($venta['dir_cp']) ?> · <?= e($venta['dir_pais']) ?></div>
                </div>

                <?php if ($venta['empresa_paqueteria']): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            Envío
                        </h2>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Paquetería</span>
                                <span class="text-gray-900"><?= e($venta['empresa_paqueteria']) ?></span>
                            </div>
                            <?php if ($venta['numero_guia']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Guía</span>
                                    <span class="text-gray-900 font-mono text-xs"><?= e($venta['numero_guia']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Estado</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">
                                    <?= e(ucfirst($venta['envio_estado'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: CANCELAR
// ============================================================
function accionCancelar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/ventas.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id]);
    $venta = $stmt->fetch();

    if (!$venta) { flash('error', 'Venta no encontrada.'); redirect('/modules/ventas.php'); }
    if ($venta['estado'] === 'cancelada') {
        flash('error', 'La venta ya está cancelada.');
        redirect('/modules/ventas.php');
    }

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $pdo->beginTransaction();

            // Cambiar estado → el trigger AFTER UPDATE devuelve el stock
            $pdo->prepare("UPDATE ventas SET estado = 'cancelada' WHERE id_venta = ?")
                ->execute([$id]);

            // Auditoría
            $pdo->prepare("
                INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                VALUES (USER(), 'ventas', 'CANCELACION', ?, ?)
            ")->execute([$id, 'Cancelada por ' . $_SESSION['nombre']]);

            $pdo->commit();
            flash('exito', 'Venta cancelada. El stock fue devuelto automáticamente.');
            redirect('/modules/ventas.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errores[] = 'Error al cancelar: ' . $ex->getMessage();
        }
    }

    $tituloPagina = 'Cancelar venta';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/ventas.php" class="hover:text-primary-600">Ventas</a>
            <span>›</span>
            <a href="/modules/ventas.php?accion=ver&id=<?= $id ?>" class="hover:text-primary-600"><?= e($venta['folio']) ?></a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Cancelar</span>
        </nav>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Cancelar venta</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Esta acción cancelará la venta y devolverá el stock de todos los productos al inventario.
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Folio:</span>
                    <strong class="text-gray-900 font-mono"><?= e($venta['folio']) ?></strong>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Fecha:</span>
                    <span class="text-gray-700"><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Total:</span>
                    <span class="text-gray-900 font-semibold">$<?= number_format((float)$venta['total'], 2) ?></span>
                </div>
            </div>

            <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                <strong>¿Qué sucederá?</strong>
                <ul class="list-disc list-inside mt-2 space-y-1">
                    <li>La venta se marcará como <strong>cancelada</strong>.</li>
                    <li>El stock de todos los productos se devolverá al inventario.</li>
                    <li>La operación quedará en la bitácora de auditoría.</li>
                    <li><strong>El pago no se reembolsa automáticamente</strong> (debe gestionarse por separado).</li>
                </ul>
            </div>

            <?php foreach ($errores as $err): ?>
                <div class="mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/ventas.php?accion=ver&id=<?= $id ?>"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700
                          hover:bg-gray-50 transition text-center">
                    Volver sin cancelar
                </a>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2
                               rounded-lg transition shadow-sm">
                    Sí, cancelar venta
                </button>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
}