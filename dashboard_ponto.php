<?php
require 'conexao.php';

// ── Filtros ──────────────────────────────────────────────────────────────────
$nome        = trim($_GET['nome']        ?? '');
$cargo       = trim($_GET['cargo']       ?? '');
$data_inicio = trim($_GET['data_inicio'] ?? date('Y-m-01'));
$data_fim    = trim($_GET['data_fim']    ?? date('Y-m-t'));

// Listas para os selects/datalists
$listaFuncionarios = $pdo->query("SELECT id, nome FROM funcionarios ORDER BY nome")->fetchAll();
$listaCargos       = $pdo->query(
    "SELECT DISTINCT cargo FROM funcionarios WHERE cargo IS NOT NULL AND cargo <> '' ORDER BY cargo"
)->fetchAll(PDO::FETCH_COLUMN);

// ── Funções auxiliares ────────────────────────────────────────────────────────

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

function formatar_segundos_dash(int $segundos): string {
    $h = (int)floor($segundos / 3600);
    $m = (int)floor(($segundos % 3600) / 60);
    return sprintf('%02dh%02dm', $h, $m);
}

// ── Busca de dados ────────────────────────────────────────────────────────────

if ($nome !== '') {
    // ── Visão detalhada: pares entrada/saída para um funcionário específico ──
    $sql = "
        SELECT f.nome, f.cargo, f.departamento, rp.tipo, rp.data_hora
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
        $registros[$row['nome']]['cargo'] = $row['cargo'] ?? '';
        $registros[$row['nome']]['depto'] = $row['departamento'] ?? '';
        $registros[$row['nome']]['eventos'][] = ['tipo' => $row['tipo'], 'data_hora' => $row['data_hora']];
    }
    $modo = 'detalhe';

} else {
    // ── Visão resumo: total de horas trabalhadas por funcionário no período ──
    $sql = "
        SELECT f.id, f.nome, f.cargo, f.departamento, f.situacao, rp.tipo, rp.data_hora
        FROM funcionarios f
        LEFT JOIN registro_ponto rp
            ON f.id = rp.funcionario_id
           AND rp.data_hora BETWEEN :data_inicio AND :data_fim
        WHERE f.situacao = 'Ativo'
    ";
    $params = [
        ':data_inicio' => $data_inicio . ' 00:00:00',
        ':data_fim'    => $data_fim    . ' 23:59:59',
    ];

    if ($cargo !== '') {
        $sql .= " AND f.cargo = :cargo";
        $params[':cargo'] = $cargo;
    }

    $sql .= " ORDER BY f.cargo, f.nome, rp.data_hora";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $empMap = [];
    while ($row = $stmt->fetch()) {
        $id = $row['id'];
        if (!isset($empMap[$id])) {
            $empMap[$id] = [
                'nome'    => $row['nome'],
                'cargo'   => $row['cargo']        ?? '',
                'depto'   => $row['departamento'] ?? '',
                'eventos' => [],
            ];
        }
        if ($row['data_hora'] !== null) {
            $empMap[$id]['eventos'][] = ['tipo' => $row['tipo'], 'data_hora' => $row['data_hora']];
        }
    }

    // Calcula totais
    $resumo = [];
    foreach ($empMap as $emp) {
        $pares     = agrupar_pares_dashboard($emp['eventos']);
        $totalSeg  = array_sum(array_column($pares, 'segundos'));
        $diasTrab  = count(array_unique(array_column(
            array_filter($pares, fn($p) => $p['saida'] !== null),
            'dia'
        )));
        $resumo[] = [
            'nome'    => $emp['nome'],
            'cargo'   => $emp['cargo'],
            'depto'   => $emp['depto'],
            'total'   => $totalSeg,
            'dias'    => $diasTrab,
            'pares'   => $pares,
        ];
    }
    $modo = 'resumo';
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
      .cabecalho { display:flex; align-items:center; gap:16px; justify-content:space-between; margin-bottom:1.5rem; }
      @media (max-width:576px) { .cabecalho { flex-direction:column; align-items:stretch; gap:.5rem; } }
      .badge-entrada { background-color:#198754; }
      .badge-saida   { background-color:#6c757d; }
      .total-row td  { font-weight:700; background-color:#f0f4ff; }
      .cargo-header  { background-color:#f8f9fa; font-weight:700; font-size:.9rem;
                       color:#495057; border-left:4px solid #0d6efd; padding:.5rem .75rem; }
      .summary-total { background-color:#e8f0ff; font-weight:700; }
      .zero-hours    { color:#adb5bd; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="cabecalho">
    <h2 class="mb-0"><i class="bi bi-graph-up me-2 text-info"></i>Horas Trabalhadas — Dashboard</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
  </div>

  <!-- ── Filtros ── -->
  <form class="row g-2 mb-4 align-items-end" method="get">
    <div class="col-md-3">
      <label for="nome" class="form-label">Funcionário</label>
      <input type="text" class="form-control" name="nome" id="nome"
             value="<?= htmlspecialchars($nome) ?>" list="nomes" placeholder="Nome do funcionário">
      <datalist id="nomes">
        <?php foreach ($listaFuncionarios as $f): ?>
          <option value="<?= htmlspecialchars($f['nome']) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="col-md-3">
      <label for="cargo" class="form-label">Cargo</label>
      <select name="cargo" id="cargo" class="form-select">
        <option value="">Todos os cargos</option>
        <?php foreach ($listaCargos as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $cargo === $c ? 'selected' : '' ?>>
            <?= htmlspecialchars($c) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="data_inicio" class="form-label">Data Inicial</label>
      <input type="date" class="form-control" name="data_inicio" id="data_inicio"
             value="<?= htmlspecialchars($data_inicio) ?>">
    </div>
    <div class="col-md-2">
      <label for="data_fim" class="form-label">Data Final</label>
      <input type="date" class="form-control" name="data_fim" id="data_fim"
             value="<?= htmlspecialchars($data_fim) ?>">
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
      <a href="dashboard_ponto.php" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>

  <!-- ── Conteúdo ── -->
  <?php if ($modo === 'detalhe'): ?>
    <?php if (empty($registros)): ?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Nenhum registro encontrado para o filtro selecionado.</div>
    <?php else: ?>
      <?php
        $totalGeralSeg = 0;
        foreach ($registros as $nome_func => $dadosFunc):
          $pares    = agrupar_pares_dashboard($dadosFunc['eventos']);
          $totalSeg = array_sum(array_column($pares, 'segundos'));
          $totalGeralSeg += $totalSeg;
      ?>
      <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <i class="bi bi-person-fill me-2"></i><strong><?= htmlspecialchars($nome_func) ?></strong>
            <?php if (!empty($dadosFunc['cargo'])): ?>
              <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($dadosFunc['cargo']) ?></span>
            <?php endif; ?>
            <?php if (!empty($dadosFunc['depto'])): ?>
              <span class="badge bg-secondary ms-1"><?= htmlspecialchars($dadosFunc['depto']) ?></span>
            <?php endif; ?>
          </div>
          <span class="badge bg-light text-dark fs-6">
            <i class="bi bi-clock me-1"></i>Total: <?= formatar_segundos_dash($totalSeg) ?>
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
                      <?= $par['saida'] !== null
                          ? formatar_segundos_dash($par['segundos'])
                          : '<span class="text-muted">—</span>' ?>
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

      <a href="gerar_relatorio.php?nome=<?= urlencode($nome) ?>&cargo=<?= urlencode($cargo) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
         target="_blank" class="btn btn-danger mb-5">
        <i class="bi bi-file-earmark-pdf me-1"></i>Imprimir / Exportar Relatório PDF
      </a>
    <?php endif; ?>

  <?php else: /* modo resumo */ ?>

    <?php if (empty($resumo)): ?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Nenhum funcionário ativo encontrado.</div>
    <?php else: ?>

      <?php
        // Agrupa o resumo por cargo
        $porCargo = [];
        foreach ($resumo as $r) {
            $porCargo[$r['cargo'] !== '' ? $r['cargo'] : '(Sem cargo)'][] = $r;
        }
        ksort($porCargo);
        $grandTotal = array_sum(array_column($resumo, 'total'));
      ?>

      <!-- KPI cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
              <div class="fs-2 fw-bold text-primary"><?= count($resumo) ?></div>
              <div class="text-muted small">Funcionários ativos</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
              <div class="fs-2 fw-bold text-success"><?= count(array_filter($resumo, fn($r) => $r['total'] > 0)) ?></div>
              <div class="text-muted small">Com registro no período</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
              <div class="fs-2 fw-bold text-info"><?= count($porCargo) ?></div>
              <div class="text-muted small">Cargos</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
              <div class="fs-2 fw-bold text-warning"><?= formatar_segundos_dash($grandTotal) ?></div>
              <div class="text-muted small">Total geral de horas</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabela de resumo por cargo -->
      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-table me-2"></i>Resumo do período:
            <strong><?= date('d/m/Y', strtotime($data_inicio)) ?></strong> a
            <strong><?= date('d/m/Y', strtotime($data_fim)) ?></strong>
            <?php if ($cargo !== ''): ?>
              — Cargo: <span class="badge bg-primary"><?= htmlspecialchars($cargo) ?></span>
            <?php endif; ?>
          </span>
          <a href="gerar_relatorio.php?cargo=<?= urlencode($cargo) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
             target="_blank" class="btn btn-sm btn-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
              <thead class="table-dark">
                <tr>
                  <th>Funcionário</th>
                  <th>Departamento</th>
                  <th class="text-center">Dias trabalhados</th>
                  <th class="text-center">Total de horas</th>
                  <th class="text-center">Detalhes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($porCargo as $cargoNome => $emps): ?>
                  <tr>
                    <td colspan="5" class="cargo-header">
                      <i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($cargoNome) ?>
                      <span class="text-muted fw-normal ms-2">(<?= count($emps) ?> funcionário<?= count($emps) !== 1 ? 's' : '' ?>)</span>
                    </td>
                  </tr>
                  <?php foreach ($emps as $emp): ?>
                    <tr class="<?= $emp['total'] === 0 ? 'zero-hours' : '' ?>">
                      <td>
                        <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($emp['nome']) ?>
                      </td>
                      <td><?= htmlspecialchars($emp['depto']) ?: '<span class="text-muted">—</span>' ?></td>
                      <td class="text-center">
                        <?php if ($emp['dias'] > 0): ?>
                          <span class="badge bg-success"><?= $emp['dias'] ?> dia<?= $emp['dias'] !== 1 ? 's' : '' ?></span>
                        <?php else: ?>
                          <span class="badge bg-secondary">0</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center fw-bold <?= $emp['total'] === 0 ? 'text-muted' : 'text-primary' ?>">
                        <?= $emp['total'] > 0 ? formatar_segundos_dash($emp['total']) : '—' ?>
                      </td>
                      <td class="text-center">
                        <a href="dashboard_ponto.php?nome=<?= urlencode($emp['nome']) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
                           class="btn btn-sm btn-outline-primary">
                          <i class="bi bi-eye me-1"></i>Ver
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="summary-total">
                  <td colspan="2" class="text-end">Total geral:</td>
                  <td class="text-center">
                    <?= array_sum(array_column($resumo, 'dias')) ?> dias
                  </td>
                  <td class="text-center text-primary">
                    <?= formatar_segundos_dash($grandTotal) ?>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

    <?php endif; ?>
  <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
