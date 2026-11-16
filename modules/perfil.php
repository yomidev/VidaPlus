<?php
// modules/perfil.php
// Perfil del usuario logueado (empleado o cliente).
// Solo permite editar datos personales, NO rol ni username.

require_once __DIR__ . '/../includes/auth.php';

$pdo      = pdoActual();
$tipo     = $_SESSION['tipo'];       // 'empleado' | 'cliente'
$idActual = (int)$_SESSION['usuario_id'];

// ============================================================
// Cargar datos según el tipo de sesión
// ============================================================
if ($tipo === 'empleado') {
    $stmt = $pdo->prepare("
        SELECT u.*, r.nombre AS rol_nombre, r.nivel_acceso
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$idActual]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        flash('error', 'Usuario no encontrado.');
        redirect('/modules/auth/logout.php');
    }
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre AS pais
        FROM clientes c
        INNER JOIN paises p ON c.id_pais = p.id_pais
        WHERE c.id_cliente = ?
    ");
    $stmt->execute([$idActual]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        flash('error', 'Cliente no encontrado.');
        redirect('/modules/auth/logout.php');
    }
}

// ============================================================
// Procesar POST
// ============================================================
$errores = [];
$datos = $perfil;
$paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($tipo === 'empleado') {

        // Empleado: solo puede cambiar nombre y email
        $datos['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
        $datos['email']           = trim($_POST['email'] ?? '');

        if ($datos['nombre_completo'] === '') {
            $errores[] = 'El nombre completo es obligatorio.';
        }
        if ($datos['email'] === '') {
            $errores[] = 'El email es obligatorio.';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido.';
        } else {
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
            $stmt->execute([$datos['email'], $idActual]);
            if ($stmt->fetch()) $errores[] = 'Ese email ya está registrado por otro usuario.';
        }

        if (empty($errores)) {
            try {
                $pdo->prepare("
                    UPDATE usuarios SET nombre_completo = ?, email = ? WHERE id_usuario = ?
                ")->execute([$datos['nombre_completo'], $datos['email'], $idActual]);

                $_SESSION['nombre'] = $datos['nombre_completo'];

                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'usuarios', 'UPDATE_PERFIL', ?, ?)
                ")->execute([$idActual, 'El usuario actualizó su propio perfil']);

                flash('exito', 'Perfil actualizado correctamente.');
                redirect('/modules/perfil.php');
            } catch (PDOException $ex) {
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }
        }

    } else {

        // Cliente: puede cambiar nombre, apellido, email, teléfono, país, nacimiento
        foreach (['nombre','apellido','email','telefono','id_pais','fecha_nacimiento'] as $k) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }

        if ($datos['nombre'] === '')   $errores[] = 'El nombre es obligatorio.';
        if ($datos['apellido'] === '') $errores[] = 'El apellido es obligatorio.';

        if ($datos['email'] === '') {
            $errores[] = 'El email es obligatorio.';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido.';
        } else {
            $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE email = ? AND id_cliente != ?");
            $stmt->execute([$datos['email'], $idActual]);
            if ($stmt->fetch()) $errores[] = 'Ese email ya está registrado por otro cliente.';
        }

        if ((int)$datos['id_pais'] <= 0) {
            $errores[] = 'Selecciona un país.';
        }

        if ($datos['fecha_nacimiento'] !== '' && strtotime($datos['fecha_nacimiento']) > time()) {
            $errores[] = 'La fecha de nacimiento no puede ser futura.';
        }

        if (empty($errores)) {
            try {
                $pdo->prepare("
                    UPDATE clientes SET
                        nombre = ?, apellido = ?, email = ?, telefono = ?,
                        id_pais = ?, fecha_nacimiento = ?
                    WHERE id_cliente = ?
                ")->execute([
                    $datos['nombre'],
                    $datos['apellido'],
                    $datos['email'],
                    $datos['telefono'] ?: null,
                    (int)$datos['id_pais'],
                    $datos['fecha_nacimiento'] ?: null,
                    $idActual,
                ]);

                $_SESSION['nombre'] = $datos['nombre'] . ' ' . $datos['apellido'];

                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'clientes', 'UPDATE_PERFIL', ?, ?)
                ")->execute([$idActual, 'El cliente actualizó su propio perfil']);

                flash('exito', 'Perfil actualizado correctamente.');
                redirect('/modules/perfil.php');
            } catch (PDOException $ex) {
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }
        }
    }
}

