<?php
// includes/helpers.php

/** Escapa texto para HTML */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/** Redirige y corta ejecución */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/** Flash message: set o get */
function flash(string $key, ?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

/** ¿Hay sesión activa? */
function estaLogueado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['tipo']);
}

/** ¿El usuario actual tiene alguno de estos roles? */
function esRol(string ...$roles): bool
{
    if (!estaLogueado()) return false;
    if ($_SESSION['tipo'] === 'cliente') {
        return in_array('cliente', $roles, true);
    }
    return in_array($_SESSION['rol_nombre'] ?? '', $roles, true);
}

/** Exige uno de estos roles o corta con 403 */
function requiereRol(string ...$roles): void
{
    if (!esRol(...$roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem">'
          . '<h2>403 — Acceso denegado</h2>'
          . '<p>No tienes permisos para esta sección.</p>'
          . '<a href="/index.php">Volver al inicio</a></div>');
    }
}