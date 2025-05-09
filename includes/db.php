<?php
$host = 'localhost';
$db = 'zfgwmhfz_natura';
$user = 'zfgwmhfz_natura';
$pass = '122862asd';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Definir fuso horário da sessão MySQL para Horário de Brasília (UTC-3)
    $pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>