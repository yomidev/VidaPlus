<?php
// includes/usuarios_form.php
// Requiere: $modo, $datos, $errores, $roles, $esUsuarioActual, $usuario (opcional)
$esEditar = $modo === 'editar';
$accionForm = $esEditar
    ? '/modules/usuarios.php?accion=editar&id=' . (int)$usuario['id_usuario']
    : '/modules/usuarios.php?accion=crear';
?>

<div class="max-w-3xl mx-auto">

    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="/modules/usuarios.php" class="hover:text-primary-600">Usuarios</a>
        <span>›</span>
        <span class="text-gray-900 font-medium"><?= $esEditar ? 'Editar' : 'Nuevo' ?></span>
    </nav>

    <div class="mb-5 flex items-center gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <?= $esEditar ? 'Editar usuario' : 'Nuevo usuario' ?>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <?= $esEditar ? 'Actualiza los datos del empleado.' : 'Registra un nuevo empleado en el sistema.' ?>
            </p>
        </div>
        <?php if ($esEditar && $esUsuarioActual): ?>
            <span class="text-xs font-semibold uppercase tracking-wider bg-primary-100 text-primary-700 px-2 py-1 rounded">
                Tu cuenta
            </span>
        <?php endif; ?>
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

        <!-- Datos personales -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Datos personales
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nombre_completo" required
                           value="<?= e($datos['nombre_completo'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" required
                           value="<?= e($datos['email'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>

        <!-- Credenciales -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                Credenciales de acceso
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="username" required
                           pattern="[a-zA-Z0-9_.]{3,50}"
                           title="3-50 caracteres: letras, números, punto y guion bajo"
                           value="<?= e($datos['username'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                    <p class="text-xs text-gray-400 mt-1">Solo letras, números, punto y guion bajo</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Rol <span class="text-red-500">*</span>
                    </label>
                    <select name="id_rol" required
                            <?= ($esEditar && $esUsuarioActual) ? 'disabled' : '' ?>
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                   focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition
                                   disabled:bg-gray-100 disabled:cursor-not-allowed">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id_rol'] ?>"
                                <?= (int)($datos['id_rol'] ?? 0) === (int)$r['id_rol'] ? 'selected' : '' ?>>
                                <?= e($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($esEditar && $esUsuarioActual): ?>
                        <p class="text-xs text-amber-600 mt-1">No puedes cambiar tu propio rol</p>
                        <input type="hidden" name="id_rol" value="<?= (int)$datos['id_rol'] ?>">
                    <?php endif; ?>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= $esEditar ? 'Nueva contraseña' : 'Contraseña' ?>
                        <?= $esEditar ? '' : '<span class="text-red-500">*</span>' ?>
                    </label>
                    <input type="password" name="password"
                           <?= $esEditar ? '' : 'required' ?>
                           minlength="6"
                           placeholder="<?= $esEditar ? 'Dejar vacío para conservar la actual' : 'Mínimo 6 caracteres' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Confirmar contraseña
                    </label>
                    <input type="password" name="password_confirm"
                           <?= $esEditar ? '' : 'required' ?>
                           minlength="6"
                           placeholder="Repite la contraseña"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition">
                </div>
            </div>
        </div>

        <!-- Estado -->
        <?php if ($esEditar): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                    Estado de la cuenta
                </h2>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-200">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="chk_activo"
                        <?= !empty($datos['activo']) ? 'checked' : '' ?>
                        <?= $esUsuarioActual ? 'disabled' : '' ?>
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    <label for="chk_activo" class="cursor-pointer flex-1 <?= $esUsuarioActual ? 'opacity-60' : '' ?>">
                        <span class="text-sm font-medium text-gray-800">Cuenta activa</span>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <?php if ($esUsuarioActual): ?>
                                No puedes desactivar tu propia cuenta.
                            <?php else: ?>
                                Los usuarios inactivos no pueden iniciar sesión.
                            <?php endif; ?>
                        </p>
                    </label>
                    <?php if ($esUsuarioActual): ?>
                        <input type="hidden" name="activo" value="1">
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="/modules/usuarios.php"
               class="px-5 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition text-center">
                Cancelar
            </a>
            <button class="bg-primary-700 hover:bg-primary-800 text-white font-semibold
                           px-5 py-2 rounded-lg transition shadow-sm">
                <?= $esEditar ? 'Guardar cambios' : 'Crear usuario' ?>
            </button>
        </div>
    </form>
</div>