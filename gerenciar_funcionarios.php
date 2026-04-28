<?php
include 'conexao.php';

/**
 * Filtro por cargo via GET:
 * /gerenciar_funcionarios.php?cargo=Enfermeiro
 */
$cargoSelecionado = isset($_GET['cargo']) ? trim($_GET['cargo']) : '';

// Carrega lista de cargos para o select
$stmtCargos = $pdo->query("
    SELECT DISTINCT cargo
    FROM funcionarios
    WHERE cargo IS NOT NULL AND cargo <> ''
    ORDER BY cargo
");
$cargos = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);

// Monta query principal com ou sem filtro
$sql = "SELECT id, nome, cpf, matricula, cargo, data_nascimento, sexo, telefone, email, cep, rua, bairro, cidade, estado, registro_classe, departamento, data_admissao, regime, jornada, observacoes, situacao
        FROM funcionarios";

$params = [];

if ($cargoSelecionado !== '') {
    $sql .= " WHERE cargo = :cargo";
    $params[':cargo'] = $cargoSelecionado;
}

$sql .= " ORDER BY nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Funcionários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-people me-2 text-warning"></i>Funcionários Cadastrados</h2>
            <div class="d-flex gap-2">
                <a href="cadastrar_funcionario.php" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Cadastrar Novo</a>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
            </div>
        </div>

        <!-- Filtro por Cargo -->
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4">
                <label class="form-label">Filtrar por cargo</label>
                <select name="cargo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?= htmlspecialchars($cargo) ?>" <?= ($cargoSelecionado === $cargo) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cargo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="gerenciar_funcionarios.php" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php if ($_GET['sucesso'] === 'cadastro'): ?>
                    <i class="bi bi-check-circle me-1"></i> Funcionário cadastrado com sucesso!
                <?php else: ?>
                    <i class="bi bi-check-circle me-1"></i> Dados atualizados com sucesso!
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($_GET['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?php if ($_GET['erro'] === 'campos_obrigatorios'): ?>
                    Preencha todos os campos obrigatórios.
                <?php else: ?>
                    Ocorreu um erro ao salvar. Tente novamente.
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Matrícula</th>
                            <th>Cargo</th>
                            <th>Situação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($funcionarios)) : ?>
                            <?php foreach ($funcionarios as $funcionario) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($funcionario['nome']) ?></td>
                                    <td><?= htmlspecialchars($funcionario['cpf']) ?></td>
                                    <td><?= htmlspecialchars($funcionario['matricula']) ?></td>
                                    <td><?= htmlspecialchars($funcionario['cargo']) ?></td>
                                    <td>
                                        <form action="mudar_situacao.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $funcionario['id'] ?>">
                                            <select name="situacao" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()" style="min-width: 90px;">
                                                <option value="Ativo" <?= $funcionario['situacao'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                                <option value="Inativo" <?= $funcionario['situacao'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="gerar_qrcode.php?id=<?= $funcionario['id'] ?>" class="btn btn-sm btn-primary" target="_blank">
                                            Gerar QR Code
                                        </a>
                                        <button type="button" class="btn btn-sm btn-warning ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editarFuncionarioModal"
                                            data-id="<?= $funcionario['id'] ?>"
                                            data-nome="<?= htmlspecialchars($funcionario['nome'], ENT_QUOTES) ?>"
                                            data-cpf="<?= htmlspecialchars($funcionario['cpf'], ENT_QUOTES) ?>"
                                            data-matricula="<?= htmlspecialchars($funcionario['matricula'], ENT_QUOTES) ?>"
                                            data-cargo="<?= htmlspecialchars($funcionario['cargo'], ENT_QUOTES) ?>"
                                            data-data_nascimento="<?= htmlspecialchars($funcionario['data_nascimento'], ENT_QUOTES) ?>"
                                            data-sexo="<?= htmlspecialchars($funcionario['sexo'], ENT_QUOTES) ?>"
                                            data-telefone="<?= htmlspecialchars($funcionario['telefone'], ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($funcionario['email'], ENT_QUOTES) ?>"
                                            data-cep="<?= htmlspecialchars($funcionario['cep'], ENT_QUOTES) ?>"
                                            data-rua="<?= htmlspecialchars($funcionario['rua'], ENT_QUOTES) ?>"
                                            data-bairro="<?= htmlspecialchars($funcionario['bairro'], ENT_QUOTES) ?>"
                                            data-cidade="<?= htmlspecialchars($funcionario['cidade'], ENT_QUOTES) ?>"
                                            data-estado="<?= htmlspecialchars($funcionario['estado'], ENT_QUOTES) ?>"
                                            data-registro_classe="<?= htmlspecialchars($funcionario['registro_classe'], ENT_QUOTES) ?>"
                                            data-departamento="<?= htmlspecialchars($funcionario['departamento'], ENT_QUOTES) ?>"
                                            data-data_admissao="<?= htmlspecialchars($funcionario['data_admissao'], ENT_QUOTES) ?>"
                                            data-regime="<?= htmlspecialchars($funcionario['regime'], ENT_QUOTES) ?>"
                                            data-jornada="<?= htmlspecialchars($funcionario['jornada'], ENT_QUOTES) ?>"
                                            data-observacoes="<?= htmlspecialchars($funcionario['observacoes'], ENT_QUOTES) ?>"
                                            data-situacao="<?= htmlspecialchars($funcionario['situacao'], ENT_QUOTES) ?>"
                                        >Editar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum funcionário cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de edição -->
    <div class="modal fade" id="editarFuncionarioModal" tabindex="-1" aria-labelledby="editarFuncionarioModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form id="formEditarFuncionario" action="atualiza_funcionario.php" method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="editarFuncionarioModalLabel">Editar Funcionário</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="edit-nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" id="edit-nome" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" name="cpf" id="edit-cpf" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-matricula" class="form-label">Matrícula</label>
                        <input type="text" class="form-control" name="matricula" id="edit-matricula" required readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="edit-cargo" class="form-label">Cargo</label>
                        <input type="text" class="form-control" name="cargo" id="edit-cargo" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-data_nascimento" class="form-label">Data de Nascimento</label>
                        <input type="date" class="form-control" name="data_nascimento" id="edit-data_nascimento">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-sexo" class="form-label">Sexo</label>
                        <select class="form-select" name="sexo" id="edit-sexo">
                            <option value="">Selecione</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Feminino">Feminino</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="telefone" id="edit-telefone">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="edit-email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" id="edit-email">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="edit-cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" name="cep" id="edit-cep">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-rua" class="form-label">Rua</label>
                        <input type="text" class="form-control" name="rua" id="edit-rua">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" name="bairro" id="edit-bairro">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="edit-cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" name="cidade" id="edit-cidade">
                    </div>
                    <div class="col-md-1 mb-3">
                        <label for="edit-estado" class="form-label">Estado</label>
                        <input type="text" class="form-control" name="estado" id="edit-estado">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="edit-registro_classe" class="form-label">Registro de Classe</label>
                        <input type="text" class="form-control" name="registro_classe" id="edit-registro_classe">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="edit-departamento" class="form-label">Departamento</label>
                        <input type="text" class="form-control" name="departamento" id="edit-departamento">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="edit-data_admissao" class="form-label">Data de Admissão</label>
                        <input type="date" class="form-control" name="data_admissao" id="edit-data_admissao">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-regime" class="form-label">Regime</label>
                        <select class="form-select" name="regime" id="edit-regime">
                            <option value="">Selecione</option>
                            <option value="Efetivo">Efetivo</option>
                            <option value="Temporário">Temporário</option>
                            <option value="Estágio">Estágio</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-jornada" class="form-label">Jornada</label>
                        <input type="text" class="form-control" name="jornada" id="edit-jornada">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="edit-situacao" class="form-label">Situação</label>
                        <select class="form-select" name="situacao" id="edit-situacao">
                            <option value="Ativo">Ativo</option>
                            <option value="Inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="edit-observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" id="edit-observacoes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Atualizar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Preenche o modal com os dados do funcionário ao clicar em "Editar"
    const editarModal = document.getElementById('editarFuncionarioModal');
    editarModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('edit-id').value = button.getAttribute('data-id');
        document.getElementById('edit-nome').value = button.getAttribute('data-nome');
        document.getElementById('edit-cpf').value = button.getAttribute('data-cpf');
        document.getElementById('edit-matricula').value = button.getAttribute('data-matricula');
        document.getElementById('edit-cargo').value = button.getAttribute('data-cargo');
        document.getElementById('edit-data_nascimento').value = button.getAttribute('data-data_nascimento');
        document.getElementById('edit-sexo').value = button.getAttribute('data-sexo');
        document.getElementById('edit-telefone').value = button.getAttribute('data-telefone');
        document.getElementById('edit-email').value = button.getAttribute('data-email');
        document.getElementById('edit-cep').value = button.getAttribute('data-cep');
        document.getElementById('edit-rua').value = button.getAttribute('data-rua');
        document.getElementById('edit-bairro').value = button.getAttribute('data-bairro');
        document.getElementById('edit-cidade').value = button.getAttribute('data-cidade');
        document.getElementById('edit-estado').value = button.getAttribute('data-estado');
        document.getElementById('edit-registro_classe').value = button.getAttribute('data-registro_classe');
        document.getElementById('edit-departamento').value = button.getAttribute('data-departamento');
        document.getElementById('edit-data_admissao').value = button.getAttribute('data-data_admissao');
        document.getElementById('edit-regime').value = button.getAttribute('data-regime');
        document.getElementById('edit-jornada').value = button.getAttribute('data-jornada');
        document.getElementById('edit-observacoes').value = button.getAttribute('data-observacoes');
        document.getElementById('edit-situacao').value = button.getAttribute('data-situacao');
    });
    </script>
</body>
</html>