<?php include 'conexao.php'; require 'auth_admin.php'; ?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Funcionário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Cadastrar Novo Funcionário</h2>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="processa_cadastro.php" method="POST" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome completo*:</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">CPF:</label>
                            <input type="text" name="cpf" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de nascimento:</label>
                            <input type="date" name="data_nascimento" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Sexo:</label>
                            <select name="sexo" class="form-select">
                                <option value="">Selecione</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Feminino">Feminino</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telefone:</label>
                            <input type="text" name="telefone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail:</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label class="form-label">CEP:</label>
                            <input type="text" name="cep" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rua:</label>
                            <input type="text" name="rua" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bairro:</label>
                            <input type="text" name="bairro" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cidade:</label>
                            <input type="text" name="cidade" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Estado:</label>
                            <input type="text" name="estado" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Cargo*:</label>
                            <input type="text" name="cargo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Registro de Classe (ex: CRM, OAB):</label>
                            <input type="text" name="registro_classe" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Departamento/Setor:</label>
                            <input type="text" name="departamento" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Data de admissão:</label>
                            <input type="date" name="data_admissao" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Regime de contratação:</label>
                            <select name="regime" class="form-select">
                                <option value="">Selecione</option>
                                <option value="Efetivo">Efetivo</option>
                                <option value="Temporário">Temporário</option>
                                <option value="Estágio">Estágio</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jornada de trabalho:</label>
                            <input type="text" name="jornada" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Situação:</label>
                            <select name="situacao" class="form-select">
                                <option value="Ativo" selected>Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Cadastrar Funcionário</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
