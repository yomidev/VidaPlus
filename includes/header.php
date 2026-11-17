<?php
// includes/header.php
$tituloPagina = $tituloPagina ?? 'VidaPlus';
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
$rolActual    = $_SESSION['rol_nombre'] ?? '';
$nombreActual = $_SESSION['nombre'] ?? 'Usuario';
$inicial      = strtoupper(substr($nombreActual, 0, 1));

// Colores del badge según rol
$badgeMap = [
    'admin'        => 'bg-rose-500/20 text-rose-100 ring-1 ring-rose-400/40',
    'farmaceutico' => 'bg-emerald-500/20 text-emerald-100 ring-1 ring-emerald-400/40',
    'cajero'       => 'bg-amber-500/20 text-amber-100 ring-1 ring-amber-400/40',
    'auditor'      => 'bg-slate-400/20 text-slate-100 ring-1 ring-slate-300/40',
    'cliente'      => 'bg-sky-500/20 text-sky-100 ring-1 ring-sky-400/40',
];
$badgeClass = $badgeMap[$rolActual] ?? 'bg-white/20 text-white ring-1 ring-white/20';

// ============================================================
// Construir el menú según el rol (con separadores por grupo)
// ============================================================
$menu = [];

if (($_SESSION['tipo'] ?? '') === 'cliente') {
    $menu[] = ['key' => 'medicamentos', 'label' => 'Catálogo',
               'href' => '/modules/medicamentos.php', 'icon' => 'beaker'];

    $cantidadCarrito = carritoCantidad();
    $labelCarrito = 'Carrito' . ($cantidadCarrito > 0 ? " ({$cantidadCarrito})" : '');
    $menu[] = ['key' => 'carrito', 'label' => $labelCarrito,
               'href' => '/modules/portal/carrito.php', 'icon' => 'cart'];

    $menu[] = ['key' => 'mis_pedidos', 'label' => 'Mis pedidos',
               'href' => '/modules/portal/mis_pedidos.php', 'icon' => 'package'];

    $menu[] = ['key' => 'mis_direcciones', 'label' => 'Direcciones',
               'href' => '/modules/portal/mis_direcciones.php', 'icon' => 'pin'];
} else {

    // ── Grupo 1: Operación diaria ───────────────────────────
    if (esRol('admin','farmaceutico','cajero','auditor')) {
        $menu[] = ['key' => 'medicamentos', 'label' => 'Medicamentos',
                   'href' => '/modules/medicamentos.php', 'icon' => 'beaker'];
    }
    if (esRol('admin','farmaceutico','cajero')) {
        $menu[] = ['key' => 'ventas', 'label' => 'Ventas',
                   'href' => '/modules/ventas.php', 'icon' => 'cart'];
    }

        // ── Grupo 1b: Suministro ────────────────────────────────
    if (esRol('admin','farmaceutico')) {
        $menu[] = ['key' => 'proveedores', 'label' => 'Proveedores',
                   'href' => '/modules/proveedores.php', 'icon' => 'truck'];
        $menu[] = ['key' => 'compras', 'label' => 'Compras',
                   'href' => '/modules/compras.php', 'icon' => 'inbox'];
    }

    // ── Grupo 2: Análisis ───────────────────────────────────
    if (esRol('admin','farmaceutico','auditor')) {
        $menu[] = ['type' => 'sep'];
        $menu[] = ['key' => 'reportes', 'label' => 'Reportes',
                   'href' => '/modules/reportes.php', 'icon' => 'chart'];
    }

    // ── Envíos (admin y farmacéutico) ───────────────────────
    if (esRol('admin','farmaceutico')) {
        $menu[] = ['key' => 'envios', 'label' => 'Envíos',
                   'href' => '/modules/envios.php', 'icon' => 'shipping'];
    }

    // ── Grupo 3: Administración ─────────────────────────────
    if (esRol('admin','auditor')) {
        $menu[] = ['type' => 'sep'];
        if (esRol('admin')) {
            $menu[] = ['key' => 'clientes', 'label' => 'Clientes',
                       'href' => '/modules/clientes.php', 'icon' => 'user-group'];
            $menu[] = ['key' => 'usuarios', 'label' => 'Usuarios',
                       'href' => '/modules/usuarios.php', 'icon' => 'users'];
            $menu[] = ['key' => 'backups', 'label' => 'Backups',
                   'href' => '/modules/backups.php', 'icon' => 'database'];
        }
        $menu[] = ['key' => 'auditoria', 'label' => 'Auditoría',
                   'href' => '/modules/auditoria.php', 'icon' => 'shield'];
    }
}

