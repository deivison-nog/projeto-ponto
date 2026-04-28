<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Ponto - Menu Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="text-center mb-5">Sistema de Ponto</h1>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
            <div class="col">
                <a href="ponto.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm">
                        <div class="card-body">
                            <span class="display-3 text-primary"><i class="bi bi-qr-code-scan"></i></span>
                            <h5 class="card-title mt-3">Registrar Ponto (QR Code)</h5>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="registros.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm">
                        <div class="card-body">
                            <span class="display-3 text-success"><i class="bi bi-clipboard-data"></i></span>
                            <h5 class="card-title mt-3">Registros de Ponto</h5>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="dashboard_ponto.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm">
                        <div class="card-body">
                            <span class="display-3 text-info"><i class="bi bi-graph-up"></i></span>
                            <h5 class="card-title mt-3">Dashboard</h5>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="gerenciar_funcionarios.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm">
                        <div class="card-body">
                            <span class="display-3 text-warning"><i class="bi bi-people"></i></span>
                            <h5 class="card-title mt-3">Gerenciar Funcionários</h5>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="cadastrar_funcionario.php" class="text-decoration-none">
                    <div class="card h-100 text-center shadow-sm">
                        <div class="card-body">
                            <span class="display-3 text-danger"><i class="bi bi-person-plus"></i></span>
                            <h5 class="card-title mt-3">Cadastrar Funcionário</h5>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <footer class="mt-5 text-center text-muted">
            &copy; <?= date('Y') ?> Sistema de Ponto
        </footer>
    </div>
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
