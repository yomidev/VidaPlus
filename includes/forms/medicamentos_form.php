<?php
// includes/medicamentos_form.php
// Requiere: $modo, $datos, $errores, $categorias, $laboratorios, $med (opcional)
$esEditar = $modo === 'editar';
$accionForm = $esEditar
    ? '/modules/medicamentos.php?accion=editar&id=' . (int)$med['id_medicamento']
    : '/modules/medicamentos.php?accion=crear';
?>

<div class="max-w-4xl mx-auto">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/medicamentos.php" class="hover:text-primary-600 transition">Medicamentos</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= $esEditar ? 'Editar' : 'Nuevo' ?></span>
    </nav>

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $esEditar ? 'Editar medicamento' : 'Nuevo medicamento' ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= $esEditar ? 'Actualiza los datos del medicamento.' : 'Registra un nuevo medicamento en el catálogo.' ?>
        </p>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
                    <?php foreach ($errores as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($accionForm) ?>" class="space-y-4">

        <!-- Sección: Identificación -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Identificación
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Código de barras <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="codigo_barras" required
                           placeholder="Ej. 750100000001"
                           value="<?= e($datos['codigo_barras'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">Escanea o escribe el código del producto</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre comercial <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre" required
                           placeholder="Ej. Paracetamol 500mg"
                           value="<?= e($datos['nombre'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>

        <!-- Sección: Clasificación -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Clasificación
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Categoría <span class="text-red-500">*</span>
                    </label>
                    <select name="id_categoria" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id_categoria'] ?>"
                                <?= (int)($datos['id_categoria'] ?? 0) === (int)$c['id_categoria'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Laboratorio <span class="text-red-500">*</span>
                    </label>
                    <select name="id_laboratorio" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($laboratorios as $l): ?>
                            <option value="<?= (int)$l['id_laboratorio'] ?>"
                                <?= (int)($datos['id_laboratorio'] ?? 0) === (int)$l['id_laboratorio'] ? 'selected' : '' ?>>
                                <?= e($l['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Presentación <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="presentacion" required
                           placeholder="Ej. Caja 20 tabletas"
                           value="<?= e($datos['presentacion'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Concentración</label>
                    <input type="text" name="concentracion"
                           placeholder="Ej. 500mg"
                           value="<?= e($datos['concentracion'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>

        <!-- Sección: Precios -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Precios
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Precio de compra <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                        <input type="number" name="precio_compra" id="precio_compra"
                               step="0.01" min="0.01" required oninput="calcMargen()"
                               value="<?= e($datos['precio_compra'] ?? '') ?>"
                               class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Precio de venta <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                        <input type="number" name="precio_venta" id="precio_venta"
                               step="0.01" min="0.01" required oninput="calcMargen()"
                               value="<?= e($datos['precio_venta'] ?? '') ?>"
                               class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    </div>
                </div>
                <div class="md:col-span-2" id="margen-info"></div>
            </div>
        </div>

        <!-- Sección: Inventario -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Inventario
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock actual</label>
                    <input type="number" name="stock" min="0" required
                           value="<?= e($datos['stock'] ?? '0') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock mínimo</label>
                    <input type="number" name="stock_minimo" min="0" required
                           value="<?= e($datos['stock_minimo'] ?? '10') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">Umbral para alertas de reabastecimiento</p>
                </div>
            </div>
        </div>

        <!-- Sección: Detalles adicionales -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Detalles adicionales
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                    <textarea name="descripcion" rows="3"
                              placeholder="Descripción, indicaciones, contraindicaciones..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg resize-y
                                     focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition"><?= e($datos['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <input type="hidden" name="requiere_receta" value="0">
                    <input type="checkbox" name="requiere_receta" value="1" id="chk_receta"
                        <?= !empty($datos['requiere_receta']) ? 'checked' : '' ?>
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <label for="chk_receta" class="cursor-pointer flex-1">
                        <span class="text-sm font-medium text-gray-800">Requiere receta médica</span>
                        <p class="text-xs text-amber-700 mt-0.5">
                            Los medicamentos con receta no se mostrarán en el catálogo en línea.
                        </p>
                    </label>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="/modules/medicamentos.php"
               class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
                Cancelar
            </a>
            <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                <?= $esEditar ? 'Guardar cambios' : 'Crear medicamento' ?>
            </button>
        </div>
    </form>
</div>

<script>
function calcMargen() {
    const pc = parseFloat(document.getElementById('precio_compra').value) || 0;
    const pv = parseFloat(document.getElementById('precio_venta').value) || 0;
    const div = document.getElementById('margen-info');

    if (pc > 0 && pv > 0) {
        if (pv <= pc) {
            div.innerHTML = '<div class="px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">' +
                '⚠️ El precio de venta (${pv.toFixed(2)}) debe ser mayor al de compra (${pc.toFixed(2)}).' +
                '</div>';
        } else {
            const margen = ((pv - pc) / pv * 100).toFixed(1);
            const ganancia = (pv - pc).toFixed(2);
            div.innerHTML = '<div class="px-3 py-2 rounded-lg bg-green-50 border border-green-200 text-sm text-green-700 flex items-center gap-2">' +
                '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' +
                'Margen: <strong>' + margen + '%</strong> · Ganancia por unidad: <strong>$' + ganancia + '</strong>' +
                '</div>';
        }
    } else {
        div.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', calcMargen);
</script>