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

/**
 * Devuelve el id_usuario del sistema que representa al portal web.
 * Se usa para el campo ventas.id_usuario en ventas online.
 */
function idUsuarioPortal(): int
{
    static $id = null;
    if ($id === null) {
        $config = require __DIR__ . '/../config/database.php';
        $id = (int)($config['portal_user_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('portal_user_id no configurado en config/database.php');
        }
    }
    return $id;
}

/**
 * Devuelve el carrito actual como array [id_medicamento => cantidad].
 */
function carrito(): array
{
    return $_SESSION['carrito'] ?? [];
}

/**
 * Cantidad total de items en el carrito (para el badge del menú).
 */
function carritoCantidad(): int
{
    return array_sum(carrito());
}

/**
 * Vacía el carrito.
 */
function carritoVaciar(): void
{
    $_SESSION['carrito'] = [];
}