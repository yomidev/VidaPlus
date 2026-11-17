<?php
// modules/portal/mis_pedidos.php
require_once __DIR__ . '/../../includes/auth.php';

if ($_SESSION['tipo'] !== 'cliente') {
    flash('error', 'Esta sección es exclusiva del portal de clientes.');
    redirect('/index.php');
}

$pdo = pdoActual();
$idCliente = (int)$_SESSION['usuario_id'];

$stmt = $pdo->prepare("
    SELECT v.id_venta, v.folio, v.fecha_venta, v.total, v.estado, v.canal,
           (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) AS num_items,
           e.estado AS envio_estado
    FROM ventas v
    LEFT JOIN envios e ON e.id_venta = v.id_venta
    WHERE v.id_cliente = ?
    ORDER BY v.fecha_venta DESC
");
$stmt->execute([$idCliente]);
$pedidos = $stmt->fetchAll();

$tituloPagina = 'Mis pedidos';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <span class="text-gray-900 font-medium">Mis pedidos</span>
</nav>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mis pedidos</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?> en tu historial
        </p>
    </div>
    <a href="/modules/medicamentos.php"
       class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-800 text-white
              font-semibold px-4 py-2 rounded-lg transition shadow-sm">
        + Hacer otro pedido
    </a>
</div>

<?php if (empty($pedidos)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-500 mb-4">Aún no tienes pedidos registrados.</p>
        <a href="/modules/medicamentos.php"
           class="inline-block px-4 py-2 text-sm bg-primary-700 hover:bg-primary-800
                  text-white rounded-lg transition">
            Explorar catálogo
        </a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($pedidos as $p): ?>
            <a href="/modules/portal/ver_pedido.php?id=<?= (int)$p['id_venta'] ?>"
               class="block bg-white rounded-xl shadow-sm border border-gray-100 p-5
                      hover:border-primary-300 hover:shadow-md transition">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-sm font-medium text-gray-900">
                                <?= e($p['folio']) ?>
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full
                                        <?= match($p['estado']) {
                                            'completada' => 'bg-green-100 text-green-700',
                                            'cancelada'  => 'bg-red-100 text-red-700',
                                            default      => 'bg-amber-100 text-amber-700',
                                        } ?>">
                                <?= e(ucfirst($p['estado'])) ?>
                            </span>
                            <?php if ($p['envio_estado']): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">
                                    Envío: <?= e(ucfirst($p['envio_estado'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= date('d/m/Y H:i', strtotime($p['fecha_venta'])) ?>
                            · <?= (int)$p['num_items'] ?> producto<?= (int)$p['num_items'] !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-4">
                        <span class="text-lg font-bold text-gray-900">
                            $<?= number_format((float)$p['total'], 2) ?>
                        </span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>