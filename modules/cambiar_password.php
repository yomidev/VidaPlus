<?php
// modules/cambiar_password.php
// Cambio de contraseña del usuario logueado (empleado o cliente).

require_once __DIR__ . '/../includes/auth.php';

$pdo      = pdoActual();
$tipo     = $_SESSION['tipo'];
$idActual = (int)$_SESSION['usuario_id'];

// ============================================================
// Cargar el hash actual
// ============================================================
if ($tipo === 'empleado') {
    $stmt = $pdo->prepare("SELECT username, nombre_completo, password_hash FROM usuarios WHERE id_usuario = ?");
} else {
    $stmt = $pdo->prepare("SELECT nombre, apellido, password_hash FROM clientes WHERE id_cliente = ?");
}
$stmt->execute([$idActual]);
$cuenta = $stmt->fetch();

if (!$cuenta) {
    flash('error', 'Cuenta no encontrada.');
    redirect('/modules/auth/logout.php');
}

// Si el cliente nunca asignó contraseña, no puede "cambiar" una que no existe
if ($tipo === 'cliente' && empty($cuenta['password_hash'])) {
    flash('error', 'Tu cuenta aún no tiene contraseña asignada. Contacta al administrador.');
    redirect('/modules/perfil.php');
}

// ============================================================
// Procesar POST
// ============================================================
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNueva  = $_POST['password_nueva'] ?? '';
    $passwordConf   = $_POST['password_confirm'] ?? '';

    // Validaciones
    if ($passwordActual === '') {
        $errores[] = 'Ingresa tu contraseña actual.';
    } elseif (!password_verify($passwordActual, $cuenta['password_hash'])) {
        $errores[] = 'La contraseña actual es incorrecta.';
    }

    if ($passwordNueva === '') {
        $errores[] = 'Ingresa la nueva contraseña.';
    } elseif (strlen($passwordNueva) < 6) {
        $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($passwordNueva === $passwordActual) {
        $errores[] = 'La nueva contraseña debe ser diferente a la actual.';
    }

    if ($passwordNueva !== $passwordConf) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errores)) {
        try {
            $nuevoHash = password_hash($passwordNueva, PASSWORD_DEFAULT);

            if ($tipo === 'empleado') {
                $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")
                    ->execute([$nuevoHash, $idActual]);

                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'usuarios', 'PASSWORD_CHANGE', ?, ?)
                ")->execute([$idActual, "El usuario @{$cuenta['username']} cambió su contraseña"]);

            } else {
                $pdo->prepare("UPDATE clientes SET password_hash = ? WHERE id_cliente = ?")
                    ->execute([$nuevoHash, $idActual]);

                $pdo->prepare("
                    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
                    VALUES (USER(), 'clientes', 'PASSWORD_CHANGE', ?, ?)
                ")->execute([$idActual, "El cliente {$cuenta['nombre']} {$cuenta['apellido']} cambió su contraseña"]);
            }

            // Cerrar la sesión actual por seguridad (obliga a re-loguearse)
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash']['exito'] = 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.';
            redirect('/modules/auth/login.php');

        } catch (PDOException $ex) {
            $errores[] = 'Error al actualizar la contraseña: ' . $ex->getMessage();
        }
    }
}

$tituloPagina = 'Cambiar contraseña';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/perfil.php" class="hover:text-primary-600 transition">Mi perfil</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Cambiar contraseña</span>
</nav>

<div class="max-w-2xl mx-auto">

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">Cambiar contraseña</h1>
        <p class="text-sm text-gray-500 mt-1">
            Por seguridad, se cerrará tu sesión después del cambio.
        </p>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    <?php foreach ($errores as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">

        <!-- Contraseña actual -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Contraseña actual <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" name="password_actual" required autofocus
                       id="inp_actual"
                       class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                <button type="button" onclick="toggleVer('inp_actual', this)"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <hr class="border-gray-100">

        <!-- Nueva contraseña -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Nueva contraseña <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" name="password_nueva" required minlength="6"
                       id="inp_nueva"
                       placeholder="Mínimo 6 caracteres"
                       class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                <button type="button" onclick="toggleVer('inp_nueva', this)"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Debe ser diferente a la actual y tener al menos 6 caracteres.
            </p>
        </div>

        <!-- Confirmar nueva contraseña -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Confirmar nueva contraseña <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" name="password_confirm" required minlength="6"
                       id="inp_confirm"
                       placeholder="Repite la nueva contraseña"
                       class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                <button type="button" onclick="toggleVer('inp_confirm', this)"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Indicador de coincidencia en vivo -->
        <div id="match_ok" class="hidden text-xs text-emerald-600 flex items-center gap-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            Las contraseñas coinciden
        </div>
        <div id="match_err" class="hidden text-xs text-red-600">
            Las contraseñas no coinciden
        </div>

        <!-- Nota de seguridad -->
        <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800 flex items-start gap-2">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>Al cambiar tu contraseña, tu sesión actual se cerrará automáticamente por seguridad.</span>
        </div>

        <!-- Acciones -->
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-5 border-t border-gray-100">
            <a href="/modules/perfil.php"
               class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
                Cancelar
            </a>
            <button type="submit"
                    class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                Cambiar contraseña
            </button>
        </div>
    </form>
</div>

<script>
// Toggle de ver/ocultar contraseña
function toggleVer(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Validación en vivo: coincidencia de contraseñas
const inpNueva   = document.getElementById('inp_nueva');
const inpConfirm = document.getElementById('inp_confirm');
const matchOk    = document.getElementById('match_ok');
const matchErr   = document.getElementById('match_err');

function checkMatch() {
    if (inpConfirm.value === '') {
        matchOk.classList.add('hidden');
        matchErr.classList.add('hidden');
        return;
    }
    if (inpNueva.value === inpConfirm.value) {
        matchOk.classList.remove('hidden');
        matchErr.classList.add('hidden');
        inpConfirm.classList.remove('border-red-500');
        inpConfirm.classList.add('border-emerald-500');
    } else {
        matchOk.classList.add('hidden');
        matchErr.classList.remove('hidden');
        inpConfirm.classList.add('border-red-500');
        inpConfirm.classList.remove('border-emerald-500');
    }
}

inpNueva.addEventListener('input', checkMatch);
inpConfirm.addEventListener('input', checkMatch);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>