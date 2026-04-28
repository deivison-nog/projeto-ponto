<?php
require 'conexao.php';

// Filtros do formulário
$nome        = trim($_GET['nome']        ?? '');
$data_inicio = trim($_GET['data_inicio'] ?? date('Y-m-01'));
$data_fim    = trim($_GET['data_fim']    ?? date('Y-m-t'));

// Buscar funcionários para o datalist
$listaFuncionarios = $pdo->query("SELECT id, nome FROM funcionarios ORDER BY nome")->fetchAll();

/**
 * Agrupa eventos em pares entrada/saída por dia.
 * Retorna array de ['dia', 'entrada', 'saida'|null, 'segundos'].
 */
function agrupar_pares_dashboard(array $eventos): array {
    $porDia = [];
    foreach ($eventos as $ev) {
        $dia = substr($ev['data_hora'], 0, 10);
        $porDia[$dia][] = $ev;
    }
    ksort($porDia);

    $pares = [];
    foreach ($porDia as $dia => $evs) {
        $entrada = null;
        foreach ($evs as $ev) {
            if ($ev['tipo'] === 'entrada') {
                if ($entrada !== null) {
                    $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => null, 'segundos' => 0];
                }
                $entrada = $ev['data_hora'];
            } elseif ($ev['tipo'] === 'saida' && $entrada !== null) {
                $diff = strtotime($ev['data_hora']) - strtotime($entrada);
                $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => $ev['data_hora'], 'segundos' => max(0, $diff)];
                $entrada = null;
            }
        }
        if ($entrada !== null) {
            $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => null, 'segundos' => 0];
        }
    }
    return $pares;
}

function formatar_segundos_dash(int $seg): string {
    $h = (int)floor($seg / 3600);
    $m = (int)floor(($seg % 3600) / 60);
    return sprintf('%02dh%02dm', $h, $m);
}

