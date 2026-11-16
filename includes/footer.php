<?php
// includes/footer.php
// Variables disponibles: $menu (definido en header.php)
?>
</main>

<footer class="mt-auto bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700
               text-white/80 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-10">

            <!-- Columna 1: Marca -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur border border-white/20
                                flex items-center justify-center text-xl">
                        💊
                    </div>
                    <div class="leading-tight">
                        <div class="font-bold text-lg text-white tracking-tight">VidaPlus</div>
                        <div class="text-[10px] text-white/50 uppercase tracking-wider">Farmacia en línea</div>
                    </div>
                </div>
                <p class="text-sm text-white/60 leading-relaxed max-w-xs">
                    Plataforma de gestión farmacéutica. Inventario, ventas, clientes y auditoría
                    en un solo lugar.
                </p>
                <span class="inline-block text-[10px] font-semibold uppercase tracking-wider
                             px-2 py-1 rounded bg-white/10 text-white/70 ring-1 ring-white/10">
                    v1.0.0
                </span>
            </div>

            <!-- Columna 2: Navegación según rol -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-white/50 mb-4">
                    Navegación
                </h3>
                <ul class="space-y-2 text-sm">
                    <?php if (!empty($menu)): foreach ($menu as $item): ?>
                        <li>
                            <a href="<?= e($item['href']) ?>"
                               class="text-white/70 hover:text-white transition-colors inline-flex items-center gap-2">
                                <svg class="w-3 h-3 text-white/30" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <?= e($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; endif; ?>
                    <li>
                        <a href="/modules/perfil.php"
                           class="text-white/70 hover:text-white transition-colors inline-flex items-center gap-2">
                            <svg class="w-3 h-3 text-white/30" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Mi perfil
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Columna 3: Sistema -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-white/50 mb-4">
                    Sistema
                </h3>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-center gap-3">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        <span class="text-white/70">Todos los servicios operativos</span>
                    </li>
                    <li class="flex items-center gap-3 text-white/60">
                        <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Base de datos: <strong class="text-white/80">MySQL 8.0</strong></span>
                    </li>
                    <li class="flex items-center gap-3 text-white/60">
                        <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Servidor: <strong class="text-white/80"><?= date('d/m/Y H:i') ?></strong></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Línea divisoria y copyright -->
        <div class="mt-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row
                    items-center justify-between gap-3 text-xs text-white/50">
            <p>
                © <?= date('Y') ?> Farmacia VidaPlus · Todos los derechos reservados
            </p>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-white/80 transition-colors">Privacidad</a>
                <span class="text-white/20">·</span>
                <a href="#" class="hover:text-white/80 transition-colors">Términos</a>
                <span class="text-white/20">·</span>
                <a href="#" class="hover:text-white/80 transition-colors">Soporte</a>
            </div>
        </div>
    </div>
</footer>

<script>
    // Toggle del menú móvil
    const btn = document.getElementById('btnMenu');
    const menuMovil = document.getElementById('menuMovil');
    if (btn && menuMovil) {
        btn.addEventListener('click', () => menuMovil.classList.toggle('hidden'));
    }

    // Cerrar dropdown de usuario al hacer clic fuera
    document.addEventListener('click', (e) => {
        document.querySelectorAll('details[open]').forEach(d => {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
</script>

</body>
</html>