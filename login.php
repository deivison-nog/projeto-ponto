<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

require 'conexao.php';

// ── Bootstrap DB (same as auth_admin.php, needed here before login) ───────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS administradores (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        login     VARCHAR(60)  NOT NULL UNIQUE,
        senha     VARCHAR(255) NOT NULL,
        criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE login = :login");
$stmt->execute([':login' => 'administrador']);
if ((int)$stmt->fetchColumn() === 0) {
    $hash = password_hash('umsc12', PASSWORD_DEFAULT);
    $ins  = $pdo->prepare("INSERT INTO administradores (login, senha) VALUES (:login, :senha)");
    $ins->execute([':login' => 'administrador', ':senha' => $hash]);
}

// ── Processar POST ────────────────────────────────────────────────────────────
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($login === '' || $senha === '') {
        $erro = 'Preencha o login e a senha.';
    } else {
        $stmt = $pdo->prepare("SELECT id, senha FROM administradores WHERE login = :login LIMIT 1");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login']     = $login;
            // Redirect to originally requested page or index
            $redir = $_POST['redir'] ?? '';
            header('Location: ' . ($redir !== '' ? urldecode($redir) : 'index.php'));
            exit;
        } else {
            $erro = 'Login ou senha incorretos.';
        }
    }
}

$redir = $_GET['redir'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — Sistema de Ponto</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f0f4f8; }
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
  </style>
</head>
<body>
<div class="login-wrapper">
  <div class="card login-card">
    <div class="card-header">
      <i class="bi bi-shield-lock d-block mb-2"></i>
      <h4 class="mb-0 fw-bold">Sistema de Ponto</h4>
      <p class="mb-0 mt-1 small opacity-75">Acesso restrito — administradores</p>
    </div>
    <div class="card-body p-4">
      <?php if ($erro !== ''): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle-fill"></i>
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off" novalidate>
        <?php if ($redir !== ''): ?>
          <input type="hidden" name="redir" value="<?= htmlspecialchars($redir) ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label for="login" class="form-label fw-semibold">Login</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" id="login" name="login" class="form-control"
                   placeholder="Login de administrador" required autofocus
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label for="senha" class="form-label fw-semibold">Senha</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="senha" name="senha" class="form-control"
                   placeholder="Senha" required>
            <button type="button" class="btn btn-outline-secondary"
                    onclick="toggleSenha()" tabindex="-1" title="Mostrar/ocultar">
              <i class="bi bi-eye" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
          </button>
        </div>
      </form>

      <hr class="my-3">
      <p class="text-center text-muted small mb-0">
        Funcionário?
        <a href="pontoview.php" class="text-decoration-none">Acesse seu ponto aqui</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSenha() {
  const input = document.getElementById('senha');
  const icon  = document.getElementById('eye-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
