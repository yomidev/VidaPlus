<?php
// modules/portal/mis_direcciones.php
// CRUD de direcciones del cliente logueado.
// Solo puede ver/editar/eliminar SUS propias direcciones.

require_once __DIR__ . '/../../includes/auth.php';

// Solo clientes
if ($_SESSION['tipo'] !== 'cliente') {
    flash('error', 'Esta sección es exclusiva del portal de clientes.');
    redirect('/index.php');
}

$pdo = pdoActual();
$idCliente = (int)$_SESSION['usuario_id'];

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'crear'    => accionCrear(),
    'editar'   => accionEditar(),
    'eliminar' => accionEliminar(),
    'default'  => accionHacerDefault(),
    default    => accionListar(),
};

// ============================================================
// LISTAR
// ============================================================
function accionListar(): void
{
    global $pdo, $idCliente;

    $stmt = $pdo->prepare("
        SELECT d.*, p.nombre AS pais
        FROM direcciones_cliente d
        INNER JOIN paises p ON d.id_pais = p.id_pais
        WHERE d.id_cliente = ? AND d.activa = 1
        ORDER BY d.es_predeterminada DESC, d.fecha_creacion DESC
    ");
    $stmt->execute([$idCliente]);
    $direcciones = $stmt->fetchAll();

    $tituloPagina = 'Mis direcciones';
    require __DIR__ . '/../../includes/header.php';
    ?>

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <span class="text-gray-900 font-medium">Mis direcciones</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mis direcciones de envío</h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= count($direcciones) ?> dirección<?= count($direcciones) !== 1 ? 'es' : '' ?>
                registrada<?= count($direcciones) !== 1 ? 's' : '' ?>
            </p>
        </div>
        <a href="/modules/portal/mis_direcciones.php?accion=crear"
           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white
                  font-semibold px-4 py-2 rounded-lg transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nueva dirección
        </a>
    </div>

    <?php if ($msg = flash('exito')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-green-50 border border-green-200 text-green-700 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($direcciones)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1">Sin direcciones registradas</h3>
            <p class="text-gray-500 mb-4">
                Necesitas al menos una dirección para poder comprar en línea.
            </p>
            <a href="/modules/portal/mis_direcciones.php?accion=crear"
               class="inline-block px-4 py-2 text-sm bg-green-600 hover:bg-green-700
                      text-white rounded-lg transition">
                + Agregar mi primera dirección
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($direcciones as $d): ?>
                <div class="bg-white rounded-xl shadow-sm border
                            <?= $d['es_predeterminada'] ? 'border-primary-300 ring-1 ring-primary-200' : 'border-gray-100' ?> p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg bg-primary-50 text-primary-600
                                        flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                            </div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900"><?= e($d['alias']) ?></h3>
                                <?php if ($d['es_predeterminada']): ?>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider
                                                 bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full">
                                        Predeterminada
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-sm text-gray-600 space-y-1 mb-4">
                        <p><?= e($d['calle']) ?></p>
                        <?php if ($d['colonia']): ?>
                            <p class="text-gray-500">Col. <?= e($d['colonia']) ?></p>
                        <?php endif; ?>
                        <p><?= e($d['ciudad'] . ', ' . $d['estado_provincia']) ?> · CP <?= e($d['codigo_postal']) ?></p>
                        <p class="text-gray-500"><?= e($d['pais']) ?></p>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                        <?php if (!$d['es_predeterminada']): ?>
                            <form method="POST"
                                  action="/modules/portal/mis_direcciones.php?accion=default&id=<?= (int)$d['id_direccion'] ?>"
                                  class="inline">
                                <button class="text-xs px-2 py-1 rounded border border-primary-300
                                               text-primary-700 hover:bg-primary-50 transition">
                                    Hacer predeterminada
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="/modules/portal/mis_direcciones.php?accion=editar&id=<?= (int)$d['id_direccion'] ?>"
                           class="text-xs px-2 py-1 rounded border border-amber-300
                                  text-amber-700 hover:bg-amber-50 transition">
                            Editar
                        </a>
                        <a href="/modules/portal/mis_direcciones.php?accion=eliminar&id=<?= (int)$d['id_direccion'] ?>"
                           class="text-xs px-2 py-1 rounded border border-red-300
                                  text-red-700 hover:bg-red-50 transition">
                            Eliminar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../../includes/footer.php'; ?>
    <?php
}

// ============================================================
// CREAR
// ============================================================
function accionCrear(): void
{
    global $pdo, $idCliente;

    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();
    $errores = [];
    $datos = [
        'alias' => '', 'calle' => '', 'colonia' => '', 'ciudad' => '',
        'estado_provincia' => '', 'codigo_postal' => '', 'id_pais' => '',
        'es_predeterminada' => '0',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($datos as $k => $_) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarDireccion($datos);

        if (empty($errores)) {
            try {
                $pdo->beginTransaction();

                if ((int)$datos['es_predeterminada'] === 1) {
                    $pdo->prepare("UPDATE direcciones_cliente SET es_predeterminada = 0 WHERE id_cliente = ?")
                        ->execute([$idCliente]);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO direcciones_cliente
                        (id_cliente, alias, calle, colonia, ciudad, estado_provincia,
                         codigo_postal, id_pais, es_predeterminada, activa)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $idCliente,
                    $datos['alias'], $datos['calle'], $datos['colonia'] ?: null,
                    $datos['ciudad'], $datos['estado_provincia'], $datos['codigo_postal'],
                    (int)$datos['id_pais'], (int)$datos['es_predeterminada'],
                ]);

                $pdo->commit();
                flash('exito', 'Dirección agregada correctamente.');
                redirect('/modules/portal/mis_direcciones.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }
        }
    }

    $tituloPagina = 'Nueva dirección';
    $modo = 'crear';
    require __DIR__ . '/../../includes/header.php';
    require __DIR__ . '/../../includes/forms/direcciones_portal_form.php';
    require __DIR__ . '/../../includes/footer.php';
}

// ============================================================
// EDITAR
// ============================================================
function accionEditar(): void
{
    global $pdo, $idCliente;

    $id = (int)($_GET['id'] ?? 0);
    $direccion = null;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM direcciones_cliente WHERE id_direccion = ? AND id_cliente = ?");
        $stmt->execute([$id, $idCliente]);
        $direccion = $stmt->fetch();
    }

    if (!$direccion) {
        flash('error', 'Dirección no encontrada.');
        redirect('/modules/portal/mis_direcciones.php');
    }

    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();
    $errores = [];
    $datos = $direccion;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['alias','calle','colonia','ciudad','estado_provincia','codigo_postal','id_pais','es_predeterminada'] as $k) {
            $datos[$k] = trim($_POST[$k] ?? '');
        }
        $errores = validarDireccion($datos);

        if (empty($errores)) {
            try {
                $pdo->beginTransaction();

                if ((int)$datos['es_predeterminada'] === 1 && !$direccion['es_predeterminada']) {
                    $pdo->prepare("UPDATE direcciones_cliente SET es_predeterminada = 0 
                                   WHERE id_cliente = ? AND id_direccion != ?")
                        ->execute([$idCliente, $id]);
                }

                $pdo->prepare("
                    UPDATE direcciones_cliente SET
                        alias = ?, calle = ?, colonia = ?, ciudad = ?,
                        estado_provincia = ?, codigo_postal = ?, id_pais = ?,
                        es_predeterminada = ?
                    WHERE id_direccion = ? AND id_cliente = ?
                ")->execute([
                    $datos['alias'], $datos['calle'], $datos['colonia'] ?: null,
                    $datos['ciudad'], $datos['estado_provincia'], $datos['codigo_postal'],
                    (int)$datos['id_pais'], (int)$datos['es_predeterminada'],
                    $id, $idCliente,
                ]);

                $pdo->commit();
                flash('exito', 'Dirección actualizada.');
                redirect('/modules/portal/mis_direcciones.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errores[] = 'Error al actualizar: ' . $ex->getMessage();
            }
        }
    }

    $tituloPagina = 'Editar dirección';
    $modo = 'editar';
    require __DIR__ . '/../../includes/header.php';
    require __DIR__ . '/../../includes/forms/direcciones_portal_form.php';
    require __DIR__ . '/../../includes/footer.php';
}

// ============================================================
// ELIMINAR
// ============================================================
function accionEliminar(): void
{
    global $pdo, $idCliente;

    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM direcciones_cliente WHERE id_direccion = ? AND id_cliente = ?");
    $stmt->execute([$id, $idCliente]);
    $direccion = $stmt->fetch();

    if (!$direccion) {
        flash('error', 'Dirección no encontrada.');
        redirect('/modules/portal/mis_direcciones.php');
    }

    // ¿Tiene pedidos asociados?
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_direccion_envio = ?");
    $stmt->execute([$id]);
    $tieneVentas = (int)$stmt->fetchColumn() > 0;

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($tieneVentas) {
                $pdo->prepare("UPDATE direcciones_cliente SET activa = 0, es_predeterminada = 0 
                               WHERE id_direccion = ?")->execute([$id]);
                flash('exito', 'La dirección tiene pedidos asociados. Se desactivó en lugar de eliminarse.');
            } else {
                $pdo->prepare("DELETE FROM direcciones_cliente WHERE id_direccion = ? AND id_cliente = ?")
                    ->execute([$id, $idCliente]);
                flash('exito', 'Dirección eliminada.');
            }
            redirect('/modules/portal/mis_direcciones.php');
        } catch (PDOException $ex) {
            $errores[] = 'Error al eliminar: ' . $ex->getMessage();
        }
    }

    $tituloPagina = 'Eliminar dirección';
    require __DIR__ . '/../../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/portal/mis_direcciones.php" class="hover:text-primary-600">Mis direcciones</a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Eliminar</span>
        </nav>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        <?= $tieneVentas ? 'Desactivar dirección' : 'Eliminar dirección' ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $tieneVentas
                            ? 'Esta dirección está asociada a pedidos y no puede eliminarse.'
                            : 'Esta acción eliminará la dirección permanentemente.' ?>
                    </p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Alias:</span>
                    <strong class="text-gray-900"><?= e($direccion['alias']) ?></strong>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Dirección:</span>
                    <span class="text-gray-700"><?= e($direccion['calle']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Ciudad:</span>
                    <span class="text-gray-700"><?= e($direccion['ciudad'] . ', ' . $direccion['estado_provincia']) ?></span>
                </div>
            </div>

            <?php if ($tieneVentas): ?>
                <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                    Esta dirección tiene <strong>pedidos asociados</strong>.
                    Se marcará como inactiva para conservar el historial.
                </div>
            <?php endif; ?>

            <?php foreach ($errores as $err): ?>
                <div class="mb-3 px-3 py-2 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/portal/mis_direcciones.php"
                   class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700
                          hover:bg-gray-50 transition text-center">
                    Cancelar
                </a>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2
                               rounded-lg transition shadow-sm">
                    <?= $tieneVentas ? 'Sí, desactivar' : 'Sí, eliminar' ?>
                </button>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/../../includes/footer.php';
}

// ============================================================
// HACER PREDETERMINADA
// ============================================================
function accionHacerDefault(): void
{
    global $pdo, $idCliente;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/modules/portal/mis_direcciones.php');
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'ID inválido.');
        redirect('/modules/portal/mis_direcciones.php');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE direcciones_cliente SET es_predeterminada = 0 WHERE id_cliente = ?")
            ->execute([$idCliente]);
        $pdo->prepare("UPDATE direcciones_cliente SET es_predeterminada = 1 
                       WHERE id_direccion = ? AND id_cliente = ?")
            ->execute([$id, $idCliente]);
        $pdo->commit();
        flash('exito', 'Dirección marcada como predeterminada.');
    } catch (PDOException $ex) {
        $pdo->rollBack();
        flash('error', 'No se pudo actualizar la dirección predeterminada.');
    }

    redirect('/modules/portal/mis_direcciones.php');
}

// ============================================================
// Validación
// ============================================================
function validarDireccion(array $datos): array
{
    $errores = [];
    if ($datos['alias'] === '')            $errores[] = 'El alias es obligatorio (Ej. "Casa", "Oficina").';
    if ($datos['calle'] === '')            $errores[] = 'La calle es obligatoria.';
    if ($datos['ciudad'] === '')           $errores[] = 'La ciudad es obligatoria.';
    if ($datos['estado_provincia'] === '') $errores[] = 'El estado o provincia es obligatorio.';
    if ($datos['codigo_postal'] === '')    $errores[] = 'El código postal es obligatorio.';
    if ((int)$datos['id_pais'] <= 0)       $errores[] = 'Selecciona un país.';
    return $errores;
}