<?php
// modules/auditoria.php
// Solo lectura. Admin y auditor pueden ver la bitácora.

require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'auditor');

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'ver'      => accionVer(),
    'exportar' => accionExportar(),
    default    => accionListar(),
};

// ============================================================
// ACCIÓN: LISTAR
// ============================================================
function accionListar(): void
{
    $pdo = pdoActual();

    // 1. Stats globales
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                                 AS total,
            COALESCE(SUM(DATE(fecha_evento)=CURDATE()), 0)           AS hoy,
            COUNT(DISTINCT usuario_bd)                               AS usuarios,
            COUNT(DISTINCT tabla_afectada)                           AS tablas
        FROM auditoria
    ")->fetch();

    // 2. Filtros
    $busqueda = trim($_GET['q'] ?? '');
    $tablaF   = $_GET['tabla'] ?? '';
    $accionF  = $_GET['accion_f'] ?? '';
    $usuarioF = $_GET['usuario'] ?? '';
    $desde    = $_GET['desde'] ?? '';
    $hasta    = $_GET['hasta'] ?? '';

    $ordenMap = ['fecha' => 'fecha_evento', 'tabla' => 'tabla_afectada',
                 'accion' => 'accion', 'usuario' => 'usuario_bd'];
    $orden = $ordenMap[$_GET['orden'] ?? 'fecha'] ?? 'fecha_evento';
    $dir   = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // 3. WHERE dinámico
    $where  = [];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(detalle LIKE ? OR tabla_afectada LIKE ? OR usuario_bd LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    if ($tablaF !== '') {
        $where[] = "tabla_afectada = ?";
        $params[] = $tablaF;
    }
    if ($accionF !== '') {
        $where[] = "accion = ?";
        $params[] = $accionF;
    }
    if ($usuarioF !== '') {
        $where[] = "usuario_bd = ?";
        $params[] = $usuarioF;
    }
    if ($desde !== '') { $where[] = "DATE(fecha_evento) >= ?"; $params[] = $desde; }
    if ($hasta !== '') { $where[] = "DATE(fecha_evento) <= ?"; $params[] = $hasta; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 4. Paginación
    $porPagina = 50;
    $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
    $offset    = ($pagina - 1) * $porPagina;

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM auditoria $whereSql");
    $stmtCount->execute($params);
    $totalFiltrado = (int)$stmtCount->fetchColumn();
    $totalPaginas  = max(1, (int)ceil($totalFiltrado / $porPagina));

    // 5. Query principal con LIMIT
    $stmt = $pdo->prepare("
        SELECT *
        FROM auditoria
        $whereSql
        ORDER BY $orden $dir
        LIMIT $porPagina OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 6. Opciones para selects
    $tablas = $pdo->query("SELECT DISTINCT tabla_afectada FROM auditoria ORDER BY tabla_afectada")->fetchAll(PDO::FETCH_COLUMN);
    $acciones = $pdo->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
    $usuarios = $pdo->query("SELECT DISTINCT usuario_bd FROM auditoria ORDER BY usuario_bd")->fetchAll(PDO::FETCH_COLUMN);

    $hayFiltros = $busqueda !== '' || $tablaF !== '' || $accionF !== ''
                || $usuarioF !== '' || $desde !== '' || $hasta !== '';

    $tituloPagina = 'Auditoría';
    require __DIR__ . '/../includes/header.php';
    ?>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bitácora de auditoría</h1>
            <p class="text-sm text-gray-500 mt-1">
                Registro automático de operaciones realizadas en la base de datos
            </p>
        </div>
        <a href="/modules/auditoria.php?accion=exportar<?= $hayFiltros ? '&' . http_build_query(array_intersect_key($_GET, array_flip(['q','tabla','accion_f','usuario','desde','hasta']))) : '' ?>"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300
                  text-gray-700 rounded-lg hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            Exportar CSV
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Total</div>
                <div class="text-2xl font-bold text-gray-900"><?= number_format((int)$stats['total']) ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-blue-700 uppercase tracking-wide">Hoy</div>
                <div class="text-2xl font-bold text-blue-600"><?= number_format((int)$stats['hoy']) ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-emerald-700 uppercase tracking-wide">Usuarios BD</div>
                <div class="text-2xl font-bold text-emerald-600"><?= (int)$stats['usuarios'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-purple-700 uppercase tracking-wide">Tablas</div>
                <div class="text-2xl font-bold text-purple-600"><?= (int)$stats['tablas'] ?></div>
            </div>
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
                           placeholder="Buscar en detalle o usuario..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                  focus:bg-white focus:border-primary-500 outline-none transition">
                </div>
            </div>

            <div class="lg:col-span-2">
                <select name="tabla"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Tabla</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= e($t) ?>" <?= $tablaF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2">
                <select name="accion_f"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Acción</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= e($a) ?>" <?= $accionF === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2">
                <input type="date" name="desde" value="<?= e($desde) ?>"
                       class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                              focus:bg-white focus:border-primary-500 outline-none transition">
            </div>
            <div class="lg:col-span-2">
                <input type="date" name="hasta" value="<?= e($hasta) ?>"
                       class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                              focus:bg-white focus:border-primary-500 outline-none transition">
            </div>
        </div>

        <?php if ($hayFiltros): ?>
            <div class="mt-3 flex items-center gap-2">
                <span class="text-xs text-gray-500">Filtros activos:</span>
                <a href="/modules/auditoria.php" class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                    Limpiar filtros
                </a>
            </div>
        <?php endif; ?>
    </form>

    <!-- Contador -->
    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-gray-500">
            Mostrando <strong><?= count($rows) ?></strong> de <strong><?= number_format($totalFiltrado) ?></strong> registros
            <?= $hayFiltros ? '(filtrados)' : '' ?>
        </p>
        <button onclick="document.querySelector('form').submit()"
                class="text-xs px-3 py-1.5 rounded-lg bg-primary-700 hover:bg-primary-800 text-white transition">
            Aplicar filtros
        </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Usuario BD</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tabla</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Acción</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Registro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Detalle</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500">
                                <?= $hayFiltros ? 'No hay registros que coincidan con los filtros.' : 'Aún no hay registros de auditoría.' ?>
                            </p>
                            <?php if ($hayFiltros): ?>
                                <a href="/modules/auditoria.php" class="inline-block mt-3 text-sm text-primary-600 hover:underline">
                                    Limpiar filtros
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            <?= date('d/m/Y', strtotime($r['fecha_evento'])) ?>
                            <div class="text-xs text-gray-400"><?= date('H:i:s', strtotime($r['fecha_evento'])) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">
                                <?= e($r['usuario_bd']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded">
                                <?= e($r['tabla_afectada']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php
                            // Colores por tipo de acción
                            $badgeClass = match(true) {
                                str_contains($r['accion'], 'INSERT')     => 'bg-emerald-100 text-emerald-700',
                                str_contains($r['accion'], 'DELETE')     => 'bg-red-100 text-red-700',
                                str_contains($r['accion'], 'CANCELACION')=> 'bg-red-100 text-red-700',
                                str_contains($r['accion'], 'UPDATE')     => 'bg-amber-100 text-amber-700',
                                default                                   => 'bg-gray-100 text-gray-700',
                            };
                            ?>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeClass ?>">
                                <?= e($r['accion']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= $r['id_registro'] ? '#' . (int)$r['id_registro'] : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate"
                            title="<?= e($r['detalle'] ?? '') ?>">
                            <?= e($r['detalle'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Página <?= $pagina ?> de <?= $totalPaginas ?>
                </div>
                <div class="flex gap-1">
                    <?php
                    // Helper para URLs preservando filtros
                    $urlPagina = function($p) use ($hayFiltros) {
                        $params = $_GET;
                        $params['pagina'] = $p;
                        return '/modules/auditoria.php?' . http_build_query($params);
                    };
                    $mostrar = [];
                    // Siempre mostrar primera, última, actual y vecinas
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        if ($i == 1 || $i == $totalPaginas || abs($i - $pagina) <= 1) {
                            $mostrar[] = $i;
                        }
                    }
                    $mostrar = array_unique($mostrar);
                    sort($mostrar);
                    $prev = null;
                    foreach ($mostrar as $p):
                        if ($prev !== null && $p - $prev > 1): ?>
                            <span class="px-2 py-1 text-xs text-gray-400">…</span>
                        <?php endif; ?>
                        <a href="<?= e($urlPagina($p)) ?>"
                           class="px-3 py-1 text-xs rounded transition
                                  <?= $p === $pagina
                                      ? 'bg-primary-700 text-white'
                                      : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php $prev = $p; endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: VER (detalle completo de un registro)
// ============================================================
function accionVer(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/auditoria.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM auditoria WHERE id_auditoria = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if (!$r) { flash('error', 'Registro no encontrado.'); redirect('/modules/auditoria.php'); }

    $tituloPagina = 'Registro de auditoría';
    require __DIR__ . '/../includes/header.php';
    ?>

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/auditoria.php" class="hover:text-primary-600 transition">Auditoría</a>
        <span>›</span>
        <span class="text-gray-900 font-medium">Registro #<?= (int)$r['id_auditoria'] ?></span>
    </nav>

    <div class="max-w-2xl">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Registro #<?= (int)$r['id_auditoria'] ?></h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= date('d/m/Y \a \l\a\s H:i:s', strtotime($r['fecha_evento'])) ?>
                    </p>
                </div>
            </div>

            <dl class="space-y-3">
                <div class="grid grid-cols-3 gap-2 py-2 border-b border-gray-100">
                    <dt class="text-sm text-gray-500">Usuario BD</dt>
                    <dd class="col-span-2 text-sm">
                        <span class="font-mono bg-gray-100 text-gray-700 px-2 py-0.5 rounded">
                            <?= e($r['usuario_bd']) ?>
                        </span>
                    </dd>
                </div>
                <div class="grid grid-cols-3 gap-2 py-2 border-b border-gray-100">
                    <dt class="text-sm text-gray-500">Tabla afectada</dt>
                    <dd class="col-span-2 text-sm">
                        <a href="/modules/auditoria.php?tabla=<?= urlencode($r['tabla_afectada']) ?>"
                           class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded hover:bg-purple-200">
                            <?= e($r['tabla_afectada']) ?>
                        </a>
                    </dd>
                </div>
                <div class="grid grid-cols-3 gap-2 py-2 border-b border-gray-100">
                    <dt class="text-sm text-gray-500">Acción</dt>
                    <dd class="col-span-2 text-sm">
                        <span class="text-xs px-2 py-0.5 rounded-full
                                    <?= str_contains($r['accion'], 'INSERT') ? 'bg-emerald-100 text-emerald-700'
                                        : (str_contains($r['accion'], 'DELETE') || str_contains($r['accion'], 'CANCELACION')
                                            ? 'bg-red-100 text-red-700'
                                            : (str_contains($r['accion'], 'UPDATE') ? 'bg-amber-100 text-amber-700'
                                                                                    : 'bg-gray-100 text-gray-700')) ?>">
                            <?= e($r['accion']) ?>
                        </span>
                    </dd>
                </div>
                <div class="grid grid-cols-3 gap-2 py-2 border-b border-gray-100">
                    <dt class="text-sm text-gray-500">ID del registro</dt>
                    <dd class="col-span-2 text-sm text-gray-900">
                        <?= $r['id_registro'] ? '#' . (int)$r['id_registro'] : '—' ?>
                    </dd>
                </div>
                <div class="grid grid-cols-3 gap-2 py-2">
                    <dt class="text-sm text-gray-500">Detalle</dt>
                    <dd class="col-span-2 text-sm text-gray-900 whitespace-pre-wrap"><?= e($r['detalle'] ?? '—') ?></dd>
                </div>
            </dl>

            <div class="mt-6 pt-5 border-t border-gray-100 flex justify-end gap-2">
                <a href="/modules/auditoria.php"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                    ← Volver
                </a>
            </div>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: EXPORTAR CSV
// ============================================================
function accionExportar(): void
{
    $pdo = pdoActual();

    $busqueda = trim($_GET['q'] ?? '');
    $tablaF   = $_GET['tabla'] ?? '';
    $accionF  = $_GET['accion_f'] ?? '';
    $usuarioF = $_GET['usuario'] ?? '';
    $desde    = $_GET['desde'] ?? '';
    $hasta    = $_GET['hasta'] ?? '';

    $where  = [];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(detalle LIKE ? OR tabla_afectada LIKE ? OR usuario_bd LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    if ($tablaF !== '') { $where[] = "tabla_afectada = ?"; $params[] = $tablaF; }
    if ($accionF !== '') { $where[] = "accion = ?"; $params[] = $accionF; }
    if ($usuarioF !== '') { $where[] = "usuario_bd = ?"; $params[] = $usuarioF; }
    if ($desde !== '') { $where[] = "DATE(fecha_evento) >= ?"; $params[] = $desde; }
    if ($hasta !== '') { $where[] = "DATE(fecha_evento) <= ?"; $params[] = $hasta; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT id_auditoria, usuario_bd, tabla_afectada, accion,
               id_registro, detalle, fecha_evento
        FROM auditoria
        $whereSql
        ORDER BY fecha_evento DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_' . date('Y-m-d_H-i') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['ID', 'Usuario BD', 'Tabla', 'Acción', 'ID Registro', 'Detalle', 'Fecha']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_auditoria'],
            $r['usuario_bd'],
            $r['tabla_afectada'],
            $r['accion'],
            $r['id_registro'],
            $r['detalle'],
            $r['fecha_evento'],
        ]);
    }
    fclose($out);
    exit;
}