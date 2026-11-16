<?php
// includes/auth.php — Se incluye al inicio de cada página protegida.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

// Si no hay sesión, redirigir al login
if (!estaLogueado()) {
    redirect('/modules/auth/login.php');
}