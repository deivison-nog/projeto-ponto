<?php
require 'conexao.php';

// Consulta os registros de ponto com JOIN para pegar o nome do funcionário
$sql = "SELECT rp.*, f.nome
        FROM registro_ponto rp
        JOIN funcionarios f ON rp.funcionario_id = f.id
        ORDER BY rp.data_hora DESC";
$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registros de Ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Registros de Ponto</h2>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Data e Hora</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nome']); ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($row['data_hora'])); ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['tipo'] == 'entrada' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($row['tipo']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
