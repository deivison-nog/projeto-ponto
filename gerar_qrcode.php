<?php
require __DIR__ . '/vendor/autoload.php';
include 'conexao.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

// Verifica se o ID foi enviado via GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID do funcionário não fornecido.');
}

$id = (int)$_GET['id'];

// Consulta a matrícula do funcionário pelo ID
$stmt = $pdo->prepare("SELECT matricula FROM funcionarios WHERE id = ?");
$stmt->execute([$id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    die('Funcionário não encontrado.');
}

// Dados que serão convertidos em QR Code (apenas a matrícula)
$dados = $funcionario['matricula'];

// Gera o QR Code
$result = Builder::create()
    ->data($dados)
    ->encoding(new Encoding('UTF-8'))
    ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
    ->size(300)
    ->margin(10)
    ->build();

// Exibe o QR Code
header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
