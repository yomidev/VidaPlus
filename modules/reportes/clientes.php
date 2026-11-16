<?php
require_once __DIR__ . '/../../includes/auth.php';
requiereRol('admin', 'farmaceutico', 'auditor');

$pdo = pdoActual();
$tab = $_GET['tab'] ?? 'top';
$limite = (int)($_GET['limite'] ?? 20);

if ($tab === 'top') {
    $stmt = $pdo->prepare("CALL sp_reporte_clientes_top(?)");
    $stmt->execute([$limite]);
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
} else {
    $stmt = $pdo->prepare("CALL sp_reporte_clientes_sin_compras()");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $stmt->closeCursor();
}

$tituloPagina = 'Reporte de clientes';
require __DIR__ . '/../../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
    <a href="/modules/reportes.php" class="hover:text-primary-600">Reportes</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">Clientes</span>
</nav>

<h1 class="text-2xl font-bold text-gray-900 mb-5">Análisis de clientes</h1>

<div class="border-b border-gray-200 mb-5">
    <nav class="flex gap-1">
        <a href="?tab=top&limite=<?= $limite ?>"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                  <?= $tab === 'top'
                      ? 'border-primary-600 text-primary-700'
                      : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
            Top compradores
        </a>
        <a href="?tab=sin_compras"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                  <?= $tab === 'sin_compras'
                      ? 'border-primary-600 text-primary-700'
                      : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
            Sin compras
        </a>
    </nav>
</div>

<?php if ($tab === 'top'): ?>
    <div class="mb-4 flex items-center gap-2">
        <span class="text-sm text-gray-500">Mostrar:</span>
        <?php foreach ([10, 20, 50] as $n): ?>
            <a href="?tab=top&limite=<?= $n ?>"
               class="text-xs px-3 py-1 rounded-lg transition
                      <?= $limite === $n
                          ? 'bg-primary-700 text-white'
                          : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                Top <?= $n ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">País</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Compras</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Última</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Puntos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Sin datos</td></tr>
                <?php else: foreach ($rows as $i => $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-bold text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($r['cliente']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($r['email']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['pais']) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium"><?= (int)$r['num_compras'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-emerald-600">
                            $<?= number_format((float)$r['total_gastado'], 2) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600">
                            <?= $r['ultima_compra'] ? date('d/m/Y', strtotime($r['ultima_compra'])) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-purple-600 font-medium">
                            <?= number_format((int)$r['puntos_lealtad']) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <p class="text-sm text-gray-500">
                Clientes activos que nunca han comprado:
                <strong class="text-gray-900"><?= count($rows) ?></strong>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Teléfono</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">País</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Registro</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Días</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-emerald-600">
                        ✓ Todos los clientes registrados han hecho al menos una compra
                    </td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= e($r['cliente']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['email']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['telefono'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($r['pais']) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-gray-600">
                            <?= date('d/m/Y', strtotime($r['fecha_registro'])) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-amber-700 font-medium">
                            <?= (int)$r['dias_registrado'] ?> días
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>