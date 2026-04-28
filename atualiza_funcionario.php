<?php
// atualiza_funcionario.php
// Atualiza os dados do funcionário (via POST) usando PDO (conexao.php)

require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gerenciar_funcionarios.php');
    exit;
}

// Helper: transforma string vazia em NULL (para campos que aceitam NULL no banco)
function emptyToNull($v) {
    if (!isset($v)) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

// Campos obrigatórios no seu fluxo (ajuste se quiser)
$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nome      = emptyToNull($_POST['nome'] ?? null);
$cargo     = emptyToNull($_POST['cargo'] ?? null);

// data_nascimento no seu SQL está NOT NULL
$data_nascimento = emptyToNull($_POST['data_nascimento'] ?? null);

// Situação é enum com default 'Ativo' — no modal sempre vem, mas validamos
$situacao  = emptyToNull($_POST['situacao'] ?? null);

if ($id <= 0 || !$nome || !$cargo || !$data_nascimento) {
    // Você pode trocar por mensagem em tela, flash, etc.
    header('Location: gerenciar_funcionarios.php?erro=campos_obrigatorios');
    exit;
}

// Demais campos (nullable no banco)
$cpf             = emptyToNull($_POST['cpf'] ?? null);
$matricula       = emptyToNull($_POST['matricula'] ?? null); // readonly no modal, mas atualizável se vier
$sexo            = emptyToNull($_POST['sexo'] ?? null);
$telefone        = emptyToNull($_POST['telefone'] ?? null);
$email           = emptyToNull($_POST['email'] ?? null);
$cep             = emptyToNull($_POST['cep'] ?? null);
$rua             = emptyToNull($_POST['rua'] ?? null);
$bairro          = emptyToNull($_POST['bairro'] ?? null);
$cidade          = emptyToNull($_POST['cidade'] ?? null);
$estado          = emptyToNull($_POST['estado'] ?? null);
$registro_classe = emptyToNull($_POST['registro_classe'] ?? null);
$departamento    = emptyToNull($_POST['departamento'] ?? null);
$data_admissao   = emptyToNull($_POST['data_admissao'] ?? null);
$regime          = emptyToNull($_POST['regime'] ?? null);
$jornada         = emptyToNull($_POST['jornada'] ?? null);
$observacoes     = emptyToNull($_POST['observacoes'] ?? null);

// Valida enums (evita gravar lixo)
$sexosPermitidos = ['Masculino', 'Feminino', 'Outro'];
if ($sexo !== null && !in_array($sexo, $sexosPermitidos, true)) {
    $sexo = null;
}

$regimesPermitidos = ['Efetivo', 'Temporário', 'Estágio'];
if ($regime !== null && !in_array($regime, $regimesPermitidos, true)) {
    $regime = null;
}

$situacoesPermitidas = ['Ativo', 'Inativo'];
if ($situacao === null || !in_array($situacao, $situacoesPermitidas, true)) {
    $situacao = 'Ativo';
}

// Normaliza estado para 2 caracteres, se vier preenchido
if ($estado !== null) {
    $estado = strtoupper($estado);
    if (strlen($estado) > 2) {
        $estado = substr($estado, 0, 2);
    }
}

// UPDATE
$sql = "
    UPDATE funcionarios SET
        nome = :nome,
        cpf = :cpf,
        matricula = :matricula,
        cargo = :cargo,
        data_nascimento = :data_nascimento,
        sexo = :sexo,
        telefone = :telefone,
        email = :email,
        cep = :cep,
        rua = :rua,
        bairro = :bairro,
        cidade = :cidade,
        estado = :estado,
        registro_classe = :registro_classe,
        departamento = :departamento,
        data_admissao = :data_admissao,
        regime = :regime,
        jornada = :jornada,
        observacoes = :observacoes,
        situacao = :situacao
    WHERE id = :id
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':nome' => $nome,
        ':cpf' => $cpf,
        ':matricula' => $matricula,
        ':cargo' => $cargo,
        ':data_nascimento' => $data_nascimento,
        ':sexo' => $sexo,
        ':telefone' => $telefone,
        ':email' => $email,
        ':cep' => $cep,
        ':rua' => $rua,
        ':bairro' => $bairro,
        ':cidade' => $cidade,
        ':estado' => $estado,
        ':registro_classe' => $registro_classe,
        ':departamento' => $departamento,
        ':data_admissao' => $data_admissao,
        ':regime' => $regime,
        ':jornada' => $jornada,
        ':observacoes' => $observacoes,
        ':situacao' => $situacao,
    ]);

    // Se quiser garantir que atualizou alguém:
    // if ($stmt->rowCount() === 0) { ... }

    header('Location: gerenciar_funcionarios.php?sucesso=1');
    exit;

} catch (PDOException $e) {
    // Caso matricula seja UNIQUE e tente duplicar, pode cair aqui.
    // Em produção, evite exibir erro do banco.
    header('Location: gerenciar_funcionarios.php?erro=bd');
    exit;
}