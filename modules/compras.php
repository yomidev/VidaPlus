<?php
// modules/compras.php
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico');

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'nueva'         => (function(){ header('Location: /modules/compras_nueva.php'); exit; })(),
    'ver'           => accionVer(),
    'cambiarEstado' => accionCambiarEstado(),
    default         => accionListar(),
};

function accionListar(): void
{
    $pdo = pdoActual();
    $busqueda = trim($_GET['q'] ?? '');
    $estadoF  = $_GET['estado'] ?? '';

    $where = [];
    $params = [];
    if ($busqueda !== '') {
        $where[] = "(c.folio LIKE ? OR p.nombre LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%");
    }
    if (in_array($estadoF, ['pendiente','recibida','cancelada'])) {
        $where[] = "c.estado = ?";
        $params[] = $estadoF;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre AS proveedor, u.nombre_completo AS usuario,
               (SELECT COUNT(*) FROM detalle_compras dc WHERE dc.id_compra = c.id_compra) AS num_items
        FROM compras c
        INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        $whereSql
        ORDER BY c.fecha_compra DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $tituloPagina = 'Compras';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Compras a proveedores</h1>
            <p class="text-sm text-gray-500 mt-1"><?= count($rows) ?> compra<?= count($rows) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/modules/compras_nueva.php" class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-800 text-white font-semibold px-4 py-2 rounded-lg transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva compra
        </a>
    </div>

    <?php if ($msg = flash('exito')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex gap-2">
            <input type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por folio o proveedor..."
                   class="flex-1 px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none">
            <select name="estado" class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none">
                <option value="">Estado</option>
                <option value="pendiente" <?= $estadoF === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="recibida"  <?= $estadoF === 'recibida'  ? 'selected' : '' ?>>Recibida</option>
                <option value="cancelada" <?= $estadoF === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
            </select>
            <button class="bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">Filtrar</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Folio</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Proveedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Registró</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Items</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Sin compras registradas</td></tr>
                <?php else: foreach ($rows as $c): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900"><?= e($c['folio']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= date('d/m/Y', strtotime($c['fecha_compra'])) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= e($c['proveedor']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($c['usuario']) ?></td>
                        <td class="px-4 py-3 text-sm text-center"><?= (int)$c['num_items'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">$<?= number_format((float)$c['total'], 2) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                        <?= match($c['estado']) {
                                            'recibida'  => 'bg-emerald-100 text-emerald-700',
                                            'cancelada' => 'bg-red-100 text-red-700',
                                            default     => 'bg-amber-100 text-amber-700',
                                        } ?>"><?= e(ucfirst($c['estado'])) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <a href="/modules/compras.php?accion=ver&id=<?= (int)$c['id_compra'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-primary-700 hover:bg-primary-800 text-white transition">Ver</a>
                            <?php if ($c['estado'] === 'pendiente'): ?>
                                <a href="/modules/compras.php?accion=cambiarEstado&id=<?= (int)$c['id_compra'] ?>&estado=recibida"
                                   onclick="return confirm('¿Marcar como RECIBIDA? Esto sumará el stock.')"
                                   class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 transition">Recibir</a>
                                <a href="/modules/compras.php?accion=cambiarEstado&id=<?= (int)$c['id_compra'] ?>&estado=cancelada"
                                   onclick="return confirm('¿Cancelar la compra?')"
                                   class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50 transition">Cancelar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
}

function accionVer(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $pdo = pdoActual();

    $stmt = $pdo->prepare("
        SELECT c.*, p.nombre AS proveedor, u.nombre_completo AS usuario
        FROM compras c
        INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        WHERE c.id_compra = ?
    ");
    $stmt->execute([$id]);
    $compra = $stmt->fetch();
    if (!$compra) { flash('error', 'Compra no encontrada.'); redirect('/modules/compras.php'); }

    $stmt = $pdo->prepare("
        SELECT dc.*, m.nombre AS medicamento, m.presentacion, m.stock AS stock_actual
        FROM detalle_compras dc
        INNER JOIN medicamentos m ON dc.id_medicamento = m.id_medicamento
        WHERE dc.id_compra = ?
    ");
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll();

    $tituloPagina = 'Compra ' . $compra['folio'];
    require __DIR__ . '/../includes/header.php';
    ?>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/compras.php" class="hover:text-primary-600">Compras</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= e($compra['folio']) ?></span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e($compra['folio']) ?></h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= date('d/m/Y H:i', strtotime($compra['fecha_compra'])) ?> · <?= e($compra['proveedor']) ?>
            </p>
        </div>
        <span class="text-xs px-2 py-1 rounded-full self-start
                    <?= match($compra['estado']) {
                        'recibida'  => 'bg-emerald-100 text-emerald-700',
                        'cancelada' => 'bg-red-100 text-red-700',
                        default     => 'bg-amber-100 text-amber-700',
                    } ?>"><?= e(ucfirst($compra['estado'])) ?></span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Productos</h2>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 uppercase">Medicamento</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Cant.</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Precio</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Subtotal</th>
                    <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Stock actual</th>
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
                        <td class="px-5 py-3 text-sm text-right text-gray-600"><?= (int)$d['stock_actual'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <div class="max-w-xs ml-auto space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="text-gray-900">$<?= number_format((float)$compra['subtotal'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-gray-500">IVA</span><span class="text-gray-900">$<?= number_format((float)$compra['iva'], 2) ?></span></div>
                <div class="flex justify-between pt-2 border-t border-gray-200 mt-2"><span class="font-semibold text-gray-900">Total</span><span class="font-bold text-lg text-gray-900">$<?= number_format((float)$compra['total'], 2) ?></span></div>
            </div>
        </div>
    </div>

    <?php if ($compra['estado'] === 'pendiente'): ?>
        <div class="flex gap-2">
            <a href="/modules/compras.php?accion=cambiarEstado&id=<?= $id ?>&estado=recibida"
               onclick="return confirm('¿Marcar como RECIBIDA? Esto sumará el stock.')"
               class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2 rounded-lg transition shadow-sm">Marcar como recibida</a>
            <a href="/modules/compras.php?accion=cambiarEstado&id=<?= $id ?>&estado=cancelada"
               onclick="return confirm('¿Cancelar la compra?')"
               class="border border-red-300 text-red-700 hover:bg-red-50 font-semibold px-5 py-2 rounded-lg transition">Cancelar compra</a>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../includes/footer.php';
}

function accionCambiarEstado(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $estado = $_GET['estado'] ?? '';

    if (!in_array($estado, ['recibida','cancelada'])) {
        flash('error', 'Estado inválido.');
        redirect('/modules/compras.php');
    }

    $pdo = pdoActual();
    try {
        // Construir el UPDATE
        // Cuando el estado es 'recibida', también seteamos fecha_recepcion
        // (solo si aún no tiene una fecha asignada)
        $sql = "UPDATE compras SET estado = ?";
        $params = [$estado];

        if ($estado === 'recibida') {
            $sql .= ", fecha_recepcion = COALESCE(fecha_recepcion, NOW())";
        }

        $sql .= " WHERE id_compra = ?";
        $params[] = $id;

        $pdo->prepare($sql)->execute($params);

        flash('exito', "Compra marcada como $estado. El stock se actualizó automáticamente.");
    } catch (PDOException $ex) {
        flash('error', 'Error: ' . $ex->getMessage());
    }
    redirect('/modules/compras.php');
}