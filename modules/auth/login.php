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
            theme: {
                extend: {
                    colors: {
                        primary: {
                            400: '#4a8bc9',
                            500: '#2e6ba8',
                            600: '#1f568f',
                            700: '#102b46',
                            800: '#0c2138',
                            900: '#08182a',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-in': 'fadeIn 0.6s ease-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-15px)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-white flex">

    <!-- Panel izquierdo: Branding -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden
                bg-gradient-to-br from-primary-900 via-primary-700 to-primary-500">

        <!-- Patrón decorativo -->
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <!-- Círculos decorativos flotantes -->
        <div class="absolute top-20 -left-20 w-72 h-72 bg-primary-400/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-20 -right-20 w-96 h-96 bg-cyan-400/20 rounded-full blur-3xl animate-float" style="animation-delay: 2s"></div>

        <div class="relative z-10 flex flex-col justify-between p-12 w-full text-white">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur border border-white/20
                            flex items-center justify-center text-2xl">
                    💊
                </div>
                <span class="text-2xl font-bold">VidaPlus</span>
            </div>

            <!-- Mensaje principal -->
            <div class="space-y-6">
                <h1 class="text-4xl lg:text-5xl font-bold leading-tight">
                    Tu farmacia,<br>
                    <span class="text-cyan-300">siempre disponible.</span>
                </h1>
                <p class="text-lg text-white/80 max-w-md">
                    Gestiona inventario, ventas y clientes desde una sola plataforma.
                </p>

                <!-- Features -->
                <div class="space-y-3 pt-6">
                    <div class="flex items-center gap-3 text-white/90">
                        <svg class="w-5 h-5 text-cyan-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Control de inventario en tiempo real</span>
                    </div>
                    <div class="flex items-center gap-3 text-white/90">
                        <svg class="w-5 h-5 text-cyan-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Ventas locales y en línea</span>
                    </div>
                    <div class="flex items-center gap-3 text-white/90">
                        <svg class="w-5 h-5 text-cyan-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Auditoría completa de operaciones</span>
                    </div>
                </div>
            </div>

            <!-- Footer del panel -->
            <p class="text-sm text-white/60">
                © <?= date('Y') ?> Farmacia VidaPlus · Todos los derechos reservados
            </p>
        </div>
    </div>

    <!-- Panel derecho: Formulario -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gray-50">
        <div class="w-full max-w-md animate-fade-in">

            <!-- Logo móvil -->
            <div class="lg:hidden flex items-center justify-center gap-3 mb-10">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-700 to-primary-500
                            flex items-center justify-center text-2xl shadow-lg">
                    💊
                </div>
                <span class="text-2xl font-bold text-primary-700">VidaPlus</span>
            </div>

            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Bienvenido de nuevo</h2>
                <p class="text-gray-500 mt-2">Ingresa tus credenciales para continuar</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 border border-red-200
                            flex items-start gap-3 animate-fade-in">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm text-red-700"><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Usuario o email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                        </div>
                        <input type="text" name="identificador"
                               value="<?= e($_POST['identificador'] ?? '') ?>"
                               required autofocus placeholder="admin o ana@mail.test"
                               class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                      outline-none transition bg-white">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Contraseña
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                      outline-none transition bg-white">
                    </div>
                </div>

                <button class="w-full bg-primary-700 hover:bg-primary-800 active:scale-[0.98]
                               text-white font-semibold py-2.5 rounded-lg transition-all shadow-sm
                               hover:shadow-md">
                    Entrar
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-3 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                        <span><strong>Empleados:</strong> tu usuario</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-cyan-500"></div>
                        <span><strong>Clientes:</strong> tu email</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>