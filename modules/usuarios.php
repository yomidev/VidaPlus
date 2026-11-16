<?php
// modules/usuarios.php
// Solo admin. CRUD de empleados + asignación de roles.

require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin');

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'crear'    => accionCrear(),
    'editar'   => accionEditar(),
    'eliminar' => accionEliminar(),
    'exportar' => accionExportar(),
    default    => accionListar(),
};

// ============================================================
// ACCIÓN: LISTAR
// ============================================================
function accionListar(): void
{
    $pdo = pdoActual();

    // Stats
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                                AS total,
            COALESCE(SUM(u.activo = 1), 0)                          AS activos,
            COALESCE(SUM(u.activo = 0), 0)                          AS inactivos,
            COALESCE(SUM(DATE(u.fecha_creacion) = CURDATE()), 0)    AS creados_hoy
        FROM usuarios u
    ")->fetch();

    // Filtros
    $busqueda = trim($_GET['q'] ?? '');
    $rolF     = (int)($_GET['rol'] ?? 0);
    $estadoF  = $_GET['estado'] ?? '';

    $ordenMap = ['nombre' => 'u.nombre_completo', 'username' => 'u.username',
                 'rol' => 'r.nombre', 'login' => 'u.ultimo_login'];
    $orden = $ordenMap[$_GET['orden'] ?? 'nombre'] ?? 'u.nombre_completo';
    $dir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    $where  = [];
    $params = [];

    if ($busqueda !== '') {
        $where[] = "(u.username LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    if ($rolF > 0) {
        $where[] = "u.id_rol = ?";
        $params[] = $rolF;
    }
    if ($estadoF === 'activo')   $where[] = "u.activo = 1";
    if ($estadoF === 'inactivo') $where[] = "u.activo = 0";

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT u.*, r.nombre AS rol_nombre, r.nivel_acceso,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_usuario = u.id_usuario) AS num_ventas
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        $whereSql
        ORDER BY $orden $dir
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $roles = $pdo->query("SELECT id_rol, nombre, nivel_acceso FROM roles WHERE activo=1 ORDER BY nivel_acceso DESC")->fetchAll();
    $hayFiltros = $busqueda !== '' || $rolF > 0 || $estadoF !== '';

    $tituloPagina = 'Usuarios';
    require __DIR__ . '/../includes/header.php';
    ?>

    <!-- Encabezado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Usuarios</h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= count($rows) ?> usuario<?= count($rows) !== 1 ? 's' : '' ?>
                <?= $hayFiltros ? 'encontrado' . (count($rows) !== 1 ? 's' : '') . ' · <a href="/modules/usuarios.php" class="text-primary-600 hover:underline">Limpiar filtros</a>' : 'registrado' . (count($rows) !== 1 ? 's' : '') ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/modules/usuarios.php?accion=exportar"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300
                      text-gray-700 rounded-lg hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                CSV
            </a>
            <a href="/modules/usuarios.php?accion=crear"
               class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white
                      font-semibold px-4 py-2 rounded-lg transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo usuario
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
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-emerald-700 uppercase tracking-wide">Activos</div>
                <div class="text-2xl font-bold text-emerald-600"><?= (int)$stats['activos'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-300 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-gray-600 uppercase tracking-wide">Inactivos</div>
                <div class="text-2xl font-bold text-gray-700"><?= (int)$stats['inactivos'] ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs text-blue-700 uppercase tracking-wide">Creados hoy</div>
                <div class="text-2xl font-bold text-blue-600"><?= (int)$stats['creados_hoy'] ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-6">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" name="q" value="<?= e($busqueda) ?>"
                           placeholder="Buscar por username, nombre o email..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                  focus:bg-white focus:border-primary-500 outline-none transition">
                </div>
            </div>
            <div class="lg:col-span-3">
                <select name="rol"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id_rol'] ?>" <?= $rolF === (int)$r['id_rol'] ? 'selected' : '' ?>>
                            <?= e($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-3">
                <select name="estado"
                        class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg
                               focus:bg-white focus:border-primary-500 outline-none transition">
                    <option value="">Todos los estados</option>
                    <option value="activo"   <?= $estadoF === 'activo'   ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $estadoF === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
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
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Rol</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Último acceso</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center">
                            <p class="text-gray-500 mb-3">
                                <?= $hayFiltros ? 'No hay usuarios que coincidan con los filtros.' : 'Aún no hay usuarios registrados.' ?>
                            </p>
                            <?php if ($hayFiltros): ?>
                                <a href="/modules/usuarios.php" class="inline-block px-4 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    Limpiar filtros
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $u):
                    $esUsuarioActual = ((int)$u['id_usuario'] === (int)$_SESSION['usuario_id']);
                    $inicial = strtoupper(substr($u['nombre_completo'], 0, 1));

                    $badgeRol = match($u['rol_nombre']) {
                        'admin'        => 'bg-rose-100 text-rose-700',
                        'farmaceutico' => 'bg-emerald-100 text-emerald-700',
                        'cajero'       => 'bg-amber-100 text-amber-700',
                        'auditor'      => 'bg-slate-200 text-slate-700',
                        default        => 'bg-gray-100 text-gray-700',
                    };
                ?>
                    <tr class="hover:bg-gray-50 transition <?= (int)$u['activo'] === 0 ? 'opacity-60' : '' ?>">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-600 to-primary-400
                                            text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                                    <?= e($inicial) ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                        <?= e($u['nombre_completo']) ?>
                                        <?php if ($esUsuarioActual): ?>
                                            <span class="text-[10px] font-semibold uppercase tracking-wider
                                                         bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded">
                                                Tú
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-400 font-mono">@<?= e($u['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($u['email']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-semibold px-2 py-1 rounded-full <?= $badgeRol ?>">
                                <?= e($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ((int)$u['activo'] === 1): ?>
                                <span class="inline-flex items-center gap-1 text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Activo
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Inactivo
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?php if ($u['ultimo_login']): ?>
                                <?= date('d/m/Y H:i', strtotime($u['ultimo_login'])) ?>
                            <?php else: ?>
                                <span class="text-gray-400">Nunca</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <a href="/modules/usuarios.php?accion=editar&id=<?= (int)$u['id_usuario'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                      border border-amber-300 text-amber-700 hover:bg-amber-50 transition">
                                Editar
                            </a>
                            <?php if (!$esUsuarioActual): ?>
                                <a href="/modules/usuarios.php?accion=eliminar&id=<?= (int)$u['id_usuario'] ?>"
                                   class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                          border border-red-300 text-red-700 hover:bg-red-50 transition">
                                    <?= (int)$u['activo'] === 1 ? 'Eliminar' : 'Reactivar' ?>
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
// ACCIÓN: CREAR
// ============================================================
function accionCrear(): void
{
    $pdo = pdoActual();
    $roles = $pdo->query("SELECT id_rol, nombre, nivel_acceso FROM roles WHERE activo=1 ORDER BY nivel_acceso DESC")->fetchAll();

    $errores = [];
    $datos = [
        'username'        => '', 'nombre_completo' => '', 'email' => '',
        'id_rol'          => '', 'password' => '', 'password_confirm' => '',
        'activo'          => '1',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($datos as $k => $_) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarUsuario($datos, $pdo, null, true);

        if (empty($errores)) {
            try {
                $hash = password_hash($datos['password'], PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO usuarios
                        (username, password_hash, nombre_completo, email, id_rol, activo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['username'],
                    $hash,
                    $datos['nombre_completo'],
                    $datos['email'],
                    (int)$datos['id_rol'],
                    (int)$datos['activo'],
                ]);

                // Auditoría
                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'usuarios', 'INSERT', ?, ?)
                ")->execute([
                    $pdo->lastInsertId(),
                    "Nuevo usuario: {$datos['username']} ({$datos['nombre_completo']})"
                ]);

                flash('exito', 'Usuario creado correctamente.');
                redirect('/modules/usuarios.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorUsuario($ex);
            }
        }
    }

    $tituloPagina = 'Nuevo usuario';
    $modo = 'crear';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/forms/usuarios_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: EDITAR
// ============================================================
function accionEditar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/usuarios.php'); }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if (!$usuario) { flash('error', 'Usuario no encontrado.'); redirect('/modules/usuarios.php'); }

    $esUsuarioActual = ($id === (int)$_SESSION['usuario_id']);
    $roles = $pdo->query("SELECT id_rol, nombre, nivel_acceso FROM roles WHERE activo=1 ORDER BY nivel_acceso DESC")->fetchAll();

    $errores = [];
    $datos = $usuario;
    $datos['password'] = '';
    $datos['password_confirm'] = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['username','nombre_completo','email','id_rol','password','password_confirm','activo'] as $k) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }

        // Validaciones especiales
        if ($esUsuarioActual) {
            // No puede cambiar su propio rol
            if ((int)$datos['id_rol'] !== (int)$usuario['id_rol']) {
                $errores[] = 'No puedes cambiar tu propio rol.';
            }
            // No puede desactivarse a sí mismo
            if ((int)$datos['activo'] !== 1) {
                $errores[] = 'No puedes desactivar tu propia cuenta.';
            }
        }

        // No puede eliminar el último admin activo
        if (!$esUsuarioActual) {
            if ($usuario['id_rol'] == 1 && $usuario['activo'] == 1 && (int)$datos['id_rol'] != 1) {
                // Está cambiando al admin a otro rol
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND activo = 1 AND id_usuario != ?");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errores[] = 'No puedes cambiar el rol del último administrador activo.';
                }
            }
            if ($usuario['id_rol'] == 1 && (int)$datos['activo'] === 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND activo = 1 AND id_usuario != ?");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errores[] = 'No puedes desactivar al último administrador activo.';
                }
            }
        }

        $errores = array_merge($errores, validarUsuario($datos, $pdo, $id, false));

        if (empty($errores)) {
            try {
                // Hash solo si se cambió la contraseña
                $hash = $usuario['password_hash'];
                if ($datos['password'] !== '') {
                    $hash = password_hash($datos['password'], PASSWORD_DEFAULT);
                }

                $stmt = $pdo->prepare("
                    UPDATE usuarios SET
                        username = ?, nombre_completo = ?, email = ?,
                        id_rol = ?, password_hash = ?, activo = ?
                    WHERE id_usuario = ?
                ");
                $stmt->execute([
                    $datos['username'],
                    $datos['nombre_completo'],
                    $datos['email'],
                    (int)$datos['id_rol'],
                    $hash,
                    (int)$datos['activo'],
                    $id,
                ]);

                // Auditoría
                $cambios = [];
                if ($usuario['id_rol'] != $datos['id_rol']) {
                    $cambios[] = "rol cambiado";
                }
                if ($usuario['activo'] != $datos['activo']) {
                    $cambios[] = (int)$datos['activo'] === 1 ? "activado" : "desactivado";
                }
                if ($datos['password'] !== '') {
                    $cambios[] = "contraseña cambiada";
                }
                $detalle = "Usuario @{$datos['username']} actualizado"
                         . ($cambios ? ' (' . implode(', ', $cambios) . ')' : '');

                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'usuarios', 'UPDATE', ?, ?)
                ")->execute([$id, $detalle]);

                flash('exito', 'Usuario actualizado correctamente.');
                redirect('/modules/usuarios.php');
            } catch (PDOException $ex) {
                $errores[] = mapearErrorUsuario($ex);
            }
        }
    }

    $tituloPagina = 'Editar usuario';
    $modo = 'editar';
    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/forms/usuarios_form.php';
    require __DIR__ . '/../includes/footer.php';
}