// ============================================================
// Paths de los íconos SVG
// ============================================================
$iconos = [
    'beaker'     => 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
    'cart'       => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    'users'      => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 011.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    'user-group' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
    'shield'     => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    'chart'      => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'database' => 'M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7a2 2 0 00-2-2H6a2 2 0 00-2 2zm0 5h16M4 7c0 1.66 3.58 3 8 3s8-1.34 8-3',
    'package' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    'pin'     => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    'truck'    => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1',
    'inbox'    => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    'shipping' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
];
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
                    },
                    animation: {
                        'fade-in-down': 'fadeInDown 0.15s ease-out',
                    },
                    keyframes: {
                        fadeInDown: {
                            '0%':   { opacity: '0', transform: 'translateY(-6px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .table-striped tbody tr:nth-child(even) { background-color: #f9fafb; }
        /* Quitar la flecha nativa de <details> */
        summary::-webkit-details-marker { display: none; }
        summary { list-style: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<nav class="sticky top-0 z-50 bg-gradient-to-r from-primary-900 via-primary-800 to-primary-700
            shadow-lg shadow-primary-900/20 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">

            <!-- Logo -->
            <a href="/index.php" class="flex items-center gap-3 text-white group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur border border-white/20
                            flex items-center justify-center text-xl
                            group-hover:bg-white/20 transition-all">
                    💊
                </div>
                <div class="leading-tight">
                    <div class="font-bold text-lg tracking-tight">VidaPlus</div>
                    <div class="text-[10px] text-white/60 uppercase tracking-wider">Farmacia</div>
                </div>
            </a>

            <!-- Nav desktop (con separadores) -->
            <div class="hidden lg:flex items-center gap-1 ml-8 flex-1">
                <?php foreach ($menu as $item): ?>
                    <?php if (($item['type'] ?? '') === 'sep'): ?>
                        <div class="w-px h-6 bg-white/15 mx-1"></div>
                    <?php else:
                        $activo = $paginaActual === $item['key'];
                    ?>
                        <a href="<?= e($item['href']) ?>"
                           class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all
                                  <?= $activo
                                        ? 'bg-white/15 text-white shadow-sm'
                                        : 'text-white/70 hover:text-white hover:bg-white/10' ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="<?= $iconos[$item['icon']] ?>"/>
                            </svg>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Usuario (desktop) -->
            <div class="hidden lg:flex items-center gap-3">
                <details class="relative group">
                    <summary class="flex items-center gap-3 cursor-pointer select-none
                                    px-2 py-1.5 rounded-lg hover:bg-white/10 transition">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-400 to-primary-500
                                    flex items-center justify-center text-white font-bold text-sm
                                    ring-2 ring-white/20">
                            <?= e($inicial) ?>
                        </div>
                        <div class="text-left">
                            <div class="text-sm text-white font-medium leading-tight">
                                <?= e($nombreActual) ?>
                            </div>
                            <span class="inline-block text-[10px] font-semibold uppercase tracking-wider
                                         px-2 py-0.5 rounded-full mt-0.5 <?= $badgeClass ?>">
                                <?= e($rolActual) ?>
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-white/50 group-open:rotate-180 transition-transform"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>

                    <div class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-xl
                                ring-1 ring-black/5 overflow-hidden animate-fade-in-down">
                        <div class="px-4 py-3 bg-gradient-to-br from-primary-50 to-white
                                    border-b border-gray-100">
                            <div class="text-sm font-semibold text-gray-900"><?= e($nombreActual) ?></div>
                            <div class="text-xs text-gray-500 mt-0.5">Sesión activa</div>
                        </div>

                        <div class="py-1">
                            <a href="/modules/perfil.php"
                               class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700
                                      hover:bg-gray-50 transition">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Mi perfil
                            </a>
                            <a href="/modules/cambiar_password.php"
                               class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700
                                      hover:bg-gray-50 transition">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Cambiar contraseña
                            </a>
                        </div>

                        <div class="border-t border-gray-100 py-1">
                            <a href="/modules/auth/logout.php"
                               class="flex items-center gap-3 px-4 py-2 text-sm text-red-600
                                      hover:bg-red-50 transition font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Cerrar sesión
                            </a>
                        </div>
                    </div>
                </details>
            </div>

            <!-- Botón menú móvil -->
            <button id="btnMenu" class="lg:hidden text-white p-2 rounded-lg hover:bg-white/10 transition">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Menú móvil (con separadores) -->
        <div id="menuMovil" class="hidden lg:hidden pb-4 space-y-1">
            <div class="flex items-center gap-3 px-3 py-3 mb-2 bg-white/10 rounded-lg">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-400 to-primary-500
                            flex items-center justify-center text-white font-bold ring-2 ring-white/20">
                    <?= e($inicial) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-white font-medium truncate"><?= e($nombreActual) ?></div>
                    <span class="inline-block text-[10px] font-semibold uppercase tracking-wider
                                 px-2 py-0.5 rounded-full mt-0.5 <?= $badgeClass ?>">
                        <?= e($rolActual) ?>
                    </span>
                </div>
            </div>

            <?php foreach ($menu as $item): ?>
                <?php if (($item['type'] ?? '') === 'sep'): ?>
                    <div class="pt-2 mt-2 border-t border-white/10"></div>
                <?php else:
                    $activo = $paginaActual === $item['key'];
                ?>
                    <a href="<?= e($item['href']) ?>"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition
                              <?= $activo
                                    ? 'bg-white/15 text-white'
                                    : 'text-white/80 hover:text-white hover:bg-white/10' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="<?= $iconos[$item['icon']] ?>"/>
                        </svg>
                        <?= e($item['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="pt-2 mt-2 border-t border-white/10">
                <a href="/modules/perfil.php"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-white/80
                          hover:text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Mi perfil
                </a>
                <a href="/modules/auth/logout.php"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-200
                          hover:bg-red-500/20 transition mt-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6">