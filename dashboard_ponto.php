<?php
require 'conexao.php';

// Filtros do formulário
$nome = $_GET['nome'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar funcionários para o filtro
$funcionarios = $pdo->query("SELECT id, nome FROM funcionarios ORDER BY nome")->fetchAll();

// Se buscou por nome, mostrar todas as entradas do funcionário (com filtro de data)
if ($nome) {
    // Busca todas as entradas do funcionário filtrado pelo período
    $sql = "
        SELECT f.nome,
               DATE(rp.data_hora) as dia,
               rp.tipo,
               rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE f.nome LIKE :nome
          AND rp.data_hora BETWEEN :data_inicio AND :data_fim
        ORDER BY rp.data_hora
    ";
    $params = [
        ':nome' => '%' . $nome . '%',
        ':data_inicio' => $data_inicio . ' 00:00:00',
        ':data_fim' => $data_fim . ' 23:59:59'
    ];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $registros = [];
    while ($row = $stmt->fetch()) {
        $registros[$row['nome']][] = [
            'dia' => $row['dia'],
            'tipo' => $row['tipo'],
            'data_hora' => $row['data_hora']
        ];
    }
} else {
    // Aqui, mostrar apenas as últimas entradas e saídas de cada funcionário
    // Subquery para última entrada e saída de cada funcionário
    $sql = "
        SELECT f.nome, rp.tipo, rp.data_hora
        FROM funcionarios f
        LEFT JOIN (
            SELECT t1.*
            FROM registro_ponto t1
            INNER JOIN (
                SELECT funcionario_id, tipo, MAX(data_hora) as max_data_hora
                FROM registro_ponto
                GROUP BY funcionario_id, tipo
            ) t2
            ON t1.funcionario_id = t2.funcionario_id AND t1.tipo = t2.tipo AND t1.data_hora = t2.max_data_hora
        ) rp ON f.id = rp.funcionario_id
        WHERE rp.data_hora IS NOT NULL
        ORDER BY f.nome, rp.tipo DESC, rp.data_hora DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Organiza as últimas entradas e saídas por funcionário
    $registros = [];
    while ($row = $stmt->fetch()) {
        $registros[$row['nome']][] = [
            'tipo' => $row['tipo'],
            'data_hora' => $row['data_hora']
        ];
    }
}

// Função para calcular horas trabalhadas por dia
function calcula_horas($eventos) {
    $entrada = null;
    $total_segundos = 0;
    foreach ($eventos as $evento) {
        if ($evento['tipo'] === 'entrada') {
            $entrada = strtotime($evento['data_hora']);
        } elseif ($evento['tipo'] === 'saida' && $entrada) {
            $saida = strtotime($evento['data_hora']);
            $intervalo = $saida - $entrada;
            if ($intervalo > 0) {
                $total_segundos += $intervalo;
            }
            $entrada = null; // Reset para próxima entrada
        }
    }
    return $total_segundos;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Horas Trabalhadas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      .cabecalho {
        display: flex;
        align-items: center;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 1.5rem;
      }
      @media (max-width: 576px) {
        .cabecalho {
          flex-direction: column;
          align-items: stretch;
          gap: 0.5rem;
        }
      }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
      <div class="cabecalho">
        <h2 class="mb-0">Horas Trabalhadas - Dashboard</h2>
        <a href="index.php" class="btn btn-secondary">Voltar</a>
      </div>
        <form class="row g-3 mb-4" method="get">
            <div class="col-md-4">
                <label for="nome" class="form-label">Funcionário</label>
                <input type="text" class="form-control" name="nome" id="nome" value="<?= htmlspecialchars($nome) ?>" list="nomes" placeholder="Digite o nome">
                <datalist id="nomes">
                    <?php foreach($funcionarios as $f): ?>
                        <option value="<?= htmlspecialchars($f['nome']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="date" class="form-control" name="data_fim" id="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>

        <?php if (empty($registros)): ?>
            <div class="alert alert-warning">Nenhum registro encontrado para o filtro.</div>
        <?php else: ?>
            <?php if ($nome): ?>
                <!-- Listagem de todas as entradas do funcionário filtrado -->
                <?php foreach ($registros as $nome_func => $eventos): ?>
                    <div class="card mb-5 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?= htmlspecialchars($nome_func) ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Tipo</th>
                                            <th>Horário</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eventos as $evento): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($evento['data_hora'])) ?></td>
                                                <td><span class="badge bg-<?= $evento['tipo'] === 'entrada' ? 'success' : 'secondary' ?>"><?= ucfirst($evento['tipo']) ?></span></td>
                                                <td><?= date('H:i:s', strtotime($evento['data_hora'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="gerar_relatorio.php?nome=<?= urlencode($nome) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>" target="_blank" class="btn btn-danger mb-5">Imprimir Relatório</a>
            <?php else: ?>
                <!-- Listagem das últimas entradas e saídas de cada funcionário -->
                <?php foreach ($registros as $nome_func => $eventos): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?= htmlspecialchars($nome_func) ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Data</th>
                                            <th>Horário</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eventos as $evento): ?>
                                            <tr>
                                                <td><span class="badge bg-<?= $evento['tipo'] === 'entrada' ? 'success' : 'secondary' ?>"><?= ucfirst($evento['tipo']) ?></span></td>
                                                <td><?= date('d/m/Y', strtotime($evento['data_hora'])) ?></td>
                                                <td><?= date('H:i:s', strtotime($evento['data_hora'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="gerar_relatorio.php" target="_blank" class="btn btn-danger mb-5">Imprimir Relatório</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