// ============================================================
// ACCIÓN: ELIMINAR / REACTIVAR
// ============================================================
function accionEliminar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { flash('error', 'ID inválido.'); redirect('/modules/usuarios.php'); }

    // No puede eliminarse a sí mismo
    if ($id === (int)$_SESSION['usuario_id']) {
        flash('error', 'No puedes eliminar tu propia cuenta.');
        redirect('/modules/usuarios.php');
    }

    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol WHERE id_usuario = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if (!$usuario) { flash('error', 'Usuario no encontrado.'); redirect('/modules/usuarios.php'); }

    // Si está inactivo → la acción es REACTIVAR
    if ((int)$usuario['activo'] === 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id_usuario = ?")->execute([$id]);
                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'usuarios', 'REACTIVAR', ?, ?)
                ")->execute([$id, "Usuario @{$usuario['username']} reactivado"]);
                flash('exito', 'Usuario reactivado correctamente.');
            } catch (PDOException $ex) {
                flash('error', 'Error al reactivar: ' . $ex->getMessage());
            }
            redirect('/modules/usuarios.php');
        }

        $tituloPagina = 'Reactivar usuario';
        require __DIR__ . '/../includes/header.php';
        ?>
        <div class="max-w-2xl mx-auto">
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                <a href="/modules/usuarios.php" class="hover:text-primary-600 transition">Usuarios</a>
                <span>›</span>
                <span class="text-gray-900 font-medium">Reactivar</span>
            </nav>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Reactivar usuario</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            El usuario podrá volver a iniciar sesión con su contraseña actual.
                        </p>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Usuario:</span>
                        <span class="font-mono text-gray-900">@<?= e($usuario['username']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Nombre:</span>
                        <strong class="text-gray-900"><?= e($usuario['nombre_completo']) ?></strong>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Rol:</span>
                        <span class="text-gray-700"><?= e($usuario['rol_nombre']) ?></span>
                    </div>
                </div>

                <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <a href="/modules/usuarios.php"
                       class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700
                              hover:bg-gray-50 transition text-center">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2
                                   rounded-lg transition shadow-sm">
                        Sí, reactivar usuario
                    </button>
                </form>
            </div>
        </div>
        <?php
        require __DIR__ . '/../includes/footer.php';
        return;
    }

    // Si está activo → la acción es DESACTIVAR / ELIMINAR
    // Verificar dependencias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_usuario = ?");
    $stmt->execute([$id]);
    $numVentas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM compras WHERE id_usuario = ?");
    $stmt->execute([$id]);
    $numCompras = (int)$stmt->fetchColumn();

    $tieneDependencias = ($numVentas + $numCompras) > 0;

    // ¿Es el último admin activo?
    $esUltimoAdmin = false;
    if ($usuario['id_rol'] == 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND activo = 1 AND id_usuario != ?");
        $stmt->execute([$id]);
        $esUltimoAdmin = ((int)$stmt->fetchColumn() === 0);
    }

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($esUltimoAdmin) {
            $errores[] = 'No puedes eliminar al último administrador activo.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($tieneDependencias) {
                    // Baja lógica
                    $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ?")->execute([$id]);
                    $pdo->prepare("
                        INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                        VALUES (USER(), 'usuarios', 'DESACTIVAR', ?, ?)
                    ")->execute([$id, "Usuario @{$usuario['username']} desactivado (tiene movimientos asociados)"]);
                    flash('exito', 'El usuario tiene ventas/compras asociadas. Se desactivó en lugar de eliminarse.');
                } else {
                    // DELETE físico
                    $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$id]);
                    $pdo->prepare("
                        INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                        VALUES (USER(), 'usuarios', 'DELETE', ?, ?)
                    ")->execute([$id, "Usuario @{$usuario['username']} eliminado permanentemente"]);
                    flash('exito', 'Usuario eliminado permanentemente.');
                }

                $pdo->commit();
                redirect('/modules/usuarios.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errores[] = mapearErrorUsuario($ex);
            }
        }
    }

    $tituloPagina = 'Eliminar usuario';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/usuarios.php" class="hover:text-primary-600">Usuarios</a>
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
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        <?= $tieneDependencias ? 'Desactivar usuario' : 'Eliminar usuario' ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $tieneDependencias
                            ? 'Este usuario tiene ventas o compras registradas. Se desactivará pero se conservará su historial.'
                            : 'Esta acción eliminará el usuario permanentemente.' ?>
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Usuario:</span>
                    <span class="font-mono text-gray-900">@<?= e($usuario['username']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Nombre:</span>
                    <strong class="text-gray-900"><?= e($usuario['nombre_completo']) ?></strong>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Rol:</span>
                    <span class="text-gray-700"><?= e($usuario['rol_nombre']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Ventas registradas:</span>
                    <span class="text-gray-700"><?= $numVentas ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Compras registradas:</span>
                    <span class="text-gray-700"><?= $numCompras ?></span>
                </div>
            </div>

            <?php if ($esUltimoAdmin): ?>
                <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
                    <strong>⚠️ Este es el último administrador activo.</strong>
                    <p class="mt-1">No puedes eliminarlo ni desactivarlo. Crea otro admin primero.</p>
                </div>
            <?php elseif ($tieneDependencias): ?>
                <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                    <strong>Este usuario tiene movimientos registrados.</strong>
                    <p class="mt-1">Se desactivará pero sus ventas y compras se conservarán en el historial.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($errores as $err): ?>
                <div class="mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/usuarios.php"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700
                          hover:bg-gray-50 transition text-center">
                    Cancelar
                </a>
                <button type="submit"
                        <?= $esUltimoAdmin ? 'disabled' : '' ?>
                        class="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed
                               text-white font-semibold px-4 py-2 rounded-lg transition shadow-sm">
                    <?= $tieneDependencias ? 'Sí, desactivar' : 'Sí, eliminar' ?>
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
        SELECT u.username, u.nombre_completo, u.email, r.nombre AS rol,
               CASE u.activo WHEN 1 THEN 'Sí' ELSE 'No' END AS activo,
               u.ultimo_login, u.fecha_creacion
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        ORDER BY u.nombre_completo
    ")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Username', 'Nombre completo', 'Email', 'Rol',
                   'Activo', 'Último login', 'Fecha creación']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['username'], $r['nombre_completo'], $r['email'],
            $r['rol'], $r['activo'], $r['ultimo_login'], $r['fecha_creacion'],
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// Helpers
// ============================================================
function validarUsuario(array $datos, PDO $pdo, ?int $idActual, bool $esCreacion): array
{
    $errores = [];

    // Username
    if ($datos['username'] === '') {
        $errores[] = 'El username es obligatorio.';
    } elseif (!preg_match('/^[a-z0-9_.]{3,50}$/i', $datos['username'])) {
        $errores[] = 'El username solo puede tener letras, números, punto y guion bajo (3-50 caracteres).';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = ? AND id_usuario != ?");
        $stmt->execute([$datos['username'], $idActual ?? 0]);
        if ($stmt->fetch()) $errores[] = 'Ya existe un usuario con ese username.';
    }

    // Nombre completo
    if ($datos['nombre_completo'] === '') {
        $errores[] = 'El nombre completo es obligatorio.';
    }

    // Email
    if ($datos['email'] === '') {
        $errores[] = 'El email es obligatorio.';
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no tiene un formato válido.';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$datos['email'], $idActual ?? 0]);
        if ($stmt->fetch()) $errores[] = 'Ya existe un usuario con ese email.';
    }

    // Rol
    if ((int)$datos['id_rol'] <= 0) {
        $errores[] = 'Selecciona un rol.';
    } else {
        $stmt = $pdo->prepare("SELECT id_rol FROM roles WHERE id_rol = ? AND activo = 1");
        $stmt->execute([(int)$datos['id_rol']]);
        if (!$stmt->fetch()) $errores[] = 'El rol seleccionado no existe o está inactivo.';
    }

    // Contraseña
    if ($esCreacion) {
        if ($datos['password'] === '') {
            $errores[] = 'La contraseña es obligatoria para nuevos usuarios.';
        } elseif (strlen($datos['password']) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($datos['password'] !== $datos['password_confirm']) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
    } else {
        // En edición, si se proporciona, validar
        if ($datos['password'] !== '') {
            if (strlen($datos['password']) < 6) {
                $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } elseif ($datos['password'] !== $datos['password_confirm']) {
                $errores[] = 'Las contraseñas no coinciden.';
            }
        }
    }

    return $errores;
}

function mapearErrorUsuario(PDOException $ex): string
{
    $msg = $ex->getMessage();
    if (str_contains($msg, '1142')) return 'Tu rol no tiene permisos para modificar usuarios.';
    if (str_contains($msg, '1062')) return 'Ya existe un usuario con ese username o email.';
    if (str_contains($msg, '1451')) return 'No se puede eliminar: tiene registros relacionados.';
    return 'Error inesperado: ' . $msg;
}