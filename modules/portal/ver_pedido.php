<?php
// modules/portal/ver_pedido.php
require_once __DIR__ . '/../../includes/auth.php';

if ($_SESSION['tipo'] !== 'cliente') {
    flash('error', 'Esta sección es exclusiva del portal de clientes.');
    redirect('/index.php');
}

$pdo = pdoActual();
$idCliente = (int)$_SESSION['usuario_id'];
$idVenta = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT v.*, d.alias AS dir_alias, d.calle AS dir_calle, d.colonia AS dir_colonia,
           d.ciudad AS dir_ciudad, d.estado_provincia AS dir_estado,
           d.codigo_postal AS dir_cp, p.nombre AS dir_pais,
           e.empresa_paqueteria, e.estado AS envio_estado
    FROM ventas v
    LEFT JOIN direcciones_cliente d ON v.id_direccion_envio = d.id_direccion
    LEFT JOIN paises p ON d.id_pais = p.id_pais
    LEFT JOIN envios e ON e.id_venta = v.id_venta
    WHERE v.id_venta = ? AND v.id_cliente = ?
");
$stmt->execute([$idVenta, $idCliente]);
$venta = $stmt->fetch();

if (!$venta) {
    flash('error', 'Pedido no encontrado.');
    redirect('/modules/portal/mis_pedidos.php');
}

$stmt = $pdo->prepare("
    SELECT dv.*, m.nombre AS medicamento, m.presentacion
    FROM detalle_ventas dv
    INNER JOIN medicamentos m ON dv.id_medicamento = m.id_medicamento
    WHERE dv.id_venta = ?
");
$stmt->execute([$idVenta]);
$detalles = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pagos WHERE id_venta = ?");
$stmt->execute([$idVenta]);
$pagos = $stmt->fetchAll();

$tituloPagina = 'Pedido ' . $venta['folio'];
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/portal/mis_pedidos.php" class="hover:text-primary-600">Mis pedidos</a>
    <span>›</span>
    <span class="text-gray-900 font-medium"><?= e($venta['folio']) ?></span>
</nav>

<?php if ($msg = flash('exito')): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3">
        <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <div>
            <strong><?= e($msg) ?></strong>
            <p class="text-sm mt-1">Recibirás un correo de confirmación con los detalles del envío.</p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <div class="lg:col-span-2 space-y-4">

        <!-- Productos -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                    Productos (<?= count($detalles) ?>)
                </h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                        <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Cant.</th>
                        <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Precio</th>
                        <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($detalles as $d): ?>
                        <tr>
                            <td class="px-5 py-3">
                                <div class="text-sm font-medium text-gray-900"><?= e($d['medicamento']) ?></div>
                                <div class="text-xs text-gray-500"><?= e($d['presentacion']) ?></div>
                            </td>
                            <td class="px-5 py-3 text-sm text-right"><?= (int)$d['cantidad'] ?></td>
                            <td class="px-5 py-3 text-sm text-right">$<?= number_format((float)$d['precio_unitario'], 2) ?></td>
                            <td class="px-5 py-3 text-sm text-right font-medium">$<?= number_format((float)$d['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
                <div class="max-w-xs ml-auto space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900">$<?= number_format((float)$venta['subtotal'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">IVA</span>
                        <span class="text-gray-900">$<?= number_format((float)$venta['iva'], 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Envío</span>
                        <span class="text-gray-900">$<?= number_format((float)$venta['costo_envio'], 2) ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 mt-2">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="font-bold text-lg text-gray-900">$<?= number_format((float)$venta['total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pago -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Pago</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($pagos as $p): ?>
                    <li class="px-5 py-3 flex items-center justify-between text-sm">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                            <?= e(ucfirst($p['metodo'])) ?>
                        </span>
                        <span class="text-gray-500"><?= date('d/m/Y H:i', strtotime($p['fecha_pago'])) ?></span>
                        <strong class="text-gray-900">$<?= number_format((float)$p['monto'], 2) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Estado y envío -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                Estado del pedido
            </h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Estado</span>
                    <span class="text-xs px-2 py-0.5 rounded-full
                                <?= match($venta['estado']) {
                                    'completada' => 'bg-green-100 text-green-700',
                                    'cancelada'  => 'bg-red-100 text-red-700',
                                    default      => 'bg-amber-100 text-amber-700',
                                } ?>">
                        <?= e(ucfirst($venta['estado'])) ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Fecha</span>
                    <span class="text-gray-900"><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Folio</span>
                    <span class="font-mono text-gray-900"><?= e($venta['folio']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($venta['dir_calle']): ?>
            <div class="bg-white rounded-xl shadow-sm border border-sky-200 p-5">
                <h2 class="text-sm font-semibold text-sky-700 uppercase tracking-wide mb-3">
                    Envío a
                </h2>
                <div class="text-sm text-gray-900 font-medium"><?= e($venta['dir_alias']) ?></div>
                <div class="text-sm text-gray-600 mt-1"><?= e($venta['dir_calle']) ?></div>
                <?php if ($venta['dir_colonia']): ?>
                    <div class="text-sm text-gray-500">Col. <?= e($venta['dir_colonia']) ?></div>
                <?php endif; ?>
                <div class="text-sm text-gray-600"><?= e($venta['dir_ciudad']) ?>, <?= e($venta['dir_estado']) ?></div>
                <div class="text-sm text-gray-500">CP <?= e($venta['dir_cp']) ?> · <?= e($venta['dir_pais']) ?></div>

                <?php if ($venta['envio_estado']): ?>
                    <div class="mt-3 pt-3 border-t border-sky-100 text-xs text-sky-700">
                        Estado del envío: <strong><?= e(ucfirst($venta['envio_estado'])) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="/modules/portal/mis_pedidos.php"
           class="block w-full bg-primary-700 hover:bg-primary-800 text-white text-center
                  font-semibold py-2.5 rounded-lg transition shadow-sm">
            Ver todos mis pedidos
        </a>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>