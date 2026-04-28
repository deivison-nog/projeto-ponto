<?php
date_default_timezone_set('America/Sao_Paulo');
require 'conexao.php';

// Lê os dados JSON recebidos
$input = json_decode(file_get_contents('php://input'), true);

// Aceita tanto "dados" (QR) quanto "matricula" (campo manual)
$matricula = $input['matricula'] ?? $input['dados'] ?? null;

if (!$matricula) {
    http_response_code(400);
    echo "matricula_nao_recebida";
    exit;
}

// Busca o funcionário pela matrícula
$stmt = $pdo->prepare("SELECT id, nome, situacao FROM funcionarios WHERE matricula = ?");
$stmt->execute([$matricula]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    // Para integração com ponto.php, retorne exatamente esta string:
    echo "funcionario_nao_encontrado";
    exit;
}

// Bloqueia registro para funcionário inativo
if ($funcionario['situacao'] === 'Inativo') {
    // Para integração com ponto.php, retorne exatamente esta string:
    echo "funcionario_inativo";
    exit;
}

// Verifica o último registro para alternar entre entrada e saída
$stmt = $pdo->prepare("SELECT tipo FROM registro_ponto WHERE funcionario_id = ? ORDER BY data_hora DESC LIMIT 1");
$stmt->execute([$funcionario['id']]);
$ultimo = $stmt->fetch();

$novoTipo = ($ultimo && $ultimo['tipo'] === 'entrada') ? 'saida' : 'entrada';

// Insere novo registro (data_hora é preenchido automaticamente pelo banco)
$stmt = $pdo->prepare("INSERT INTO registro_ponto (funcionario_id, tipo) VALUES (?, ?)");
$stmt->execute([$funcionario['id'], $novoTipo]);

$dataHora = date('d/m/Y H:i:s');
$tipoLabel = $novoTipo === 'entrada'
    ? "<span class='text-success fw-bold'>Entrada registrada</span>"
    : "<span class='text-primary fw-bold'>Saída registrada</span>";

echo "$tipoLabel<br><b>" . htmlspecialchars($funcionario['nome']) . "</b><br>Horário: <strong>$dataHora</strong>";
?>
