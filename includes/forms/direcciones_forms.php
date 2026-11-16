<?php
// includes/direcciones_form.php
// Requiere: $modo, $datos, $errores, $paises, $cliente, $direccion (opcional)
$esEditar = $modo === 'editar';
$accionForm = $esEditar
    ? '/modules/direcciones.php?accion=editar&id=' . (int)$direccion['id_direccion']
    : '/modules/direcciones.php?accion=crear&id_cliente=' . (int)$cliente['id_cliente'];
?>

<div class="max-w-3xl mx-auto">

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/clientes.php" class="hover:text-primary-600">Clientes</a>
        <span>›</span>
        <a href="/modules/clientes.php?accion=ver&id=<?= (int)$cliente['id_cliente'] ?>"
           class="hover:text-primary-600">
            <?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
        </a>
        <span>›</span>
        <a href="/modules/direcciones.php?id_cliente=<?= (int)$cliente['id_cliente'] ?>"
           class="hover:text-primary-600">Direcciones</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= $esEditar ? 'Editar' : 'Nueva' ?></span>
    </nav>

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $esEditar ? 'Editar dirección' : 'Nueva dirección' ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            Para <?= e($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
        </p>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
            <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                <?php foreach ($errores as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($accionForm) ?>" class="space-y-4">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Alias <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="alias" required maxlength="30"
                           placeholder='Ej. "Casa", "Oficina", "Casa de mamá"'
                           value="<?= e($datos['alias'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">Nombre corto para identificar la dirección</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Calle y número <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="calle" required maxlength="200"
                           placeholder="Ej. Av. Reforma 123, Int. 4B"
                           value="<?= e($datos['calle'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Colonia</label>
                    <input type="text" name="colonia" maxlength="100"
                           value="<?= e($datos['colonia'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Código postal <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="codigo_postal" required maxlength="10"
                           placeholder="Ej. 06600"
                           value="<?= e($datos['codigo_postal'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Ciudad <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="ciudad" required maxlength="80"
                           value="<?= e($datos['ciudad'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Estado / Provincia <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="estado_provincia" required maxlength="80"
                           value="<?= e($datos['estado_provincia'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        País <span class="text-red-500">*</span>
                    </label>
                    <select name="id_pais" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= (int)$p['id_pais'] ?>"
                                <?= (int)($datos['id_pais'] ?? 0) === (int)$p['id_pais'] ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-3 p-3 rounded-lg bg-primary-50 border border-primary-200">
                <input type="hidden" name="es_predeterminada" value="0">
                <input type="checkbox" name="es_predeterminada" value="1" id="chk_default"
                    <?= !empty($datos['es_predeterminada']) ? 'checked' : '' ?>
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <label for="chk_default" class="cursor-pointer flex-1">
                    <span class="text-sm font-medium text-gray-900">Dirección predeterminada</span>
                    <p class="text-xs text-gray-600 mt-0.5">
                        Se usará automáticamente al hacer pedidos en línea. Si ya hay otra, se desmarcará.
                    </p>
                </label>
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="/modules/direcciones.php?id_cliente=<?= (int)$cliente['id_cliente'] ?>"
               class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
                Cancelar
            </a>
            <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                <?= $esEditar ? 'Guardar cambios' : 'Agregar dirección' ?>
            </button>
        </div>
    </form>
</div>