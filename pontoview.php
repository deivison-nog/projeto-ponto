<?php
session_start();

// ── Conexão ───────────────────────────────────────────────────────────────────
require 'conexao.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatar_segundos_pv(int $segundos): string {
    $h = (int)floor($segundos / 3600);
    $m = (int)floor(($segundos % 3600) / 60);
    return sprintf('%02dh%02dm', $h, $m);
}

function agrupar_pares_pv(array $eventos): array {
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
                $diff   = strtotime($ev['data_hora']) - strtotime($entrada);
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

// ── Ações ─────────────────────────────────────────────────────────────────────

$erro = '';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: pontoview.php');
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cpf'])) {
    $cpf = preg_replace('/\D/', '', trim($_POST['cpf']));

    if ($cpf === '') {
        $erro = 'Informe o CPF.';
    } else {
        // Normaliza formato do CPF para comparação (aceita com ou sem pontuação no banco)
        $stmt = $pdo->prepare(
            "SELECT id, nome, cargo, departamento, cpf
             FROM funcionarios
             WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf
               AND situacao = 'Ativo'
             LIMIT 1"
        );
        $stmt->execute([':cpf' => $cpf]);
        $func = $stmt->fetch();

        if (!$func) {
            $erro = 'CPF não encontrado ou funcionário inativo. Verifique e tente novamente.';
        } else {
            $_SESSION['pv_id']    = $func['id'];
            $_SESSION['pv_nome']  = $func['nome'];
            $_SESSION['pv_cargo'] = $func['cargo']        ?? '';
            $_SESSION['pv_depto'] = $func['departamento'] ?? '';
            header('Location: pontoview.php');
            exit;
        }
    }
}

$logado    = isset($_SESSION['pv_id']);
$funcId    = $logado ? (int)$_SESSION['pv_id']   : 0;
$funcNome  = $logado ? $_SESSION['pv_nome']       : '';
$funcCargo = $logado ? $_SESSION['pv_cargo']      : '';
$funcDepto = $logado ? $_SESSION['pv_depto']      : '';

// ── Dados do período (apenas quando logado) ───────────────────────────────────
$pares        = [];
$totalSeg     = 0;
$totalDias    = 0;
$data_inicio  = '';
$data_fim     = '';

