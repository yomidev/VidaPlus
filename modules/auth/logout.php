<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /modules/auth/login.php');
exit;