<?php
require 'conexao.php';

// Filtros
$filtroNome       = trim($_GET['nome']        ?? '');
$filtroCargo      = trim($_GET['cargo']       ?? '');
$filtroDataInicio = trim($_GET['data_inicio'] ?? '');
$filtroDataFim    = trim($_GET['data_fim']    ?? '');

// Listas para selects/datalists
$listaFuncionarios = $pdo->query("SELECT nome FROM funcionarios ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
$listaCargos       = $pdo->query(
    "SELECT DISTINCT cargo FROM funcionarios WHERE cargo IS NOT NULL AND cargo <> '' ORDER BY cargo"
)->fetchAll(PDO::FETCH_COLUMN);

// Monta query com filtros opcionais
$sql    = "SELECT rp.id, rp.tipo, rp.data_hora, f.nome, f.cargo
           FROM registro_ponto rp
           JOIN funcionarios f ON rp.funcionario_id = f.id
           WHERE 1=1";
$params = [];

if ($filtroNome !== '') {
    $sql .= " AND f.nome LIKE :nome";
    $params[':nome'] = '%' . $filtroNome . '%';
}
if ($filtroCargo !== '') {
    $sql .= " AND f.cargo = :cargo";
    $params[':cargo'] = $filtroCargo;
}
if ($filtroDataInicio !== '') {
    $sql .= " AND rp.data_hora >= :data_inicio";
    $params[':data_inicio'] = $filtroDataInicio . ' 00:00:00';
}
if ($filtroDataFim !== '') {
    $sql .= " AND rp.data_hora <= :data_fim";
    $params[':data_fim'] = $filtroDataFim . ' 23:59:59';
}

$sql .= " ORDER BY rp.data_hora DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Registros de Ponto</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-clipboard-data me-2 text-success"></i>Registros de Ponto</h2>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>

        <!-- Formulário de filtro -->
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-md-3">
                <label class="form-label">Funcionário</label>
                <input type="text" name="nome" class="form-control" list="nomes"
                       value="<?= htmlspecialchars($filtroNome) ?>" placeholder="Digite o nome">
                <datalist id="nomes">
                    <?php foreach ($listaFuncionarios as $n): ?>
                        <option value="<?= htmlspecialchars($n) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cargo</label>
                <select name="cargo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($listaCargos as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $filtroCargo === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Inicial</label>
                <input type="date" name="data_inicio" class="form-control"
                       value="<?= htmlspecialchars($filtroDataInicio) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Final</label>
                <input type="date" name="data_fim" class="form-control"
                       value="<?= htmlspecialchars($filtroDataFim) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="registros.php" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <?php if (empty($registros)): ?>
                    <div class="p-4 text-muted text-center">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Nenhum registro encontrado para os filtros selecionados.
                    </div>
                <?php else: ?>
                <p class="text-muted mb-0 px-3 pt-3">
                    <?= count($registros) ?> registro(s) encontrado(s)
                    <?= count($registros) === 500 ? ' (limite de 500 exibido — use filtros para refinar)' : '' ?>
                </p>
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nome']) ?></td>
                                <td><?= htmlspecialchars($row['cargo'] ?? '') ?></td>
                                <td><?= date('d/m/Y', strtotime($row['data_hora'])) ?></td>
                                <td><?= date('H:i:s', strtotime($row['data_hora'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['tipo'] === 'entrada' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($row['tipo']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
