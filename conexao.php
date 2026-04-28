<?php
$host = 'srv2036.hstgr.io';
$db   = 'u641927335_pontocolares';
$user = 'u641927335_ponto';
$pass = 'Colares@2025';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}
