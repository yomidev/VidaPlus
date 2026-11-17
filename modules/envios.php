<?php
// modules/envios.php — Gestión de envíos de ventas online
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico');

$pdo = pdoActual();
$accion = $_GET['accion'] ?? 'listar';

// ============================================================
// Actualizar estado (POST)
// ============================================================
if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEnvio = (int)($_POST['id_envio'] ?? 0);
    $nuevoEstado = $_POST['estado'] ?? '';
    $guia = trim($_POST['numero_guia'] ?? '');
    $empresa = trim($_POST['empresa_paqueteria'] ?? '');

    $validos = ['pendiente','preparando','enviado','entregado','cancelado'];
    if (!in_array($nuevoEstado, $validos)) {
        flash('error', 'Estado inválido.');
        redirect('/modules/envios.php');
    }

    try {
        $sets = ['estado = ?'];
        $params = [$nuevoEstado];

        if ($guia !== '') { $sets[] = 'numero_guia = ?'; $params[] = $guia; }
        if ($empresa !== '') { $sets[] = 'empresa_paqueteria = ?'; $params[] = $empresa; }

        if ($nuevoEstado === 'enviado' && empty($_POST['fecha_envio'])) {
            $sets[] = 'fecha_envio = NOW()';
        }
        if ($nuevoEstado === 'entregado') {
            $sets[] = 'fecha_entrega_real = NOW()';
        }

        $params[] = $idEnvio;
        $pdo->prepare("UPDATE envios SET " . implode(', ', $sets) . " WHERE id_envio = ?")
            ->execute($params);

        flash('exito', 'Envío actualizado.');
    } catch (PDOException $ex) {
        flash('error', 'Error: ' . $ex->getMessage());
    }
    redirect('/modules/envios.php');
}

// ============================================================
// Listar
// ============================================================
$estadoF = $_GET['estado'] ?? '';
$where = [];
$params = [];
if (in_array($estadoF, ['pendiente','preparando','enviado','entregado','cancelado'])) {
    $where[] = "e.estado = ?";
    $params[] = $estadoF;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT e.*, v.folio AS venta_folio, v.total AS venta_total,
           CONCAT(c.nombre, ' ', c.apellido) AS cliente,
           d.calle, d.ciudad, d.estado_provincia, d.codigo_postal,
           p.nombre AS pais
    FROM envios e
    INNER JOIN ventas v ON e.id_venta = v.id_venta
    LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN direcciones_cliente d ON v.id_direccion_envio = d.id_direccion
    LEFT JOIN paises p ON d.id_pais = p.id_pais
    $whereSql
    ORDER BY e.estado ASC, e.id_envio DESC
");
$stmt->execute($params);
$envios = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT estado, COUNT(*) AS num FROM envios GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

$tituloPagina = 'Envíos';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de envíos</h1>
        <p class="text-sm text-gray-500 mt-1">Envíos de pedidos online</p>
    </div>
</div>

<?php if ($msg = flash('exito')): ?>
    <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($msg) ?></div>
<?php endif; ?>

<!-- Stats por estado -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-5">
    <?php
    $estados = [
        'pendiente'  => ['label' => 'Pendientes',  'color' => 'amber'],
        'preparando' => ['label' => 'Preparando',  'color' => 'blue'],
        'enviado'    => ['label' => 'Enviados',    'color' => 'indigo'],
        'entregado'  => ['label' => 'Entregados',  'color' => 'emerald'],
        'cancelado'  => ['label' => 'Cancelados',  'color' => 'red'],
    ];
    foreach ($estados as $key => $info):
        $num = (int)($stats[$key] ?? 0);
    ?>
        <a href="?estado=<?= $key ?>"
           class="bg-white rounded-lg shadow-sm border border-<?= $info['color'] ?>-200 p-3 hover:shadow-md transition">
            <div class="text-xs text-<?= $info['color'] ?>-700 uppercase tracking-wide"><?= $info['label'] ?></div>
            <div class="text-2xl font-bold text-<?= $info['color'] ?>-600"><?= $num ?></div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($estadoF): ?>
    <div class="mb-3">
        <a href="/modules/envios.php" class="text-sm text-primary-600 hover:underline">← Ver todos los envíos</a>
    </div>
<?php endif; ?>

<?php if (empty($envios)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
        No hay envíos <?= $estadoF ? "en estado '$estadoF'" : 'registrados' ?>.
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($envios as $e):
            $badgeEstado = match($e['estado']) {
                'entregado' => 'bg-emerald-100 text-emerald-700',
                'enviado'   => 'bg-indigo-100 text-indigo-700',
                'preparando'=> 'bg-blue-100 text-blue-700',
                'cancelado' => 'bg-red-100 text-red-700',
                default     => 'bg-amber-100 text-amber-700',
            };
        ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="/modules/ventas.php?accion=ver&id=<?= (int)$e['id_venta'] ?>"
                               class="font-mono text-sm font-medium text-primary-700 hover:underline">
                                <?= e($e['venta_folio']) ?>
                            </a>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeEstado ?>">
                                <?= e(ucfirst($e['estado'])) ?>
                            </span>
                            <?php if ($e['numero_guia']): ?>
                                <span class="text-xs text-gray-500 font-mono">
                                    Guía: <?= e($e['numero_guia']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-700 mt-1">
                            <?= e($e['cliente'] ?? 'Cliente desconocido') ?>
                            · $<?= number_format((float)$e['venta_total'], 2) ?>
                        </p>
                        <?php if ($e['calle']): ?>
                            <p class="text-xs text-gray-500 mt-0.5">
                                📍 <?= e($e['calle']) ?>, <?= e($e['ciudad']) ?>, <?= e($e['estado_provincia']) ?>
                                <?= e($e['codigo_postal']) ?> · <?= e($e['pais']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1">
                            Paquetería: <?= e($e['empresa_paqueteria']) ?>
                            <?php if ($e['fecha_envio']): ?>
                                · Enviado: <?= date('d/m/Y', strtotime($e['fecha_envio'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Formulario inline para actualizar estado -->
                    <form method="POST" action="/modules/envios.php?accion=actualizar"
                          class="flex flex-wrap gap-2 items-end">
                        <input type="hidden" name="id_envio" value="<?= (int)$e['id_envio'] ?>">
                        <div>
                            <label class="text-xs text-gray-500 block mb-0.5">Estado</label>
                            <select name="estado" required
                                    class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                                <option value="pendiente"  <?= $e['estado'] === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
                                <option value="preparando" <?= $e['estado'] === 'preparando' ? 'selected' : '' ?>>Preparando</option>
                                <option value="enviado"    <?= $e['estado'] === 'enviado'    ? 'selected' : '' ?>>Enviado</option>
                                <option value="entregado"  <?= $e['estado'] === 'entregado'  ? 'selected' : '' ?>>Entregado</option>
                                <option value="cancelado"  <?= $e['estado'] === 'cancelado'  ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-0.5">Guía</label>
                            <input type="text" name="numero_guia" value="<?= e($e['numero_guia'] ?? '') ?>"
                                   placeholder="Opcional"
                                   class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg w-32 focus:ring-2 focus:ring-primary-500 outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-0.5">Paquetería</label>
                            <input type="text" name="empresa_paqueteria" value="<?= e($e['empresa_paqueteria'] ?? '') ?>"
                                   placeholder="DHL, FedEx..."
                                   class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg w-28 focus:ring-2 focus:ring-primary-500 outline-none">
                        </div>
                        <button type="submit"
                                class="bg-primary-700 hover:bg-primary-800 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                            Actualizar
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>