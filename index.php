<?php
require 'conexao.php';
require 'auth_admin.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Ponto - Menu Principal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .menu-card { transition: transform .15s, box-shadow .15s; }
        .menu-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.12) !important; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="text-center mb-2"><i class="bi bi-clock-history me-2 text-primary"></i>Sistema de Ponto</h1>
        <p class="text-center text-muted mb-5">Gerenciamento de ponto eletrônico</p>
        <div class="text-end mb-2">
          <a href="logout_admin.php" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right me-1"></i>Sair
          </a>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
            <div class="col">
                <a href="ponto.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-primary"><i class="bi bi-qr-code-scan"></i></span>
                            <h5 class="card-title mt-3">Registrar Ponto</h5>
                            <p class="card-text text-muted small">Via QR Code ou matrícula</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="registros.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-success"><i class="bi bi-clipboard-data"></i></span>
                            <h5 class="card-title mt-3">Registros de Ponto</h5>
                            <p class="card-text text-muted small">Consultar todos os registros</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="dashboard_ponto.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-info"><i class="bi bi-graph-up"></i></span>
                            <h5 class="card-title mt-3">Dashboard</h5>
                            <p class="card-text text-muted small">Horas trabalhadas e totais</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="gerar_relatorio.php" target="_blank" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-danger"><i class="bi bi-file-earmark-pdf"></i></span>
                            <h5 class="card-title mt-3">Relatório PDF</h5>
                            <p class="card-text text-muted small">Exportar relatório de ponto</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="gerenciar_funcionarios.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-warning"><i class="bi bi-people"></i></span>
                            <h5 class="card-title mt-3">Gerenciar Funcionários</h5>
                            <p class="card-text text-muted small">Editar, ativar e desativar</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="cadastrar_funcionario.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm menu-card">
                        <div class="card-body py-4">
                            <span class="display-3 text-danger"><i class="bi bi-person-plus"></i></span>
                            <h5 class="card-title mt-3">Cadastrar Funcionário</h5>
                            <p class="card-text text-muted small">Novo registro de colaborador</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <footer class="mt-5 text-center text-muted small">
            &copy; <?= date('Y') ?> Sistema de Ponto
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
