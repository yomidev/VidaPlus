<?php
// includes/header.php
$tituloPagina = $tituloPagina ?? 'VidaPlus';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> — Farmacia VidaPlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eef4fb',
                            100: '#d9e6f5',
                            500: '#2e6ba8',
                            600: '#1f568f',
                            700: '#102b46',
                            800: '#0c2138',
                            900: '#08182a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Ajustes mínimos que Tailwind no cubre nativamente */
        .table-striped tbody tr:nth-child(even) { background-color: #f9fafb; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<nav class="bg-primary-700 shadow">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">

            <!-- Logo -->
            <a href="/index.php" class="flex items-center gap-2 text-white font-bold text-lg">
                <span class="text-2xl">💊</span>
                <span>VidaPlus</span>
            </a>

            <!-- Botón hamburguesa móvil -->
            <button id="btnMenu" class="md:hidden text-white p-2 rounded hover:bg-primary-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Links desktop -->
            <div class="hidden md:flex md:items-center md:gap-1 flex-1 ml-6">
                <?php if (esRol('admin','farmaceutico','cajero','auditor')): ?>
                    <a href="/modules/medicamentos.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Medicamentos
                    </a>
                <?php endif; ?>
                <?php if (esRol('admin','farmaceutico','cajero')): ?>
                    <a href="/modules/ventas.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Ventas
                    </a>
                <?php endif; ?>
                <?php if (esRol('admin')): ?>
                    <a href="/modules/usuarios.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Usuarios
                    </a>
                    <a href="/modules/clientes.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Clientes
                    </a>
                <?php endif; ?>
                <?php if (esRol('admin','auditor')): ?>
                    <a href="/modules/auditoria.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Auditoría
                    </a>
                <?php endif; ?>
                <?php if (esRol('cliente')): ?>
                    <a href="/modules/medicamentos.php"
                       class="px-3 py-2 rounded text-white/90 hover:bg-primary-600 hover:text-white text-sm font-medium transition">
                        Catálogo
                    </a>
                <?php endif; ?>
            </div>

            <!-- Usuario y logout -->
            <div class="hidden md:flex md:items-center md:gap-3">
                <div class="text-right text-sm">
                    <div class="text-white font-medium"><?= e($_SESSION['nombre']) ?></div>
                    <span class="inline-block bg-white/20 text-white text-xs px-2 py-0.5 rounded-full">
                        <?= e($_SESSION['rol_nombre']) ?>
                    </span>
                </div>
                <a href="/modules/auth/logout.php"
                   class="px-3 py-1.5 text-sm border border-white/40 rounded text-white
                          hover:bg-white hover:text-primary-700 transition">
                    Salir
                </a>
            </div>
        </div>

        <!-- Menú móvil (oculto por defecto) -->
        <div id="menuMovil" class="hidden md:hidden pb-3 space-y-1">
            <?php if (esRol('admin','farmaceutico','cajero','auditor')): ?>
                <a href="/modules/medicamentos.php" class="block px-3 py-2 rounded text-white hover:bg-primary-600 text-sm">Medicamentos</a>
            <?php endif; ?>
            <?php if (esRol('admin','farmaceutico','cajero')): ?>
                <a href="/modules/ventas.php" class="block px-3 py-2 rounded text-white hover:bg-primary-600 text-sm">Ventas</a>
            <?php endif; ?>
            <?php if (esRol('admin')): ?>
                <a href="/modules/usuarios.php" class="block px-3 py-2 rounded text-white hover:bg-primary-600 text-sm">Usuarios</a>
                <a href="/modules/clientes.php" class="block px-3 py-2 rounded text-white hover:bg-primary-600 text-sm">Clientes</a>
            <?php endif; ?>
            <?php if (esRol('admin','auditor')): ?>
                <a href="/modules/auditoria.php" class="block px-3 py-2 rounded text-white hover:bg-primary-600 text-sm">Auditoría</a>
            <?php endif; ?>
            <a href="/modules/auth/logout.php" class="block px-3 py-2 rounded text-white bg-primary-800 text-sm">Salir</a>
        </div>
    </div>
</nav>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6">