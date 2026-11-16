<?php
// modules/backups.php — Solo admin
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin');

$BACKUP_DIR = __DIR__ . '/../backups';
$LOG_DIR    = $BACKUP_DIR . '/logs';
$DB_NAME    = 'vidaplus';

// ============================================================
// ACCIONES (POST)
// ============================================================
$mensajeExito = null;
$mensajeError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Ejecutar backup manual
    if ($accion === 'crear') {
        $script = escapeshellarg(__DIR__ . '/../sql/06_backup.sh');

        // En Windows puede requerir bash explícito (Git Bash o WSL)
        $comando = "bash $script full 2>&1";
        $salida  = shell_exec($comando);

        if ($salida && str_contains($salida, 'OK|')) {
            $lineas = array_filter(explode("\n", $salida));
            $ultima = end($lineas);
            $partes = explode('|', $ultima);
            $mensajeExito = "Backup creado: " . basename($partes[1] ?? '') .
                          " (" . ($partes[2] ?? '?') . " KB)";
        } else {
            $mensajeError = "No se pudo completar el backup. Revisa el log.";
            error_log("Backup manual falló: " . substr($salida ?? '', -500));
        }
    }

    // Eliminar un backup
    if ($accion === 'eliminar') {
        $archivo = basename($_POST['archivo'] ?? '');
        $ruta = $BACKUP_DIR . '/' . $archivo;
        if ($archivo !== '' && file_exists($ruta) && pathinfo($ruta, PATHINFO_EXTENSION) === 'gz') {
            unlink($ruta);
            $mensajeExito = "Backup eliminado: $archivo";
        } else {
            $mensajeError = "Archivo no válido.";
        }
    }
}

