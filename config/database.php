<?php

return [
    'dsn' => 'mysql:host=localhost;dbname=vidaplus;charset=utf8mb4',

    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],

    'users' => [
        'login'        => ['user' => 'app_login',        'pass' => 'Login_VidaPlus#2026!'],
        'admin'        => ['user' => 'app_admin',        'pass' => 'Admin_VidaPlus#2026!'],
        'farmaceutico' => ['user' => 'app_farmaceutico', 'pass' => 'Farma_VidaPlus#2026!'],
        'cajero'       => ['user' => 'app_cajero',       'pass' => 'Caja_VidaPlus#2026!'],
        'auditor'      => ['user' => 'app_auditor',      'pass' => 'Audit_VidaPlus#2026!'],
        'cliente'      => ['user' => 'app_portal',       'pass' => 'Portal_VidaPlus#2026!'],
    ],
];