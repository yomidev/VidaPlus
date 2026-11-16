<?php
    $pdo = new PDO('mysql:host=localhost;dbname=vidaplus;charset=utf8mb4', 'root', 'root123');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $usuarios = [
        [1, 'admin123'],
        [2, 'farma123'],
        [3, 'farma123'],
        [4, 'caja123'],
        [5, 'caja123'],
        [6, 'audit123'],
    ];

    foreach ($usuarios as [$id, $pwd]) {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?');
        $stmt->execute([$hash, $id]);
    }

    $clientes = [
        [1, 'cliente123'], [2, 'cliente123'], [3, 'cliente123'],
        [4, 'cliente123'], [5, 'cliente123'], [6, 'cliente123'],
        [7, 'cliente123'], [8, 'cliente123'], [9, 'cliente123'],
        [10, 'cliente123'],
    ];

    foreach ($clientes as [$id, $pwd]) {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE clientes SET password_hash = ? WHERE id_cliente = ?');
        $stmt->execute([$hash, $id]);
    }

    echo "Contraseñas actualizadas correctamente\n";
?>