<?php
/**
 * auth_admin.php
 * Include this file at the top of every admin page.
 * - Starts (or resumes) the PHP session.
 * - On first run, creates the `administradores` table and inserts the default
 *   admin account if it does not already exist.
 * - Redirects unauthenticated requests to login.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── One-time DB bootstrap (table + default user) ──────────────────────────────
// conexao.php must have been required BEFORE this file so $pdo is available.
if (isset($pdo)) {
    // Create table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS administradores (
            id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login    VARCHAR(60)  NOT NULL UNIQUE,
            senha    VARCHAR(255) NOT NULL,
            criado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert default admin if not present
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE login = :login");
    $stmt->execute([':login' => 'administrador']);
    if ((int)$stmt->fetchColumn() === 0) {
        $hash = password_hash('umsc12', PASSWORD_DEFAULT);
        $ins  = $pdo->prepare("INSERT INTO administradores (login, senha) VALUES (:login, :senha)");
        $ins->execute([':login' => 'administrador', ':senha' => $hash]);
    }
}

// ── Guard ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: login.php' . ($redirect ? '?redir=' . $redirect : ''));
    exit;
}
