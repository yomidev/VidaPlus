<?php
// includes/clientes_form.php
// Requiere: $modo, $datos, $errores, $paises, $cliente (opcional)
$esEditar = $modo === 'editar';
$accionForm = $esEditar
    ? '/modules/clientes.php?accion=editar&id=' . (int)$cliente['id_cliente']
    : '/modules/clientes.php?accion=crear';
$tienePortal = $esEditar && !empty($cliente['password_hash']);
?>

<div class="max-w-4xl mx-auto">

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/clientes.php" class="hover:text-primary-600 transition">Clientes</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= $esEditar ? 'Editar' : 'Nuevo' ?></span>
    </nav>

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">
            <?= $esEditar ? 'Editar cliente' : 'Nuevo cliente' ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= $esEditar ? 'Actualiza los datos del cliente.' : 'Registra un cliente en el sistema.' ?>
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

        <!-- Sección: Datos personales -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Datos personales
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre" required
                           value="<?= e($datos['nombre'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Apellido <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="apellido" required
                           value="<?= e($datos['apellido'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                           placeholder="cliente@ejemplo.com"
                           value="<?= e($datos['email'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">También sirve como login del portal</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="tel" name="telefono"
                           placeholder="5551234567"
                           value="<?= e($datos['telefono'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
                <div>
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
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= e($datos['fecha_nacimiento'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>

        <!-- Sección: Lealtad -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Programa de lealtad
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Puntos acumulados</label>
                    <input type="number" name="puntos_lealtad" min="0"
                           value="<?= e($datos['puntos_lealtad'] ?? '0') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">Se acumulan con cada compra (1 punto por cada $10)</p>
                </div>
            </div>
        </div>

        <!-- Sección: Acceso al portal -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Acceso al portal en línea
            </h2>

            <?php if ($tienePortal): ?>
                <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 flex items-start gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Este cliente ya tiene acceso al portal. Deja la contraseña en blanco para conservarla.</span>
                </div>
            <?php else: ?>
                <div class="mb-4 p-3 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-600">
                    Este cliente aún no tiene acceso al portal. Asigna una contraseña para habilitarlo.
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= $tienePortal ? 'Nueva contraseña' : 'Contraseña' ?>
                    </label>
                    <input type="password" name="password"
                           minlength="6"
                           placeholder="<?= $tienePortal ? 'Dejar vacío para conservar' : 'Mínimo 6 caracteres' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">
                        Opcional. Sin contraseña, el cliente no puede acceder al portal.
                    </p>
                </div>
            </div>
        </div>

        <!-- Sección: Estado -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Estado de la cuenta
            </h2>
            <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-200">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" id="chk_activo"
                    <?= !empty($datos['activo']) || !isset($datos['activo']) ? 'checked' : '' ?>
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <label for="chk_activo" class="cursor-pointer flex-1">
                    <span class="text-sm font-medium text-gray-800">Cliente activo</span>
                    <p class="text-xs text-gray-500 mt-0.5">Los clientes inactivos no aparecen en el listado por defecto</p>
                </label>
            </div>
        </div>

        <!-- Acciones -->
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="/modules/clientes.php"
               class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
                Cancelar
            </a>
            <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                <?= $esEditar ? 'Guardar cambios' : 'Registrar cliente' ?>
            </button>
        </div>
    </form>
</div>