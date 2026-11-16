<?php
session_start();
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

if (estaLogueado()) {
    redirect('/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $password      = $_POST['password'] ?? '';

    if ($identificador === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        try {
            $pdo = getPDO('login');

            // 1) Empleado
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.password_hash, u.nombre_completo,
                       r.nombre AS rol_nombre, r.nivel_acceso
                FROM usuarios u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.username = ? AND u.activo = 1 AND r.activo = 1
            ");
            $stmt->execute([$identificador]);
            $empleado = $stmt->fetch();

            if ($empleado && password_verify($password, $empleado['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id']   = (int)$empleado['id_usuario'];
                $_SESSION['tipo']         = 'empleado';
                $_SESSION['nombre']       = $empleado['nombre_completo'];
                $_SESSION['rol_nombre']   = $empleado['rol_nombre'];
                $_SESSION['nivel_acceso'] = (int)$empleado['nivel_acceso'];

                $upd = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?");
                $upd->execute([$empleado['id_usuario']]);

                redirect('/index.php');
            }

            // 2) Cliente
            $stmt = $pdo->prepare("
                SELECT id_cliente, password_hash, nombre, apellido
                FROM clientes
                WHERE email = ? AND activo = 1 AND password_hash IS NOT NULL
            ");
            $stmt->execute([$identificador]);
            $cliente = $stmt->fetch();

            if ($cliente && password_verify($password, $cliente['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id']   = (int)$cliente['id_cliente'];
                $_SESSION['tipo']         = 'cliente';
                $_SESSION['nombre']       = $cliente['nombre'] . ' ' . $cliente['apellido'];
                $_SESSION['rol_nombre']   = 'cliente';
                $_SESSION['nivel_acceso'] = 1;
                redirect('/index.php');
            }

            $error = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $ex) {
            $error = 'Error de conexión. Contacta al administrador.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — VidaPlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: {
                500:'#2e6ba8', 600:'#1f568f', 700:'#102b46'
            }}}}
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-700 via-primary-600 to-primary-500
             flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="text-5xl mb-2">💊</div>
            <h1 class="text-2xl font-bold text-primary-700">VidaPlus</h1>
            <p class="text-sm text-gray-500">Farmacia en línea</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario o email</label>
                <input type="text" name="identificador"
                       value="<?= e($_POST['identificador'] ?? '') ?>"
                       required autofocus
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <input type="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg
                              focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
            </div>
            <button class="w-full bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           py-2.5 rounded-lg transition shadow">
                Entrar
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-500 text-center">
            Empleados: usa tu <strong>usuario</strong> · Clientes: usa tu <strong>email</strong>
        </div>
    </div>

</body>
</html>