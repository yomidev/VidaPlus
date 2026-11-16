<?php
// index.php — Entrada del proyecto
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/helpers.php';

if (!estaLogueado()) {
    redirect('/modules/auth/login.php');
}

require __DIR__ . '/modules/dashboard.php';