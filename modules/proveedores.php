<?php
// modules/proveedores.php
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'farmaceutico');

$accion = $_GET['accion'] ?? 'listar';

match($accion) {
    'crear'    => accionCrear(),
    'editar'   => accionEditar(),
    'eliminar' => accionEliminar(),
    default    => accionListar(),
};

function accionListar(): void
{
    $pdo = pdoActual();
    $busqueda = trim($_GET['q'] ?? '');

    $where  = [];
    $params = [];
    if ($busqueda !== '') {
        $where[] = "(p.nombre LIKE ? OR p.contacto LIKE ? OR p.email LIKE ?)";
        array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%");
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT p.*, pa.nombre AS pais,
               (SELECT COUNT(*) FROM compras c WHERE c.id_proveedor = p.id_proveedor) AS num_compras,
               (SELECT COALESCE(SUM(total),0) FROM compras c WHERE c.id_proveedor = p.id_proveedor AND c.estado='recibida') AS total_comprado
        FROM proveedores p
        INNER JOIN paises pa ON p.id_pais = pa.id_pais
        $whereSql
        ORDER BY p.nombre
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $tituloPagina = 'Proveedores';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Proveedores</h1>
            <p class="text-sm text-gray-500 mt-1"><?= count($rows) ?> proveedor<?= count($rows) !== 1 ? 'es' : '' ?> registrado<?= count($rows) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/modules/proveedores.php?accion=crear"
           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo proveedor
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
            <input type="text" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por nombre, contacto o email..."
                   class="flex-1 px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary-500 outline-none transition">
            <button class="bg-primary-700 hover:bg-primary-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">Buscar</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Proveedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Contacto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">País</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Compras</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sin proveedores registrados</td></tr>
                <?php else: foreach ($rows as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?= e($p['nombre']) ?></div>
                            <div class="text-xs text-gray-400"><?= e($p['email']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-700"><?= e($p['contacto']) ?></div>
                            <div class="text-xs text-gray-500"><?= e($p['telefono']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= e($p['pais']) ?></td>
                        <td class="px-4 py-3 text-sm text-right"><?= (int)$p['num_compras'] ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-emerald-600">$<?= number_format((float)$p['total_comprado'], 2) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                            <a href="/modules/proveedores.php?accion=editar&id=<?= (int)$p['id_proveedor'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-amber-300 text-amber-700 hover:bg-amber-50 transition">Editar</a>
                            <a href="/modules/proveedores.php?accion=eliminar&id=<?= (int)$p['id_proveedor'] ?>"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50 transition">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
}

function accionCrear(): void
{
    $pdo = pdoActual();
    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();
    $errores = [];
    $datos = ['nombre' => '', 'id_pais' => '', 'contacto' => '', 'telefono' => '', 'email' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($datos as $k => $_) $datos[$k] = trim($_POST[$k] ?? '');
        $errores = validarProveedor($datos, $pdo, null);

        if (empty($errores)) {
            try {
                $pdo->prepare("
                    INSERT INTO proveedores (nombre, id_pais, contacto, telefono, email)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $datos['nombre'], (int)$datos['id_pais'], $datos['contacto'],
                    $datos['telefono'], $datos['email'] ?: null,
                ]);
                flash('exito', 'Proveedor creado correctamente.');
                redirect('/modules/proveedores.php');
            } catch (PDOException $ex) {
                $errores[] = 'Error: ' . $ex->getMessage();
            }
        }
    }

    $tituloPagina = 'Nuevo proveedor';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-3xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/proveedores.php" class="hover:text-primary-600">Proveedores</a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Nuevo</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 mb-5">Nuevo proveedor</h1>

        <?php if (!empty($errores)): ?>
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre / Razón social <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required value="<?= e($datos['nombre']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">País <span class="text-red-500">*</span></label>
                    <select name="id_pais" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= (int)$p['id_pais'] ?>" <?= (int)$datos['id_pais'] === (int)$p['id_pais'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contacto <span class="text-red-500">*</span></label>
                    <input type="text" name="contacto" required value="<?= e($datos['contacto']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono <span class="text-red-500">*</span></label>
                    <input type="tel" name="telefono" required value="<?= e($datos['telefono']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= e($datos['email']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2 pt-5 border-t border-gray-100">
                <a href="/modules/proveedores.php" class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Cancelar</a>
                <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold px-5 py-2 rounded-lg transition shadow-sm">Crear proveedor</button>
            </div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
}

function accionEditar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
    $stmt->execute([$id]);
    $prov = $stmt->fetch();
    if (!$prov) { flash('error', 'Proveedor no encontrado.'); redirect('/modules/proveedores.php'); }

    $paises = $pdo->query("SELECT id_pais, nombre FROM paises WHERE activo=1 ORDER BY nombre")->fetchAll();
    $errores = [];
    $datos = $prov;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['nombre','id_pais','contacto','telefono','email'] as $k) $datos[$k] = trim($_POST[$k] ?? '');
        $errores = validarProveedor($datos, $pdo, $id);

        if (empty($errores)) {
            try {
                $pdo->prepare("
                    UPDATE proveedores SET nombre = ?, id_pais = ?, contacto = ?, telefono = ?, email = ?
                    WHERE id_proveedor = ?
                ")->execute([
                    $datos['nombre'], (int)$datos['id_pais'], $datos['contacto'],
                    $datos['telefono'], $datos['email'] ?: null, $id,
                ]);
                flash('exito', 'Proveedor actualizado.');
                redirect('/modules/proveedores.php');
            } catch (PDOException $ex) {
                $errores[] = 'Error: ' . $ex->getMessage();
            }
        }
    }

    $tituloPagina = 'Editar proveedor';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-3xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/proveedores.php" class="hover:text-primary-600">Proveedores</a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Editar</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 mb-5">Editar proveedor</h1>

        <?php if (!empty($errores)): ?>
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
                <ul class="list-disc list-inside text-sm text-red-700"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre / Razón social</label>
                    <input type="text" name="nombre" required value="<?= e($datos['nombre']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                    <select name="id_pais" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= (int)$p['id_pais'] ?>" <?= (int)$datos['id_pais'] === (int)$p['id_pais'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contacto</label>
                    <input type="text" name="contacto" required value="<?= e($datos['contacto']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="tel" name="telefono" required value="<?= e($datos['telefono']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= e($datos['email']) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2 pt-5 border-t border-gray-100">
                <a href="/modules/proveedores.php" class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Cancelar</a>
                <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold px-5 py-2 rounded-lg transition shadow-sm">Guardar cambios</button>
            </div>
        </form>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
}

function accionEliminar(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $pdo = pdoActual();
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
    $stmt->execute([$id]);
    $prov = $stmt->fetch();
    if (!$prov) { flash('error', 'Proveedor no encontrado.'); redirect('/modules/proveedores.php'); }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM compras WHERE id_proveedor = ?");
    $stmt->execute([$id]);
    $numCompras = (int)$stmt->fetchColumn();
    $tieneCompras = $numCompras > 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($tieneCompras) {
                flash('error', 'No se puede eliminar: tiene ' . $numCompras . ' compras asociadas.');
            } else {
                $pdo->prepare("DELETE FROM proveedores WHERE id_proveedor = ?")->execute([$id]);
                flash('exito', 'Proveedor eliminado.');
            }
            redirect('/modules/proveedores.php');
        } catch (PDOException $ex) {
            flash('error', 'Error al eliminar: ' . $ex->getMessage());
            redirect('/modules/proveedores.php');
        }
    }

    $tituloPagina = 'Eliminar proveedor';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="max-w-2xl mx-auto">
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/modules/proveedores.php" class="hover:text-primary-600">Proveedores</a>
            <span>›</span>
            <span class="text-gray-900 font-medium">Eliminar</span>
        </nav>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Eliminar proveedor</h1>
                    <p class="text-sm text-gray-500 mt-1">¿Seguro que quieres eliminar <strong><?= e($prov['nombre']) ?></strong>?</p>
                </div>
            </div>
            <?php if ($tieneCompras): ?>
                <div class="mb-4 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                    <strong>Este proveedor tiene <?= $numCompras ?> compra<?= $numCompras !== 1 ? 's' : '' ?> registrada<?= $numCompras !== 1 ? 's' : '' ?>.</strong>
                    <p class="mt-1">No se puede eliminar. Considera desactivarlo en su lugar.</p>
                </div>
            <?php endif; ?>
            <form method="POST" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <a href="/modules/proveedores.php" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">Cancelar</a>
                <button type="submit" <?= $tieneCompras ? 'disabled' : '' ?>
                        class="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg transition">Sí, eliminar</button>
            </form>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
}

function validarProveedor(array $datos, PDO $pdo, ?int $idActual): array
{
    $errores = [];
    if ($datos['nombre'] === '')    $errores[] = 'El nombre es obligatorio.';
    if ($datos['contacto'] === '')  $errores[] = 'El contacto es obligatorio.';
    if ($datos['telefono'] === '')  $errores[] = 'El teléfono es obligatorio.';
    if ((int)$datos['id_pais'] <= 0) $errores[] = 'Selecciona un país.';

    if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido.';
    }

    // Verificar duplicados
    $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE nombre = ? AND id_proveedor != ?");
    $stmt->execute([$datos['nombre'], $idActual ?? 0]);
    if ($stmt->fetch()) $errores[] = 'Ya existe un proveedor con ese nombre.';

    if ($datos['email'] !== '') {
        $stmt = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE email = ? AND id_proveedor != ?");
        $stmt->execute([$datos['email'], $idActual ?? 0]);
        if ($stmt->fetch()) $errores[] = 'Ya existe un proveedor con ese email.';
    }

    return $errores;
}