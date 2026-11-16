<?php
require_once __DIR__ . '/../includes/auth.php';

$tituloPagina = 'Panel';
require_once __DIR__ . '/../includes/header.php';

$pdo = pdoActual();
$rol = $_SESSION['rol_nombre'];
?>

<h1 class="text-2xl font-bold text-gray-900 mb-1">
    Hola, <?= e($_SESSION['nombre']) ?>
</h1>
<p class="text-gray-500 mb-6">
    Estás conectado como
    <span class="inline-block bg-primary-100 text-primary-700 text-xs font-semibold
                 px-2 py-1 rounded-full uppercase tracking-wide">
        <?= e($rol) ?>
    </span>
</p>

<?php if ($rol === 'admin' || $rol === 'farmaceutico'): ?>
    <?php
        $medicamentos = (int)$pdo->query("SELECT COUNT(*) FROM medicamentos WHERE activo=1")->fetchColumn();
        $stockBajo    = (int)$pdo->query("SELECT COUNT(*) FROM medicamentos WHERE stock < stock_minimo AND activo=1")->fetchColumn();
        $ventasHoy    = (int)$pdo->query("SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta)=CURDATE()")->fetchColumn();
        $ingresoHoy   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM ventas WHERE DATE(fecha_venta)=CURDATE() AND estado='completada'")->fetchColumn();
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Medicamentos</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $medicamentos ?></p>
            <p class="text-xs text-gray-400 mt-1">activos en catálogo</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-yellow-200 p-5">
            <p class="text-xs text-yellow-700 uppercase tracking-wide">Stock bajo</p>
            <p class="text-3xl font-bold text-yellow-600 mt-1"><?= $stockBajo ?></p>
            <p class="text-xs text-yellow-500 mt-1">por reabastecer</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Ventas hoy</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?= $ventasHoy ?></p>
            <p class="text-xs text-gray-400 mt-1">transacciones</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-green-200 p-5">
            <p class="text-xs text-green-700 uppercase tracking-wide">Ingreso hoy</p>
            <p class="text-3xl font-bold text-green-600 mt-1">$<?= number_format($ingresoHoy, 2) ?></p>
            <p class="text-xs text-green-500 mt-1">ventas completadas</p>
        </div>
    </div>

<?php elseif ($rol === 'cajero'): ?>
    <?php
        $ventasHoy = (int)$pdo->query("
            SELECT COUNT(*) FROM ventas
            WHERE id_usuario = " . (int)$_SESSION['usuario_id'] . "
              AND DATE(fecha_venta) = CURDATE()
        ")->fetchColumn();
    ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 mb-4">
        Ventas registradas hoy: <strong><?= $ventasHoy ?></strong>
    </div>
    <a href="/modules/ventas.php?accion=nueva"
       class="inline-block bg-primary-700 hover:bg-primary-800 text-white font-semibold
              px-4 py-2 rounded-lg transition shadow">
        + Nueva venta
    </a>

<?php elseif ($rol === 'auditor'): ?>
    <?php $total = (int)$pdo->query("SELECT COUNT(*) FROM auditoria")->fetchColumn(); ?>
    <div class="bg-gray-100 border border-gray-200 text-gray-800 rounded-lg p-4 mb-4">
        Registros de auditoría acumulados: <strong><?= $total ?></strong>
    </div>
    <a href="/modules/auditoria.php"
       class="inline-block bg-gray-700 hover:bg-gray-800 text-white font-semibold
              px-4 py-2 rounded-lg transition shadow">
        Ver auditoría
    </a>

<?php elseif ($rol === 'cliente'): ?>
    <?php $productos = (int)$pdo->query("SELECT COUNT(*) FROM v_catalogo_online")->fetchColumn(); ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 mb-4">
        Productos disponibles para ti: <strong><?= $productos ?></strong>
    </div>
    <a href="/modules/medicamentos.php"
       class="inline-block bg-primary-700 hover:bg-primary-800 text-white font-semibold
              px-4 py-2 rounded-lg transition shadow">
        Ver catálogo
    </a>
    <p class="text-sm text-gray-500 mt-3">
        Recuerda: solo mostramos medicamentos sin receta.
    </p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>