// ============================================================
// DESCARGAR UN BACKUP
// ============================================================
if (($_GET['accion'] ?? '') === 'descargar') {
    $archivo = basename($_GET['archivo'] ?? '');
    $ruta = $BACKUP_DIR . '/' . $archivo;

    if ($archivo !== '' && file_exists($ruta) && pathinfo($ruta, PATHINFO_EXTENSION) === 'gz') {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
    http_response_code(404);
    exit('Archivo no encontrado');
}

// ============================================================
// LISTAR BACKUPS
// ============================================================
$backups = [];
if (is_dir($BACKUP_DIR)) {
    foreach (glob($BACKUP_DIR . '/' . $DB_NAME . '_*.sql.gz') as $archivo) {
        $stat = stat($archivo);
        $backups[] = [
            'nombre'    => basename($archivo),
            'ruta'      => $archivo,
            'size'      => $stat['size'],
            'size_kb'   => round($stat['size'] / 1024, 1),
            'fecha'     => $stat['mtime'],
            'tipo'      => preg_match('/_([a-z]+)_\d{4}/', basename($archivo), $m) ? $m[1] : 'unknown',
        ];
    }
    // Más recientes primero
    usort($backups, fn($a, $b) => $b['fecha'] <=> $a['fecha']);
}

$totalBackups = count($backups);
$totalMB = round(array_sum(array_column($backups, 'size')) / 1024 / 1024, 2);
$ultimoBackup = $backups[0] ?? null;
$diasSinBackup = $ultimoBackup ? floor((time() - $ultimoBackup['fecha']) / 86400) : null;

// Espacio libre
$espacioLibreMB = round(disk_free_space($BACKUP_DIR) / 1024 / 1024, 0);

$tituloPagina = 'Backups';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Respaldos de base de datos</h1>
        <p class="text-sm text-gray-500 mt-1">
            Esquema de recuperación · Base: <strong><?= e($DB_NAME) ?></strong>
        </p>
    </div>
    <form method="POST" onsubmit="return confirm('¿Crear un backup ahora mismo? Puede tardar unos segundos.');">
        <input type="hidden" name="accion" value="crear">
        <button class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-800
                       text-white font-semibold px-4 py-2 rounded-lg transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Crear backup ahora
        </button>
    </form>
</div>

<?php if ($mensajeExito): ?>
    <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
        ✓ <?= e($mensajeExito) ?>
    </div>
<?php endif; ?>
<?php if ($mensajeError): ?>
    <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
        <?= e($mensajeError) ?>
    </div>
<?php endif; ?>

<!-- Alerta si no hay backup reciente -->
<?php if ($diasSinBackup === null || $diasSinBackup >= 7): ?>
    <div class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
        <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div>
            <strong class="text-amber-900">
                <?= $diasSinBackup === null
                    ? 'No hay respaldos registrados'
                    : "Último backup hace {$diasSinBackup} días" ?>
            </strong>
            <p class="text-sm text-amber-700 mt-1">
                Se recomienda hacer un backup cada 7 días como mínimo (RPO sugerido).
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Backups</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?= $totalBackups ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Espacio usado</div>
        <div class="text-2xl font-bold text-gray-900 mt-1"><?= $totalMB ?> MB</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-4">
        <div class="text-xs text-emerald-700 uppercase tracking-wide">Espacio libre</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($espacioLibreMB) ?> MB</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-blue-200 p-4">
        <div class="text-xs text-blue-700 uppercase tracking-wide">Último backup</div>
        <div class="text-sm font-bold text-blue-600 mt-1">
            <?= $ultimoBackup ? date('d/m/Y H:i', $ultimoBackup['fecha']) : 'Nunca' ?>
        </div>
    </div>
</div>

<!-- Lista de backups -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
            Respaldos disponibles
        </h2>
    </div>

    <?php if (empty($backups)): ?>
        <div class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7a2 2 0 00-2-2H6a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500 mb-3">Aún no hay respaldos creados.</p>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <button class="inline-block px-4 py-2 text-sm bg-primary-700 hover:bg-primary-800
                               text-white rounded-lg transition">
                    Crear el primer backup
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Archivo</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Tamaño</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($backups as $b): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                <?= e($b['nombre']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                            <?= $b['tipo'] === 'full'   ? 'bg-primary-100 text-primary-700'
                                                : ($b['tipo'] === 'schema' ? 'bg-amber-100 text-amber-700'
                                                                         : 'bg-emerald-100 text-emerald-700') ?>">
                                    <?= e($b['tipo']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= $b['size_kb'] ?> KB</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?= date('d/m/Y H:i:s', $b['fecha']) ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                <a href="?accion=descargar&archivo=<?= urlencode($b['nombre']) ?>"
                                   class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                          bg-primary-700 hover:bg-primary-800 text-white transition">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Descargar
                                </a>
                                <form method="POST" class="inline"
                                      onsubmit="return confirm('¿Eliminar este backup permanentemente?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="archivo" value="<?= e($b['nombre']) ?>">
                                    <button class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded
                                                   border border-red-300 text-red-700 hover:bg-red-50 transition">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Documentación rápida -->
<div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
        Esquema de recuperación
    </h2>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-gray-500">RTO (Recovery Time Objective)</dt>
            <dd class="text-gray-900 font-medium mt-0.5">≤ 30 minutos</dd>
        </div>
        <div>
            <dt class="text-gray-500">RPO (Recovery Point Objective)</dt>
            <dd class="text-gray-900 font-medium mt-0.5">≤ 7 días (backups manuales)</dd>
        </div>
        <div>
            <dt class="text-gray-500">Frecuencia recomendada</dt>
            <dd class="text-gray-900 font-medium mt-0.5">Diaria o semanal</dd>
        </div>
        <div>
            <dt class="text-gray-500">Retención automática</dt>
            <dd class="text-gray-900 font-medium mt-0.5">30 días</dd>
        </div>
    </dl>

    <div class="mt-4 p-3 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-600 font-mono">
        <div class="text-gray-500 mb-1"># Crear backup manual desde terminal:</div>
        <div>bash sql/06_backup.sh full</div>
        <div class="text-gray-500 mt-2 mb-1"># Restaurar desde terminal:</div>
        <div>bash sql/07_restore.sh backups/vidaplus_full_2026-11-16_14-30-00.sql.gz</div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>