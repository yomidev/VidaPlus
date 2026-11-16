<?php
// includes/db.php
// Conexión PDO con caché por perfil.

function getPDO(string $perfil = 'login'): PDO
{
    static $pool = [];

    if (isset($pool[$perfil])) {
        return $pool[$perfil];
    }

    $config = require __DIR__ . '/../config/database.php';

    if (!isset($config['users'][$perfil])) {
        throw new RuntimeException("Perfil de conexión no válido: $perfil");
    }

    $creds = $config['users'][$perfil];

    return $pool[$perfil] = new PDO(
        $config['dsn'],
        $creds['user'],
        $creds['pass'],
        $config['options']
    );
}

/**
 * Devuelve la conexión correspondiente al usuario logueado en la app.
 * Si no hay sesión, devuelve la conexión de login (solo lectura).
 */
function pdoActual(): PDO
{
    if (!isset($_SESSION['tipo'])) {
        return getPDO('login');
    }

    if ($_SESSION['tipo'] === 'cliente') {
        return getPDO('cliente');
    }

    return getPDO($_SESSION['rol_nombre'] ?? 'cajero');
}