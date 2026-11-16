<?php
// modules/clientes.php
// Solo admin. CRUD completo de clientes.
// Las direcciones se gestionan en módulo aparte (siguiente iteración).

require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin');

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'crear'    => accionCrear(),
    'editar'   => accionEditar(),
    'eliminar' => accionEliminar(),
    'exportar' => accionExportar(),
    'ver'      => accionVer(), 
    default    => accionListar(),
};

// Helper: URLs con filtros preservados
function urlListar(array $cambios = []): string
{
    $params = array_merge($_GET, $cambios);
    unset($params['accion']);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return '/modules/clientes.php' . ($params ? '?' . http_build_query($params) : '');
}

// ============================================================
// ACCIÓN: LISTAR
// ============================================================
function accionListar(): void
{
    $pdo = pdoActual();

    // 1. Stats
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                                        AS total,
            COALESCE(SUM(password_hash IS NOT NULL), 0)                     AS con_portal,
            COALESCE(SUM(fecha_registro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS nuevos_mes,
            COALESCE(SUM(puntos_lealtad > 0), 0)                           AS con_puntos
        FROM clientes
        WHERE activo = 1
    ")->fetch();

    // 2. Filtros
    $busqueda  = trim($_GET['q'] ?? '');
    $idPais    = (int)($_GET['pais'] ?? 0);
    $filtroEstado = $_GET['estado'] ?? '';
    $filtroPortal = $_GET['portal'] ?? '';

    $ordenMap = ['nombre' => 'apellido, nombre', 'puntos' => 'puntos_lealtad',
                 'fecha' => 'fecha_registro', 'email' => 'email'];
    $orden = $ordenMap[$_GET['orden'] ?? 'nombre'] ?? 'apellido, nombre';
    $dir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    // 3. WHERE dinámico
    $where  = [];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(c.nombre LIKE ? OR c.apellido LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    if ($idPais > 0) {
        $where[] = "c.id_pais = ?";
        $params[] = $idPais;
    }
    switch ($filtroEstado) {
        case 'activo':   $where[] = "c.activo = 1"; break;
        case 'inactivo': $where[] = "c.activo = 0"; break;
    }
    switch ($filtroPortal) {
        case 'con':  $where[] = "c.password_hash IS NOT NULL"; break;
        case 'sin':  $where[] = "c.password_hash IS NULL"; break;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 4. Query principal
    $sql = "
        SELECT c.*, p.nombre AS pais,
               (SELECT COUNT(*) FROM direcciones_cliente dc WHERE dc.id_cliente = c.id_cliente) AS num_direcciones,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_cliente = c.id_cliente) AS num_ventas,
               (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.id_cliente = c.id_cliente AND v.estado='completada') AS total_gastado
        FROM clientes c
        INNER JOIN paises p ON c.id_pais = p.id_pais
        $whereSql
        ORDER BY $orden $dir
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 5. Filtros para el select
    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();
    $hayFiltros = $busqueda !== '' || $idPais > 0 || $filtroEstado !== '' || $filtroPortal !== '';

    $tituloPagina = 'Clientes';
    require __DIR__ . '/../includes/header.php';
    ?>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Clientes</h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= count($rows) ?> cliente<?= count($rows) !== 1 ? 's' : '' ?>
                <?= $hayFiltros ? 'encontrado' . (count($rows) !== 1 ? 's' : '') . ' · <a href="/modules/clientes.php" class="text-primary-600 hover:underline">Limpiar filtros</a>' : 'registrado' . (count($rows) !== 1 ? 's' : '') ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/modules/clientes.php?accion=exportar"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300
                      text-gray-700 rounded-lg hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                CSV
            </a>
            <a href="/modules/clientes.php?accion=crear"
               class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white
                      font-semibold px-4 py-2 rounded-lg transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo cliente
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Total</div>
                <div class="text-2xl font-bold text-gray-900"><?= (int)$stats['total'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-emerald-700 uppercase tracking-wide">Con portal</div>
                <div class="text-2xl font-bold text-emerald-600"><?= (int)$stats['con_portal'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-blue-700 uppercase tracking-wide">Nuevos este mes</div>
                <div class="text-2xl font-bold text-blue-600"><?= (int)$stats['nuevos_mes'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-purple-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-purple-700 uppercase tracking-wide">Con puntos</div>
                <div class="text-2xl font-bold text-purple-600"><?= (int)$stats['con_puntos'] ?></div>
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
                           placeholder="Buscar por nombre, email o teléfono..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                  focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20
                                  outline-none transition">
                </div>
            </div>

            <div class="lg:col-span-3">
                <select name="pais"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todos los países</option>
                    <?php foreach ($paises as $p): ?>
                        <option value="<?= (int)$p['id_pais'] ?>" <?= $idPais === (int)$p['id_pais'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lg:col-span-2">
                <select name="portal"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Portal</option>
                    <option value="con" <?= $filtroPortal === 'con' ? 'selected' : '' ?>>Con acceso</option>
                    <option value="sin" <?= $filtroPortal === 'sin' ? 'selected' : '' ?>>Sin acceso</option>
                </select>
            </div>

            <div class="lg:col-span-3">
                <select name="estado"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todos los estados</option>
                    <option value="activo"   <?= $filtroEstado === 'activo'   ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>

            <div class="lg:hidden">
                <button type="submit"
                        class="w-full bg-primary-700 hover:bg-primary-800 text-white text-sm
                               font-semibold py-2 rounded-lg transition">
                    Aplicar filtros
                </button>
            </div>
        </div>

        <?php if (isset($_GET['orden'])): ?>
            <input type="hidden" name="orden" value="<?= e($_GET['orden']) ?>">
        <?php endif; ?>
        <?php if (isset($_GET['dir'])): ?>
            <input type="hidden" name="dir" value="<?= e($_GET['dir']) ?>">
        <?php endif; ?>
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
                        <?php
                        $sortLink = function (string $key, string $label) use ($orden, $dir): string {
                            $nuevoDir = ($orden === $key && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $flecha = $orden === $key ? ($dir === 'ASC' ? '↑' : '↓') : '';
                            $url = urlListar(['orden' => $key, 'dir' => $nuevoDir]);
                            return '<a href="' . e($url) . '" class="inline-flex items-center gap-1 hover:text-gray-900">'
                                 . $label . ($flecha ? ' <span class="text-primary-600">' . $flecha . '</span>' : '')
                                 . '</a>';
                        };
                        ?>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('nombre', 'Cliente') ?>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('email', 'Contacto') ?>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">País</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Portal</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('puntos', 'Puntos') ?>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Compras</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 mb-3">
                                <?= $hayFiltros ? 'No hay clientes que coincidan con los filtros.' : 'Aún no hay clientes registrados.' ?>
                            </p>
                            <?php if ($hayFiltros): ?>
                                <a href="/modules/clientes.php"
                                   class="inline-block px-4 py-2 text-sm bg-white border border-gray-300
                                          text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    Limpiar filtros
                                </a>
                            <?php else: ?>
                                <a href="/modules/clientes.php?accion=crear"
                                   class="inline-block px-4 py-2 text-sm bg-green-600 hover:bg-green-700
                                          text-white rounded-lg transition">
                                    + Registrar el primer cliente
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $c):
                    $inicial = strtoupper(substr($c['nombre'], 0, 1) . substr($c['apellido'], 0, 1));
                    $tienePortal = $c['password_hash'] !== null;
                ?>
                    <tr class="hover:bg-gray-50 transition <?= (int)$c['activo'] === 0 ? 'opacity-60' : '' ?>">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-600 to-primary-400
                                            text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                                    <?= e($inicial) ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= e($c['nombre'] . ' ' . $c['apellido']) ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        Registrado <?= date('d/m/Y', strtotime($c['fecha_registro'])) ?>
                                        <?php if ((int)$c['activo'] === 0): ?>
                                            · <span class="text-red-500">Inactivo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-700"><?= e($c['email']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($c['telefono'] ?: '—') ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($c['pais']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($tienePortal): ?>
                                <span class="inline-flex items-center gap-1 text-xs bg-emerald-100 text-emerald-700
                                            px-2 py-1 rounded-full">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Activo
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">Sin acceso</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="text-sm font-medium text-purple-600">
                                <?= number_format((int)$c['puntos_lealtad']) ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="text-sm font-medium text-gray-900">
                                $<?= number_format((float)$c['total_gastado'], 2) ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= (int)$c['num_ventas'] ?> venta<?= (int)$c['num_ventas'] !== 1 ? 's' : '' ?>
                                <?php if ((int)$c['num_direcciones'] > 0): ?>
                                    · <?= (int)$c['num_direcciones'] ?> dirección<?= (int)$c['num_direcciones'] !== 1 ? 'es' : '' ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <a href="/modules/clientes.php?accion=ver&id=<?= (int)$c['id_cliente'] ?>"
                                class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                bg-primary-700 hover:bg-primary-800 text-white transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Ver
                            </a>
                            <a href="/modules/clientes.php?accion=editar&id=<?= (int)$c['id_cliente'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                      border border-amber-300 text-amber-700 hover:bg-amber-50 transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Editar
                            </a>
                            <a href="/modules/clientes.php?accion=eliminar&id=<?= (int)$c['id_cliente'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                      border border-red-300 text-red-700 hover:bg-red-50 transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Eliminar
                            </a>
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
// ACCIÓN: CREAR
// ============================================================
function accionCrear(): void
{
    $pdo    = pdoActual();
    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();

    $errores = [];
    $datos = [
        'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => '',
        'id_pais' => '', 'fecha_nacimiento' => '', 'puntos_lealtad' => '0',
        'activo' => '1',
        'password' => '', // Solo en memoria, no se guarda en claro
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($datos as $k => $_) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarCliente($datos, $pdo, null);

        if (empty($errores)) {
            try {
                // Hashear contraseña solo si se proporcionó
                $hash = null;
                if ($datos['password'] !== '') {
                    $hash = password_hash($datos['password'], PASSWORD_DEFAULT);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO clientes
                        (nombre, apellido, email, telefono, id_pais, fecha_nacimiento,
                         puntos_lealtad, password_hash, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['nombre'],
                    $datos['apellido'],
                    $datos['email'],
                    $datos['telefono'] ?: null,
                    (int)$datos['id_pais'],
                    $datos['fecha_nacimiento'] ?: null,
                    (int)$datos['puntos_lealtad'],
                    $hash,
                    (int)$datos['activo'],
                ]);

                flash('exito', 'Cliente creado correctamente.');
                redirect('/modules/clientes.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorCliente($ex);
            }
        }
    }

    $tituloPagina = 'Nuevo cliente';
    $modo = 'crear';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/forms/clientes_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: EDITAR
// ============================================================
function accionEditar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/clientes.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    if (!$cliente) { flash('error', 'Cliente no encontrado.'); redirect('/modules/clientes.php'); }

    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();

    $errores = [];
    $datos = $cliente;
    $datos['password'] = ''; // La contraseña no se precarga

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['nombre','apellido','email','telefono','id_pais','fecha_nacimiento',
                  'puntos_lealtad','activo','password'] as $k) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarCliente($datos, $pdo, $id);

        if (empty($errores)) {
            try {
                // Hashear solo si se proporcionó nueva contraseña
                $hash = $cliente['password_hash'];
                if ($datos['password'] !== '') {
                    $hash = password_hash($datos['password'], PASSWORD_DEFAULT);
                }

                $stmt = $pdo->prepare("
                    UPDATE clientes SET
                        nombre = ?, apellido = ?, email = ?, telefono = ?,
                        id_pais = ?, fecha_nacimiento = ?,
                        puntos_lealtad = ?, password_hash = ?,
                        activo = ?
                    WHERE id_cliente = ?
                ");
                $stmt->execute([
                    $datos['nombre'],
                    $datos['apellido'],
                    $datos['email'],
                    $datos['telefono'] ?: null,
                    (int)$datos['id_pais'],
                    $datos['fecha_nacimiento'] ?: null,
                    (int)$datos['puntos_lealtad'],
                    $hash,
                    (int)$datos['activo'],
                    $id,
                ]);

                flash('exito', 'Cliente actualizado correctamente.');
                redirect('/modules/clientes.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorCliente($ex);
            }
        }
    }

    $tituloPagina = 'Editar cliente';
    $modo = 'editar';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/forms/clientes_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: ELIMINAR
// ============================================================
function accionEliminar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/clientes.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    if (!$cliente) { flash('error', 'Cliente no encontrado.'); redirect('/modules/clientes.php'); }

    // Dependencias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_cliente = ?");
    $stmt->execute([$id]);
    $numVentas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM direcciones_cliente WHERE id_cliente = ?");
    $stmt->execute([$id]);
    $numDirecciones = (int)$stmt->fetchColumn();

    $tieneVentas = $numVentas > 0;

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($tieneVentas) {
                $pdo->prepare("UPDATE clientes SET activo = 0 WHERE id_cliente = ?")->execute([$id]);
                flash('exito', 'El cliente tiene ventas asociadas. Se desactivó para preservar el historial.');
            } else {
                $pdo->prepare("DELETE FROM clientes WHERE id_cliente = ?")->execute([$id]);
                flash('exito', 'Cliente eliminado permanentemente.');
            }
            redirect('/modules/clientes.php');
        } catch (PDOException $ex) {
            $errores[] = mapearErrorCliente($ex);
        }
    }

    $tituloPagina = 'Eliminar cliente';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/clientes.php" class="hover:text-primary-600 transition">Clientes</a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Eliminar</span>
        </nav>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h1 class="text-xl font-bold text-gray-900">
                        <?= $tieneVentas ? 'Desactivar cliente' : 'Eliminar cliente' ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $tieneVentas
                            ? 'Este cliente tiene ventas registradas. Se desactivará pero se conservará su historial.'
                            : 'Esta acción eliminará el cliente y sus direcciones permanentemente.' ?>
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Cliente:</span>
                    <strong class="text-gray-900"><?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?></strong>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Email:</span>
                    <span class="text-gray-700"><?= e($cliente['email']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Ventas registradas:</span>
                    <span class="text-gray-700"><?= $numVentas ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Direcciones de envío:</span>
                    <span class="text-gray-700"><?= $numDirecciones ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Puntos de lealtad:</span>
                    <span class="text-purple-600 font-medium"><?= number_format((int)$cliente['puntos_lealtad']) ?></span>
                </div>
            </div>

            <?php if ($tieneVentas): ?>
                <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm">
                            <strong class="text-amber-800"><?= $numVentas ?> venta<?= $numVentas !== 1 ? 's' : '' ?> asociada<?= $numVentas !== 1 ? 's' : '' ?>.</strong>
                            <p class="text-amber-700 mt-1">El cliente no se eliminará; solo se marcará como inactivo. Sus direcciones de envío se conservarán.</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($numDirecciones > 0): ?>
                <div class="mb-4 p-4 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
                    <strong>Este cliente tiene <?= $numDirecciones ?> dirección<?= $numDirecciones !== 1 ? 'es' : '' ?> registrada<?= $numDirecciones !== 1 ? 's' : '' ?>.</strong>
                    <p class="text-blue-700 mt-1">Al eliminar el cliente, sus direcciones también se eliminarán automáticamente.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($errores as $err): ?>
                <div class="mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/clientes.php"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700
                          hover:bg-gray-50 transition text-center">
                    Cancelar
                </a>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2
                               rounded-lg transition shadow-sm">
                    <?= $tieneVentas ? 'Sí, desactivar' : 'Sí, eliminar' ?>
                </button>
            </form>
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
    $rows = $pdo->query("
        SELECT c.nombre, c.apellido, c.email, c.telefono,
               p.nombre AS pais, c.fecha_nacimiento,
               c.puntos_lealtad,
               CASE WHEN c.password_hash IS NOT NULL THEN 'Sí' ELSE 'No' END AS con_portal,
               c.fecha_registro, c.activo
        FROM clientes c
        INNER JOIN paises p ON c.id_pais = p.id_pais
        ORDER BY c.apellido, c.nombre
    ")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Nombre', 'Apellido', 'Email', 'Teléfono', 'País',
                   'Fecha nacimiento', 'Puntos', 'Portal',
                   'Fecha registro', 'Activo']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['nombre'], $r['apellido'], $r['email'], $r['telefono'],
            $r['pais'], $r['fecha_nacimiento'], $r['puntos_lealtad'],
            $r['con_portal'], $r['email_verificado'] ? 'Sí' : 'No',
            $r['fecha_registro'], $r['activo'] ? 'Sí' : 'No',
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// ACCIÓN: VER (ficha completa del cliente)
// ============================================================
function accionVer(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/clientes.php'); }

    $pdo = pdoActual();

    // Cliente con país
    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre AS pais
        FROM clientes c
        INNER JOIN paises p ON c.id_pais = p.id_pais
        WHERE c.id_cliente = ?
    ");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        flash('error', 'Cliente no encontrado.');
        redirect('/modules/clientes.php');
    }

    // Direcciones activas
    $stmt = $pdo->prepare("
        SELECT d.*, p.nombre AS pais
        FROM direcciones_cliente d
        INNER JOIN paises p ON d.id_pais = p.id_pais
        WHERE d.id_cliente = ? AND d.activa = 1
        ORDER BY d.es_predeterminada DESC, d.fecha_creacion DESC
    ");
    $stmt->execute([$id]);
    $direcciones = $stmt->fetchAll();

    // Resumen de compras
    $stats = $pdo->prepare("
        SELECT
            COUNT(*) AS num_ventas,
            COALESCE(SUM(total), 0) AS total_gastado,
            MAX(fecha_venta) AS ultima_compra
        FROM ventas
        WHERE id_cliente = ? AND estado = 'completada'
    ");
    $stats->execute([$id]);
    $resumen = $stats->fetch();

    // Últimas 5 ventas
    $stmt = $pdo->prepare("
        SELECT id_venta, folio, fecha_venta, total, estado, canal
        FROM ventas
        WHERE id_cliente = ?
        ORDER BY fecha_venta DESC
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $ultimasVentas = $stmt->fetchAll();

    $tituloPagina = 'Cliente: ' . $cliente['nombre'];
    require __DIR__ . '/../includes/header.php';
    ?>

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/clientes.php" class="hover:text-primary-600 transition">Clientes</a>
        <span>›</span>
        <span class="text-gray-900 font-medium">
            <?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
        </span>
    </nav>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div class="flex items-center gap-4">
            <?php $inicial = strtoupper(substr($cliente['nombre'], 0, 1) . substr($cliente['apellido'], 0, 1)); ?>
            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary-600 to-primary-400
                        text-white text-lg font-bold flex items-center justify-center">
                <?= e($inicial) ?>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    <?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <?= e($cliente['email']) ?>
                    <?php if ((int)$cliente['activo'] === 0): ?>
                        · <span class="text-red-500">Inactivo</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/modules/clientes.php?accion=editar&id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-amber-300
                      text-amber-700 rounded-lg hover:bg-amber-50 transition">
                Editar
            </a>
            <a href="/modules/clientes.php?accion=eliminar&id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-red-300
                      text-red-700 rounded-lg hover:bg-red-50 transition">
                Eliminar
            </a>
        </div>
    </div>

    <?php if ($msg = flash('exito')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Columna izquierda: Datos del cliente -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Datos personales
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Email</dt>
                        <dd class="text-gray-900 text-right"><?= e($cliente['email']) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Teléfono</dt>
                        <dd class="text-gray-900"><?= e($cliente['telefono'] ?: '—') ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">País</dt>
                        <dd class="text-gray-900"><?= e($cliente['pais']) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Nacimiento</dt>
                        <dd class="text-gray-900">
                            <?= $cliente['fecha_nacimiento']
                                ? date('d/m/Y', strtotime($cliente['fecha_nacimiento']))
                                : '—' ?>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Registro</dt>
                        <dd class="text-gray-900"><?= date('d/m/Y', strtotime($cliente['fecha_registro'])) ?></dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100">
                        <dt class="text-gray-500">Portal</dt>
                        <dd>
                            <?php if ($cliente['password_hash']): ?>
                                <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                                    Activo
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">Sin acceso</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Puntos</dt>
                        <dd class="text-purple-600 font-semibold">
                            <?= number_format((int)$cliente['puntos_lealtad']) ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Resumen de compras
                </h2>
                <div class="space-y-3">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">
                            $<?= number_format((float)$resumen['total_gastado'], 2) ?>
                        </div>
                        <div class="text-xs text-gray-500">Total gastado</div>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Ventas</span>
                        <span class="text-gray-900 font-medium"><?= (int)$resumen['num_ventas'] ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Última compra</span>
                        <span class="text-gray-900">
                            <?= $resumen['ultima_compra']
                                ? date('d/m/Y', strtotime($resumen['ultima_compra']))
                                : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Direcciones + últimas ventas -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Direcciones -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                            Direcciones de envío
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <?= count($direcciones) ?> registrada<?= count($direcciones) !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="/modules/direcciones.php?id_cliente=<?= $id ?>"
                           class="text-xs px-2 py-1 rounded border border-gray-300
                                  text-gray-700 hover:bg-gray-50 transition">
                            Ver todas
                        </a>
                        <a href="/modules/direcciones.php?accion=crear&id_cliente=<?= $id ?>"
                           class="text-xs px-2 py-1 rounded bg-green-600 text-white
                                  hover:bg-green-700 transition">
                            + Nueva
                        </a>
                    </div>
                </div>

                <?php if (empty($direcciones)): ?>
                    <div class="px-5 py-8 text-center text-sm text-gray-400">
                        Sin direcciones registradas
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach (array_slice($direcciones, 0, 3) as $d): ?>
                            <li class="px-5 py-3 flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600
                                            flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900"><?= e($d['alias']) ?></span>
                                        <?php if ($d['es_predeterminada']): ?>
                                            <span class="text-[10px] font-semibold uppercase
                                                         bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded">
                                                Predeterminada
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate">
                                        <?= e($d['calle']) ?> · <?= e($d['ciudad']) ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($direcciones) > 3): ?>
                            <li class="px-5 py-2 text-center">
                                <a href="/modules/direcciones.php?id_cliente=<?= $id ?>"
                                   class="text-xs text-primary-600 hover:underline">
                                    Ver las <?= count($direcciones) - 3 ?> restantes
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Últimas ventas -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                        Últimas compras
                    </h2>
                </div>

                <?php if (empty($ultimasVentas)): ?>
                    <div class="px-5 py-8 text-center text-sm text-gray-400">
                        Este cliente aún no tiene compras registradas
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($ultimasVentas as $v): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2 text-sm font-mono text-gray-700"><?= e($v['folio']) ?></td>
                                    <td class="px-5 py-2 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($v['fecha_venta'])) ?>
                                    </td>
                                    <td class="px-5 py-2 text-center">
                                        <span class="text-xs px-2 py-0.5 rounded-full
                                                    <?= $v['canal'] === 'online'
                                                        ? 'bg-sky-100 text-sky-700'
                                                        : 'bg-gray-100 text-gray-600' ?>">
                                            <?= e(ucfirst($v['canal'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-2 text-sm text-right font-medium text-gray-900">
                                        $<?= number_format((float)$v['total'], 2) ?>
                                    </td>
                                    <td class="px-5 py-2 text-center">
                                        <span class="text-xs px-2 py-0.5 rounded-full
                                                    <?= match($v['estado']) {
                                                        'completada' => 'bg-green-100 text-green-700',
                                                        'cancelada'  => 'bg-red-100 text-red-700',
                                                        default      => 'bg-amber-100 text-amber-700',
                                                    } ?>">
                                            <?= e(ucfirst($v['estado'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// Helpers
// ============================================================
function validarCliente(array $datos, PDO $pdo, ?int $idActual): array
{
    $errores = [];

    if ($datos['nombre'] === '')   $errores[] = 'El nombre es obligatorio.';
    if ($datos['apellido'] === '') $errores[] = 'El apellido es obligatorio.';

    if ($datos['email'] === '') {
        $errores[] = 'El email es obligatorio.';
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no tiene un formato válido.';
    } else {
        // Verificar duplicado
        $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE email = ? AND id_cliente != ?");
        $stmt->execute([$datos['email'], $idActual ?? 0]);
        if ($stmt->fetch()) {
            $errores[] = 'Ya existe otro cliente con ese email.';
        }
    }

    if ((int)$datos['id_pais'] <= 0) {
        $errores[] = 'Selecciona un país.';
    } else {
        $stmt = $pdo->prepare("SELECT id_pais FROM paises WHERE id_pais = ? AND activo = 1");
        $stmt->execute([(int)$datos['id_pais']]);
        if (!$stmt->fetch()) {
            $errores[] = 'El país seleccionado no existe.';
        }
    }

    if ($datos['fecha_nacimiento'] !== '' && !strtotime($datos['fecha_nacimiento'])) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    } elseif ($datos['fecha_nacimiento'] !== '' && strtotime($datos['fecha_nacimiento']) > time()) {
        $errores[] = 'La fecha de nacimiento no puede ser futura.';
    }

    if ((int)$datos['puntos_lealtad'] < 0) {
        $errores[] = 'Los puntos de lealtad no pueden ser negativos.';
    }

    // Validar contraseña solo si se proporcionó
    if (!empty($datos['password']) && strlen($datos['password']) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    return $errores;
}

function mapearErrorCliente(PDOException $ex): string
{
    $msg = $ex->getMessage();
    if (str_contains($msg, '1142')) return 'Tu rol no tiene permisos para modificar clientes.';
    if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry'))
        return 'Ya existe un cliente con ese email.';
    if (str_contains($msg, '1452') || str_contains($msg, 'foreign key'))
        return 'El país seleccionado no existe.';
    if (str_contains($msg, '1451')) return 'No se puede eliminar: tiene registros relacionados.';
    return 'Error inesperado: ' . $msg;
}