if ($logado) {
    $data_inicio = trim($_GET['data_inicio'] ?? date('Y-m-01'));
    $data_fim    = trim($_GET['data_fim']    ?? date('Y-m-t'));

    $stmt = $pdo->prepare("
        SELECT tipo, data_hora
        FROM registro_ponto
        WHERE funcionario_id = :id
          AND data_hora BETWEEN :data_inicio AND :data_fim
        ORDER BY data_hora
    ");
    $stmt->execute([
        ':id'          => $funcId,
        ':data_inicio' => $data_inicio . ' 00:00:00',
        ':data_fim'    => $data_fim    . ' 23:59:59',
    ]);
    $eventos  = $stmt->fetchAll();
    $pares    = agrupar_pares_pv($eventos);
    $totalSeg = (int)array_sum(array_column($pares, 'segundos'));
    $totalDias = count(array_unique(array_column(
        array_filter($pares, fn($p) => $p['saida'] !== null),
        'dia'
    )));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Meu Ponto</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f0f4f8; }

    /* ── Login card ── */
    .login-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      border-radius: 1rem;
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
    }
    .login-card .card-header {
      background: linear-gradient(135deg, #1e5aa0, #2d82d2);
      border-radius: 1rem 1rem 0 0;
      padding: 2rem 1.5rem 1.5rem;
      text-align: center;
      color: #fff;
    }
    .login-card .card-header .bi { font-size: 2.8rem; }
    .cpf-input { letter-spacing: .08em; font-size: 1.1rem; text-align: center; }

    /* ── Top bar ── */
    .topbar {
      background: linear-gradient(135deg, #1e5aa0, #2d82d2);
      color: #fff;
      padding: .75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .5rem;
    }
    .topbar .brand { font-size: 1.1rem; font-weight: 700; }
    .topbar .user-info { font-size: .9rem; opacity: .9; }

    /* ── KPI cards ── */
    .kpi-card { border: none; border-radius: .75rem; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
    .kpi-card .kpi-val { font-size: 1.6rem; font-weight: 700; }

    /* ── Badge colors ── */
    .badge-entrada { background-color: #198754; }
    .badge-saida   { background-color: #6c757d; }

    /* ── Table ── */
    .total-row td { font-weight: 700; background-color: #e8f0ff; }
  </style>
</head>
<body>

<?php if (!$logado): ?>

<!-- ════════════════════════════════════════════════════════
     TELA DE LOGIN
═══════════════════════════════════════════════════════════ -->
<div class="login-wrapper">
  <div class="card login-card">
    <div class="card-header">
      <i class="bi bi-person-badge d-block mb-2"></i>
      <h4 class="mb-0 fw-bold">Meu Ponto</h4>
      <p class="mb-0 mt-1 small opacity-75">Consulte seus registros de ponto</p>
    </div>
    <div class="card-body p-4">
      <?php if ($erro !== ''): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle-fill"></i>
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off" novalidate>
        <div class="mb-3">
          <label for="cpf" class="form-label fw-semibold">CPF</label>
          <input type="text" id="cpf" name="cpf" class="form-control cpf-input"
                 placeholder="000.000.000-00" maxlength="18"
                 inputmode="numeric" required autofocus>
          <div class="form-text text-muted">Digite apenas os números ou com pontuação.</div>
        </div>
        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Máscara de CPF no campo
document.getElementById('cpf').addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '').substring(0, 11);
  if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
  else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
  else if (v.length > 3) v = v.replace(/^(\d{3})(\d{0,3})/, '$1.$2');
  this.value = v;
});
</script>

<?php else: ?>

<!-- ════════════════════════════════════════════════════════
     PAINEL DO FUNCIONÁRIO
═══════════════════════════════════════════════════════════ -->
<div class="topbar">
  <div>
    <span class="brand"><i class="bi bi-clock-history me-2"></i>Meu Ponto</span>
  </div>
  <div class="d-flex align-items-center gap-3">
    <div class="user-info text-end">
      <div><i class="bi bi-person-fill me-1"></i><strong><?= htmlspecialchars($funcNome) ?></strong></div>
      <?php if ($funcCargo !== ''): ?>
        <div class="small opacity-75"><?= htmlspecialchars($funcCargo) ?><?= $funcDepto !== '' ? ' · ' . htmlspecialchars($funcDepto) : '' ?></div>
      <?php endif; ?>
    </div>
    <a href="pontoview.php?logout=1" class="btn btn-sm btn-light text-primary">
      <i class="bi bi-box-arrow-right me-1"></i>Sair
    </a>
  </div>
</div>

<div class="container py-4">

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card kpi-card text-center py-3">
        <div class="kpi-val text-primary"><?= formatar_segundos_pv($totalSeg) ?></div>
        <div class="text-muted small">Horas no período</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card text-center py-3">
        <div class="kpi-val text-success"><?= $totalDias ?></div>
        <div class="text-muted small">Dias trabalhados</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card text-center py-3">
        <div class="kpi-val text-info"><?= count($pares) ?></div>
        <div class="text-muted small">Registros</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card kpi-card text-center py-3">
        <div class="kpi-val text-warning"><?= count(array_filter($pares, fn($p) => $p['saida'] === null)) ?></div>
        <div class="text-muted small">Em aberto</div>
      </div>
    </div>
  </div>

  <!-- Filtro de datas -->
  <form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-sm-4 col-md-3">
      <label class="form-label">Data Inicial</label>
      <input type="date" name="data_inicio" class="form-control"
             value="<?= htmlspecialchars($data_inicio) ?>">
    </div>
    <div class="col-sm-4 col-md-3">
      <label class="form-label">Data Final</label>
      <input type="date" name="data_fim" class="form-control"
             value="<?= htmlspecialchars($data_fim) ?>">
    </div>
    <div class="col-sm-4 col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-primary flex-fill">
        <i class="bi bi-search me-1"></i>Filtrar
      </button>
      <a href="pontoview.php" class="btn btn-outline-secondary" title="Mês atual">
        <i class="bi bi-calendar3"></i>
      </a>
    </div>
  </form>

  <!-- Tabela de registros -->
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>
        <i class="bi bi-table me-1"></i>
        Registros de <strong><?= date('d/m/Y', strtotime($data_inicio)) ?></strong>
        a <strong><?= date('d/m/Y', strtotime($data_fim)) ?></strong>
      </span>
      <?php if (!empty($pares)): ?>
        <a href="pontoview_pdf.php?data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>"
           target="_blank" class="btn btn-sm btn-danger">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
      <?php endif; ?>
    </div>
    <div class="card-body p-0">
      <?php if (empty($pares)): ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-2 d-block mb-2"></i>
          Nenhum registro encontrado para o período selecionado.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-dark">
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
                  <td><?= date('d/m/Y', strtotime($par['dia'])) ?> <span class="text-muted small">(<?= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($par['dia']))] ?>)</span></td>
                  <td><span class="badge badge-entrada"><?= date('H:i:s', strtotime($par['entrada'])) ?></span></td>
                  <td>
                    <?php if ($par['saida'] !== null): ?>
                      <span class="badge badge-saida"><?= date('H:i:s', strtotime($par['saida'])) ?></span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Em aberto</span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-semibold">
                    <?= $par['saida'] !== null
                        ? formatar_segundos_pv($par['segundos'])
                        : '<span class="text-muted">—</span>' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="total-row">
                <td colspan="3" class="text-end">Total no período:</td>
                <td><?= formatar_segundos_pv($totalSeg) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <p class="text-muted small text-center mt-4 mb-0">
    &copy; <?= date('Y') ?> Sistema de Ponto
  </p>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
