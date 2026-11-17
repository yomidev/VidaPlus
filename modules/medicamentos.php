<?php
// modules/medicamentos.php
// Control de acceso en 3 capas: UI + PHP + MySQL

require_once __DIR__ . '/../includes/auth.php';

$accion = $_GET['accion'] ?? 'listar';

switch ($accion) {
    case 'crear':
    case 'editar':
        requiereRol('admin', 'farmaceutico');
        break;
    case 'eliminar':
        requiereRol('admin');
        break;
    case 'exportar':
        requiereRol('admin', 'farmaceutico');
        break;
    case 'listar':
    default:
        break;
}

match($accion) {
    'crear'    => accionCrear(),
    'editar'   => accionEditar(),
    'eliminar' => accionEliminar(),
    'exportar' => accionExportar(),
    default    => accionListar(),
};

// Helper para URLs con filtros preservados
function urlListar(array $cambios = []): string
{
    $params = array_merge($_GET, $cambios);
    unset($params['accion']);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return '/modules/medicamentos.php' . ($params ? '?' . http_build_query($params) : '');
}

// ============================================================
// ACCIÓN: LISTAR (con stats, búsqueda, filtros, orden)
// ============================================================
function accionListar(): void
{
    $pdo = pdoActual();
    $esCliente = $_SESSION['tipo'] === 'cliente';

    // 1. Stats globales (siempre del catálogo completo, sin filtros)
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                                   AS total,
            COALESCE(SUM(stock < stock_minimo AND stock > 0), 0)       AS bajo,
            COALESCE(SUM(stock = 0), 0)                                AS agotado,
            COUNT(DISTINCT id_categoria)                               AS categorias
        FROM medicamentos
        WHERE activo = 1
    ")->fetch();

    // 2. Filtros y orden desde GET
    $busqueda    = trim($_GET['q'] ?? '');
    $idCategoria = (int)($_GET['categoria'] ?? 0);
    $idLab       = (int)($_GET['laboratorio'] ?? 0);
    $filtroStock = $_GET['stock'] ?? '';

    $ordenMap = ['nombre' => 'nombre', 'precio_venta' => 'precio_venta',
                 'stock' => 'stock', 'categoria' => 'categoria'];
    $orden = $ordenMap[$_GET['orden'] ?? 'nombre'] ?? 'nombre';
    $dir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    // 3. Construir WHERE dinámico
    $where  = [];
    $params = [];

    if (!$esCliente) {
        $where[] = "m.activo = 1";
    }

    if ($busqueda !== '') {
        $where[] = "(m.nombre LIKE ? OR m.codigo_barras LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    if ($idCategoria > 0) {
        $where[] = "m.id_categoria = ?";
        $params[] = $idCategoria;
    }
    if ($idLab > 0 && !$esCliente) {
        $where[] = "m.id_laboratorio = ?";
        $params[] = $idLab;
    }
    switch ($filtroStock) {
        case 'bajo':
            $where[] = "m.stock < m.stock_minimo AND m.stock > 0";
            break;
        case 'agotado':
            $where[] = "m.stock = 0";
            break;
        case 'disponible':
            $where[] = "m.stock >= m.stock_minimo";
            break;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $ordenSql = "ORDER BY $orden $dir";

    // 4. Query principal
    if ($esCliente) {
        // El cliente SOLO ve medicamentos sin receta, activos y con stock.
        // Se incluye id_medicamento para el botón "Agregar al carrito".
        $sql = "SELECT m.id_medicamento, m.nombre, m.presentacion,
                       c.nombre AS categoria, m.precio_venta, m.stock
                FROM medicamentos m
                INNER JOIN categorias c ON m.id_categoria = c.id_categoria
                WHERE m.activo = 1
                  AND m.requiere_receta = 0
                  AND m.stock > 0
                ORDER BY m.nombre";
        $params = []; // El cliente no usa filtros dinámicos de lab/stock
    } else {
        $sql = "SELECT m.id_medicamento, m.codigo_barras, m.nombre, m.presentacion,
                       m.precio_compra, m.precio_venta, m.stock, m.stock_minimo,
                       m.requiere_receta,
                       c.nombre AS categoria, l.nombre AS laboratorio
                FROM medicamentos m
                INNER JOIN categorias   c ON m.id_categoria   = c.id_categoria
                INNER JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
                $whereSql $ordenSql";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 5. Datos para filtros
    $categorias   = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
    $laboratorios = $esCliente ? [] :
        $pdo->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo=1 ORDER BY nombre")->fetchAll();

    $hayFiltros = $busqueda !== '' || $idCategoria > 0 || $idLab > 0 || $filtroStock !== '';

    $tituloPagina = $esCliente ? 'Catálogo en línea' : 'Medicamentos';
    require __DIR__ . '/../includes/header.php';
    ?>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e($tituloPagina) ?></h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= count($rows) ?> producto<?= count($rows) !== 1 ? 's' : '' ?>
                <?= $hayFiltros ? 'encontrado' . (count($rows) !== 1 ? 's' : '') : ($esCliente ? 'disponible' . (count($rows) !== 1 ? 's' : '') : 'activo' . (count($rows) !== 1 ? 's' : '')) ?>
                <?= $hayFiltros ? '· <a href="/modules/medicamentos.php" class="text-primary-600 hover:underline">Limpiar filtros</a>' : '' ?>
            </p>
        </div>
        <div class="flex gap-2">
            <?php if (esRol('admin','farmaceutico')): ?>
                <a href="/modules/medicamentos.php?accion=exportar<?= $hayFiltros ? '&' . http_build_query(array_intersect_key($_GET, array_flip(['q','categoria','laboratorio','stock']))) : '' ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300
                          text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    CSV
                </a>
                <a href="/modules/medicamentos.php?accion=crear"
                   class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white
                          font-semibold px-4 py-2 rounded-lg transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjetas de estadísticas (solo empleados) -->
    <?php if (!$esCliente): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Total</div>
                <div class="text-2xl font-bold text-gray-900"><?= (int)$stats['total'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-amber-700 uppercase tracking-wide">Stock bajo</div>
                <div class="text-2xl font-bold text-amber-600"><?= (int)$stats['bajo'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-red-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-red-700 uppercase tracking-wide">Agotados</div>
                <div class="text-2xl font-bold text-red-600"><?= (int)$stats['agotado'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Categorías</div>
                <div class="text-2xl font-bold text-gray-900"><?= (int)$stats['categorias'] ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra de búsqueda y filtros -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Buscador -->
            <div class="lg:col-span-4">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" name="q" value="<?= e($busqueda) ?>"
                           placeholder="Buscar por nombre o código..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                  focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20
                                  outline-none transition">
                </div>
            </div>

            <!-- Categoría -->
            <div class="lg:col-span-3">
                <select name="categoria"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id_categoria'] ?>"
                            <?= $idCategoria === (int)$c['id_categoria'] ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Laboratorio (solo empleados) -->
            <?php if (!$esCliente): ?>
            <div class="lg:col-span-3">
                <select name="laboratorio"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todos los laboratorios</option>
                    <?php foreach ($laboratorios as $l): ?>
                        <option value="<?= (int)$l['id_laboratorio'] ?>"
                            <?= $idLab === (int)$l['id_laboratorio'] ? 'selected' : '' ?>>
                            <?= e($l['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Stock -->
            <div class="<?= $esCliente ? 'lg:col-span-5' : 'lg:col-span-2' ?>">
                <select name="stock"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Stock</option>
                    <option value="disponible" <?= $filtroStock === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="bajo"       <?= $filtroStock === 'bajo'       ? 'selected' : '' ?>>Bajo</option>
                    <option value="agotado"    <?= $filtroStock === 'agotado'    ? 'selected' : '' ?>>Agotado</option>
                </select>
            </div>

            <!-- Botón aplicar (móvil) -->
            <div class="lg:hidden">
                <button type="submit"
                        class="w-full bg-primary-700 hover:bg-primary-800 text-white text-sm
                               font-semibold py-2 rounded-lg transition">
                    Aplicar filtros
                </button>
            </div>
        </div>

        <!-- Preservar orden actual al filtrar -->
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
                            $flecha = '';
                            if ($orden === $key) {
                                $flecha = $dir === 'ASC' ? '↑' : '↓';
                            }
                            $url = urlListar(['orden' => $key, 'dir' => $nuevoDir]);
                            return '<a href="' . e($url) . '" class="inline-flex items-center gap-1 hover:text-gray-900">'
                                 . $label . ($flecha ? ' <span class="text-primary-600">' . $flecha . '</span>' : '')
                                 . '</a>';
                        };
                        ?>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('nombre', 'Nombre') ?>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Presentación</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('categoria', 'Categoría') ?>
                        </th>
                        <?php if (!$esCliente): ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Laboratorio</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Receta</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('precio_venta', 'Precio') ?>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">
                            <?= $sortLink('stock', 'Stock') ?>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">
                            <?= $esCliente ? 'Comprar' : 'Acciones' ?>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 mb-3">
                                <?= $hayFiltros
                                    ? 'No hay productos que coincidan con los filtros aplicados.'
                                    : 'Aún no hay productos registrados en el catálogo.' ?>
                            </p>
                            <?php if ($hayFiltros): ?>
                                <a href="/modules/medicamentos.php"
                                   class="inline-block px-4 py-2 text-sm bg-white border border-gray-300
                                          text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    Limpiar filtros
                                </a>
                            <?php elseif (esRol('admin','farmaceutico')): ?>
                                <a href="/modules/medicamentos.php?accion=crear"
                                   class="inline-block px-4 py-2 text-sm bg-green-600 hover:bg-green-700
                                          text-white rounded-lg transition">
                                    + Crear el primer medicamento
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $m):
                    // Cálculos protegidos para cliente (no existen precio_compra ni stock_minimo)
                    $margen = (!$esCliente && ($m['precio_compra'] ?? 0) > 0)
                        ? round((($m['precio_venta'] - $m['precio_compra']) / $m['precio_venta']) * 100, 1)
                        : null;
                    $stockPct = (!$esCliente && ($m['stock_minimo'] ?? 0) > 0)
                        ? min(100, round(($m['stock'] / max(1, $m['stock_minimo'] * 3)) * 100))
                        : 0;
                    $stockColor = $m['stock'] == 0 ? 'bg-red-500'
                        : ($m['stock'] < ($m['stock_minimo'] ?? 10) ? 'bg-amber-500' : 'bg-green-500');
                ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($m['nombre']) ?></div>
                            <?php if (!$esCliente): ?>
                                <div class="text-xs text-gray-400 font-mono"><?= e($m['codigo_barras']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($m['presentacion']) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded-full">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                </svg>
                                <?= e($m['categoria']) ?>
                            </span>
                        </td>
                        <?php if (!$esCliente): ?>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= e($m['laboratorio']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($m['requiere_receta']): ?>
                                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-xs font-semibold px-2 py-1 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Sí
                                    </span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-500 text-xs px-2 py-1 rounded-full">No</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-right">
                            <div class="text-sm font-medium text-gray-900">$<?= number_format($m['precio_venta'], 2) ?></div>
                            <?php if ($margen !== null): ?>
                                <div class="text-xs text-green-600">+<?= $margen ?>% margen</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <?php if ($esCliente): ?>
                                <span class="text-sm text-gray-900"><?= (int)$m['stock'] ?></span>
                            <?php else: ?>
                                <div class="text-sm text-gray-900 font-medium">
                                    <?= (int)$m['stock'] ?>
                                    <span class="text-xs text-gray-400">/ <?= (int)$m['stock_minimo'] ?> mín</span>
                                </div>
                                <div class="w-20 h-1.5 bg-gray-200 rounded-full mt-1 ml-auto overflow-hidden">
                                    <div class="h-full <?= $stockColor ?> rounded-full" style="width: <?= $stockPct ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Columna final: Comprar (cliente) / Acciones (empleados) -->
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <?php if ($esCliente): ?>
                                <?php if ((int)$m['stock'] > 0): ?>
                                    <form method="POST" action="/modules/portal/carrito.php?accion=agregar" class="inline">
                                        <input type="hidden" name="id_medicamento" value="<?= (int)$m['id_medicamento'] ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded
                                                       bg-primary-700 hover:bg-primary-800 text-white transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Agregar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">Sin stock</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (esRol('admin','farmaceutico')): ?>
                                    <a href="/modules/medicamentos.php?accion=editar&id=<?= (int)$m['id_medicamento'] ?>"
                                       class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                              border border-amber-300 text-amber-700 hover:bg-amber-50 transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Editar
                                    </a>
                                <?php endif; ?>
                                <?php if (esRol('cajero','farmaceutico','admin')): ?>
                                    <a href="/modules/ventas_nueva.php?medicamento=<?= (int)$m['id_medicamento'] ?>"
                                       class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                              bg-primary-700 hover:bg-primary-800 text-white transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        Vender
                                    </a>
                                <?php endif; ?>
                                <?php if (esRol('admin')): ?>
                                    <a href="/modules/medicamentos.php?accion=eliminar&id=<?= (int)$m['id_medicamento'] ?>"
                                       class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                              border border-red-300 text-red-700 hover:bg-red-50 transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar
                                    </a>
                                <?php endif; ?>
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
// ACCIÓN: CREAR
// ============================================================
function accionCrear(): void
{
    $pdo = pdoActual();
    $categorias   = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
    $laboratorios = $pdo->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo=1 ORDER BY nombre")->fetchAll();

    $errores = [];
    $datos = [
        'codigo_barras' => '', 'nombre' => '', 'descripcion' => '',
        'id_categoria' => '', 'id_laboratorio' => '', 'presentacion' => '',
        'concentracion' => '', 'precio_compra' => '', 'precio_venta' => '',
        'stock' => '0', 'stock_minimo' => '10', 'requiere_receta' => '0',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($datos as $k => $_) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarMedicamento($datos);

        if (empty($errores)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO medicamentos
                        (codigo_barras, nombre, descripcion, id_categoria, id_laboratorio,
                         presentacion, concentracion, precio_compra, precio_venta,
                         stock, stock_minimo, requiere_receta)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['codigo_barras'], $datos['nombre'], $datos['descripcion'] ?: null,
                    (int)$datos['id_categoria'], (int)$datos['id_laboratorio'],
                    $datos['presentacion'], $datos['concentracion'] ?: null,
                    (float)$datos['precio_compra'], (float)$datos['precio_venta'],
                    (int)$datos['stock'], (int)$datos['stock_minimo'],
                    (int)$datos['requiere_receta'],
                ]);
                flash('exito', 'Medicamento creado correctamente.');
                redirect('/modules/medicamentos.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorMySQL($ex);
            }
        }
    }

    $tituloPagina = 'Nuevo medicamento';
    $modo = 'crear';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/medicamentos_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: EDITAR
// ============================================================
function accionEditar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/medicamentos.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$id]);
    $med = $stmt->fetch();
    if (!$med) { flash('error', 'Medicamento no encontrado.'); redirect('/modules/medicamentos.php'); }

    $categorias   = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
    $laboratorios = $pdo->query("SELECT id_laboratorio, nombre FROM laboratorios WHERE activo=1 ORDER BY nombre")->fetchAll();

    $errores = [];
    $datos = $med;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['codigo_barras','nombre','descripcion','id_categoria','id_laboratorio',
                  'presentacion','concentracion','precio_compra','precio_venta',
                  'stock','stock_minimo','requiere_receta'] as $k) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarMedicamento($datos);

        if (empty($errores)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE medicamentos SET
                        codigo_barras = ?, nombre = ?, descripcion = ?,
                        id_categoria = ?, id_laboratorio = ?,
                        presentacion = ?, concentracion = ?,
                        precio_compra = ?, precio_venta = ?,
                        stock = ?, stock_minimo = ?, requiere_receta = ?
                    WHERE id_medicamento = ?
                ");
                $stmt->execute([
                    $datos['codigo_barras'], $datos['nombre'], $datos['descripcion'] ?: null,
                    (int)$datos['id_categoria'], (int)$datos['id_laboratorio'],
                    $datos['presentacion'], $datos['concentracion'] ?: null,
                    (float)$datos['precio_compra'], (float)$datos['precio_venta'],
                    (int)$datos['stock'], (int)$datos['stock_minimo'],
                    (int)$datos['requiere_receta'], $id,
                ]);
                flash('exito', 'Medicamento actualizado correctamente.');
                redirect('/modules/medicamentos.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorMySQL($ex);
            }
        }
    }

    $tituloPagina = 'Editar medicamento';
    $modo = 'editar';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/medicamentos_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: ELIMINAR (con detalles y dependencias)
// ============================================================
function accionEliminar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/medicamentos.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$id]);
    $med = $stmt->fetch();
    if (!$med) { flash('error', 'Medicamento no encontrado.'); redirect('/modules/medicamentos.php'); }

    // Detectar dependencias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM detalle_ventas WHERE id_medicamento = ?");
    $stmt->execute([$id]);
    $numVentas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM detalle_compras WHERE id_medicamento = ?");
    $stmt->execute([$id]);
    $numCompras = (int)$stmt->fetchColumn();

    $tieneVentas = $numVentas > 0;

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($tieneVentas) {
                $pdo->prepare("UPDATE medicamentos SET activo = 0 WHERE id_medicamento = ?")->execute([$id]);
                flash('exito', 'El medicamento tiene ventas asociadas. Se desactivó para preservar el historial.');
            } else {
                $pdo->prepare("DELETE FROM medicamentos WHERE id_medicamento = ?")->execute([$id]);
                flash('exito', 'Medicamento eliminado permanentemente.');
            }
            redirect('/modules/medicamentos.php');
        } catch (PDOException $ex) {
            $errores[] = mapearErrorMySQL($ex);
        }
    }

    $tituloPagina = 'Eliminar medicamento';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/medicamentos.php" class="hover:text-primary-600 transition">Medicamentos</a>
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
                        <?= $tieneVentas ? 'Desactivar medicamento' : 'Eliminar medicamento' ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $tieneVentas
                            ? 'Este medicamento se marcará como inactivo pero se conservará el historial.'
                            : 'Esta acción eliminará el medicamento permanentemente.' ?>
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Nombre:</span>
                    <strong class="text-gray-900"><?= e($med['nombre']) ?></strong>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Código:</span>
                    <span class="text-gray-700 font-mono"><?= e($med['codigo_barras']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Presentación:</span>
                    <span class="text-gray-700"><?= e($med['presentacion']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Precio de venta:</span>
                    <span class="text-gray-700">$<?= number_format($med['precio_venta'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Stock actual:</span>
                    <span class="text-gray-700"><?= (int)$med['stock'] ?> unidades</span>
                </div>
            </div>

            <?php if ($tieneVentas): ?>
                <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm">
                            <strong class="text-amber-800">Este medicamento tiene <?= $numVentas ?> venta<?= $numVentas !== 1 ? 's' : '' ?> registrada<?= $numVentas !== 1 ? 's' : '' ?>.</strong>
                            <p class="text-amber-700 mt-1">No se eliminará de la base de datos: se marcará como inactivo para preservar el historial de ventas.</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($numCompras > 0): ?>
                <div class="mb-4 p-4 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="text-sm text-blue-800">
                        <strong>Este medicamento tiene <?= $numCompras ?> compra<?= $numCompras !== 1 ? 's' : '' ?> registrada<?= $numCompras !== 1 ? 's' : '' ?>.</strong>
                        <p class="text-blue-700 mt-1">Se eliminará del catálogo pero los registros de compra se conservarán con el nombre histórico.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($errores as $err): ?>
                <div class="mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/medicamentos.php"
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
        SELECT m.codigo_barras, m.nombre, c.nombre AS categoria, l.nombre AS laboratorio,
               m.presentacion, m.concentracion, m.precio_compra, m.precio_venta,
               m.stock, m.stock_minimo, m.requiere_receta
        FROM medicamentos m
        INNER JOIN categorias   c ON m.id_categoria   = c.id_categoria
        INNER JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
        WHERE m.activo = 1
        ORDER BY m.nombre
    ")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="medicamentos_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel
    fputcsv($out, ['Código', 'Nombre', 'Categoría', 'Laboratorio', 'Presentación',
                   'Concentración', 'Precio compra', 'Precio venta', 'Stock',
                   'Stock mínimo', 'Requiere receta']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['codigo_barras'], $r['nombre'], $r['categoria'], $r['laboratorio'],
            $r['presentacion'], $r['concentracion'], $r['precio_compra'],
            $r['precio_venta'], $r['stock'], $r['stock_minimo'],
            $r['requiere_receta'] ? 'Sí' : 'No',
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// Helpers compartidos
// ============================================================
function validarMedicamento(array $datos): array
{
    $errores = [];
    if ($datos['codigo_barras'] === '') $errores[] = 'El código de barras es obligatorio.';
    if ($datos['nombre'] === '')        $errores[] = 'El nombre es obligatorio.';
    if ($datos['presentacion'] === '')  $errores[] = 'La presentación es obligatoria.';
    if (!is_numeric($datos['precio_compra']) || (float)$datos['precio_compra'] <= 0)
        $errores[] = 'El precio de compra debe ser mayor a 0.';
    if (!is_numeric($datos['precio_venta']) || (float)$datos['precio_venta'] <= 0)
        $errores[] = 'El precio de venta debe ser mayor a 0.';
    if ((float)$datos['precio_venta'] <= (float)$datos['precio_compra'])
        $errores[] = 'El precio de venta debe ser mayor al precio de compra.';
    if ((int)$datos['stock'] < 0)        $errores[] = 'El stock no puede ser negativo.';
    if ((int)$datos['stock_minimo'] < 0) $errores[] = 'El stock mínimo no puede ser negativo.';
    if ((int)$datos['id_categoria'] <= 0)   $errores[] = 'Selecciona una categoría.';
    if ((int)$datos['id_laboratorio'] <= 0) $errores[] = 'Selecciona un laboratorio.';
    return $errores;
}

function mapearErrorMySQL(PDOException $ex): string
{
    $msg = $ex->getMessage();
    if (str_contains($msg, '1142')) return 'Tu rol no tiene permisos para modificar medicamentos.';
    if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry'))
        return 'Ya existe un medicamento con ese código de barras.';
    if (str_contains($msg, '3819') || str_contains($msg, 'chk_medicamentos'))
        return 'Los datos violan una regla de negocio: el precio de venta debe ser mayor al de compra.';
    if (str_contains($msg, '1452') || str_contains($msg, 'foreign key'))
        return 'La categoría o el laboratorio seleccionado no existe.';
    if (str_contains($msg, '1451')) return 'No se puede eliminar: tiene registros relacionados.';
    return 'Error inesperado: ' . $msg;
}