$tituloPagina = 'Mi perfil';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <span class="text-gray-900 font-medium">Mi perfil</span>
</nav>

<div class="max-w-3xl mx-auto">

    <!-- Encabezado con avatar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex items-center gap-5">
            <?php
            $nombreCompleto = $tipo === 'empleado'
                ? $perfil['nombre_completo']
                : $perfil['nombre'] . ' ' . $perfil['apellido'];
            $inicial = strtoupper(substr($nombreCompleto, 0, 1));
            ?>
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-cyan-400 to-primary-500
                        flex items-center justify-center text-white font-bold text-2xl
                        ring-4 ring-primary-100 flex-shrink-0">
                <?= e($inicial) ?>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 truncate"><?= e($nombreCompleto) ?></h1>
                <p class="text-sm text-gray-500 mt-0.5"><?= e($perfil['email']) ?></p>
                <div class="flex items-center gap-2 mt-2">
                    <?php if ($tipo === 'empleado'): ?>
                        <span class="inline-block text-xs font-semibold uppercase tracking-wider
                                     bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full">
                            <?= e($perfil['rol_nombre']) ?>
                        </span>
                        <span class="text-xs text-gray-400">Nivel <?= (int)$perfil['nivel_acceso'] ?></span>
                    <?php else: ?>
                        <span class="inline-block text-xs font-semibold uppercase tracking-wider
                                     bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full">
                            Cliente
                        </span>
                        <span class="text-xs text-purple-600 font-medium">
                            ⭐ <?= number_format((int)$perfil['puntos_lealtad']) ?> puntos
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($msg = flash('exito')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errores)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
            <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                <?php foreach ($errores as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Datos de solo lectura -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
            Información de la cuenta
        </h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

            <?php if ($tipo === 'empleado'): ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Username</dt>
                    <dd class="mt-0.5 font-mono text-gray-900">@<?= e($perfil['username']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Rol</dt>
                    <dd class="mt-0.5 text-gray-900 capitalize"><?= e($perfil['rol_nombre']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Miembro desde</dt>
                    <dd class="mt-0.5 text-gray-900">
                        <?= date('d/m/Y', strtotime($perfil['fecha_creacion'])) ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Último acceso</dt>
                    <dd class="mt-0.5 text-gray-900">
                        <?= $perfil['ultimo_login']
                            ? date('d/m/Y H:i', strtotime($perfil['ultimo_login']))
                            : 'Primera sesión' ?>
                    </dd>
                </div>
            <?php else: ?>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Cliente desde</dt>
                    <dd class="mt-0.5 text-gray-900">
                        <?= date('d/m/Y', strtotime($perfil['fecha_registro'])) ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">Portal</dt>
                    <dd class="mt-0.5">
                        <?php if ($perfil['password_hash']): ?>
                            <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">
                                Activo
                            </span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Sin acceso</span>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Formulario de edición -->
    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
            Datos personales
        </h2>

        <?php if ($tipo === 'empleado'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre_completo" required
                           value="<?= e($datos['nombre_completo']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                           value="<?= e($datos['email']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre" required
                           value="<?= e($datos['nombre']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Apellido <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="apellido" required
                           value="<?= e($datos['apellido']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                           value="<?= e($datos['email']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">También sirve como login del portal</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="tel" name="telefono"
                           value="<?= e($datos['telefono'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        País <span class="text-red-500">*</span>
                    </label>
                    <select name="id_pais" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= (int)$p['id_pais'] ?>"
                                <?= (int)$datos['id_pais'] === (int)$p['id_pais'] ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= e($datos['fecha_nacimiento'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-between gap-2 pt-5 border-t border-gray-100">
            <a href="/modules/cambiar_password.php"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg
                      border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Cambiar contraseña
            </a>
            <button type="submit"
                    class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                Guardar cambios
            </button>
        </div>
    </form>

    <!-- Nota informativa -->
    <div class="bg-gray-100 border border-gray-200 rounded-lg p-4 text-sm text-gray-600">
        <strong>Nota:</strong> Los datos sensibles como tu <strong>username</strong>,
        <strong>rol</strong> y <strong>puntos de lealtad</strong> solo pueden ser modificados
        por un administrador.
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>