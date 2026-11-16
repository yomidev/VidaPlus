</main>

<footer class="border-t border-gray-200 py-4 text-center text-sm text-gray-500">
    Farmacia VidaPlus — <?= date('Y') ?>
</footer>

<script>
    // Toggle del menú móvil
    const btn = document.getElementById('btnMenu');
    const menu = document.getElementById('menuMovil');
    if (btn && menu) {
        btn.addEventListener('click', () => menu.classList.toggle('hidden'));
    }
</script>

</body>
</html>