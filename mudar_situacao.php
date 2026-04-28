<?php
// mudar_situacao.php

include 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $situacao = isset($_POST['situacao']) && in_array($_POST['situacao'], ['Ativo', 'Inativo']) ? $_POST['situacao'] : null;

    if ($id > 0 && $situacao) {
        $stmt = $pdo->prepare("UPDATE funcionarios SET situacao = ? WHERE id = ?");
        $stmt->execute([$situacao, $id]);
    }
}

header('Location: gerenciar_funcionarios.php');
exit;