if ($nome !== '') {
    // Busca todos os eventos do funcionário filtrado pelo período
    $sql = "
        SELECT f.nome, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE f.nome LIKE :nome
          AND rp.data_hora BETWEEN :data_inicio AND :data_fim
        ORDER BY rp.data_hora
    ";
    $params = [
        ':nome'        => '%' . $nome . '%',
        ':data_inicio' => $data_inicio . ' 00:00:00',
        ':data_fim'    => $data_fim    . ' 23:59:59',
    ];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $registros = [];
    while ($row = $stmt->fetch()) {
        $registros[$row['nome']][] = ['tipo' => $row['tipo'], 'data_hora' => $row['data_hora']];
    }
} else {
    // Sem filtro: última entrada e última saída de cada funcionário
    $sql = "
        SELECT f.nome, rp.tipo, rp.data_hora
        FROM funcionarios f
        LEFT JOIN (
            SELECT t1.*
            FROM registro_ponto t1
            INNER JOIN (
                SELECT funcionario_id, tipo, MAX(data_hora) AS max_data_hora
                FROM registro_ponto
                GROUP BY funcionario_id, tipo
            ) t2
            ON t1.funcionario_id = t2.funcionario_id
           AND t1.tipo           = t2.tipo
           AND t1.data_hora      = t2.max_data_hora
        ) rp ON f.id = rp.funcionario_id
        WHERE rp.data_hora IS NOT NULL
        ORDER BY f.nome, rp.tipo DESC, rp.data_hora DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $registros = [];
    while ($row = $stmt->fetch()) {
        $registros[$row['nome']][] = ['tipo' => $row['tipo'], 'data_hora' => $row['data_hora']];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Horas Trabalhadas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
      .cabecalho {
        display: flex;
        align-items: center;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 1.5rem;
      }
      @media (max-width: 576px) {
        .cabecalho { flex-direction: column; align-items: stretch; gap: 0.5rem; }
      }
      .badge-entrada { background-color: #198754; }
      .badge-saida   { background-color: #6c757d; }
      .total-row td  { font-weight: 700; background-color: #f0f4ff; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
      <div class="cabecalho">
        <h2 class="mb-0"><i class="bi bi-graph-up me-2 text-info"></i>Horas Trabalhadas — Dashboard</h2>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
      </div>

      <form class="row g-3 mb-4" method="get">
          <div class="col-md-4">
              <label for="nome" class="form-label">Funcionário</label>
              <input type="text" class="form-control" name="nome" id="nome"
                     value="<?= htmlspecialchars($nome) ?>" list="nomes" placeholder="Digite o nome">
              <datalist id="nomes">
                  <?php foreach ($listaFuncionarios as $f): ?>
                      <option value="<?= htmlspecialchars($f['nome']) ?>">
                  <?php endforeach; ?>
              </datalist>
          </div>
          <div class="col-md-3">
              <label for="data_inicio" class="form-label">Data Inicial</label>
              <input type="date" class="form-control" name="data_inicio" id="data_inicio"
                     value="<?= htmlspecialchars($data_inicio) ?>">
          </div>
          <div class="col-md-3">
              <label for="data_fim" class="form-label">Data Final</label>
              <input type="date" class="form-control" name="data_fim" id="data_fim"
                     value="<?= htmlspecialchars($data_fim) ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
          </div>
      </form>

      <?php if (empty($registros)): ?>
          <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Nenhum registro encontrado para o filtro selecionado.</div>
      <?php else: ?>

          <?php if ($nome !== ''): ?>
              <!-- ── Vista detalhada por funcionário (pares entrada/saída por dia) ── -->
              <?php foreach ($registros as $nome_func => $eventos):
                  $pares     = agrupar_pares_dashboard($eventos);
                  $totalSeg  = array_sum(array_column($pares, 'segundos'));
              ?>
              <div class="card mb-4 shadow-sm">
                  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                      <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i><?= htmlspecialchars($nome_func) ?></h5>
                      <span class="badge bg-light text-dark fs-6">
                          Total: <?= formatar_segundos_dash($totalSeg) ?>
                      </span>
                  </div>
                  <div class="card-body p-0">
                      <div class="table-responsive">
                          <table class="table table-bordered table-hover align-middle mb-0">
                              <thead class="table-light">
                                  <tr>
                                      <th>Data</th>
                                      <th>Entrada</th>
                                      <th>Saída</th>
                                      <th>Trabalhado</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php foreach ($pares as $par): ?>
                                      <tr>
                                          <td><?= date('d/m/Y', strtotime($par['dia'])) ?></td>
                                          <td><span class="badge badge-entrada"><?= date('H:i:s', strtotime($par['entrada'])) ?></span></td>
                                          <td>
                                              <?php if ($par['saida'] !== null): ?>
                                                  <span class="badge badge-saida"><?= date('H:i:s', strtotime($par['saida'])) ?></span>
                                              <?php else: ?>
                                                  <span class="badge bg-warning text-dark">Em aberto</span>
                                              <?php endif; ?>
                                          </td>
                                          <td>
                                              <?php if ($par['saida'] !== null): ?>
                                                  <?= formatar_segundos_dash($par['segundos']) ?>
                                              <?php else: ?>
                                                  <span class="text-muted">—</span>
                                              <?php endif; ?>
                                          </td>
                                      </tr>
                                  <?php endforeach; ?>
                              </tbody>
                              <tfoot>
                                  <tr class="total-row">
                                      <td colspan="3" class="text-end">Total no período:</td>
                                      <td><?= formatar_segundos_dash($totalSeg) ?></td>
                                  </tr>
                              </tfoot>
                          </table>
                      </div>
                  </div>
              </div>
              <?php endforeach; ?>

              <a href="gerar_relatorio.php?nome=<?= urlencode($nome) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
                 target="_blank" class="btn btn-danger mb-5">
                  <i class="bi bi-file-earmark-pdf me-1"></i>Imprimir / Exportar Relatório PDF
              </a>

          <?php else: ?>
              <!-- ── Vista resumida: último registro por funcionário ── -->
              <div class="alert alert-info mb-3">
                  <i class="bi bi-info-circle me-1"></i>
                  Exibindo o último registro de cada funcionário. Use o filtro por nome para ver o histórico detalhado e calcular horas trabalhadas.
              </div>
              <?php foreach ($registros as $nome_func => $eventos): ?>
              <div class="card mb-3 shadow-sm">
                  <div class="card-header bg-primary text-white">
                      <h6 class="mb-0"><i class="bi bi-person me-2"></i><?= htmlspecialchars($nome_func) ?></h6>
                  </div>
                  <div class="card-body p-0">
                      <div class="table-responsive">
                          <table class="table table-bordered table-hover align-middle mb-0">
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
                                          <td>
                                              <span class="badge bg-<?= $evento['tipo'] === 'entrada' ? 'success' : 'secondary' ?>">
                                                  <?= ucfirst($evento['tipo']) ?>
                                              </span>
                                          </td>
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

              <a href="gerar_relatorio.php?data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
                 target="_blank" class="btn btn-danger mb-5">
                  <i class="bi bi-file-earmark-pdf me-1"></i>Imprimir / Exportar Relatório PDF (todos)
              </a>
          <?php endif; ?>

      <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
