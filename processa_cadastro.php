<?php
require 'conexao.php';
require 'auth_admin.php';

// Função para gerar matrícula única
function gerarMatriculaUnica($pdo) {
    do {
        $matricula = rand(100000, 999999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE matricula = ?");
        $stmt->execute([$matricula]);
        $existe = $stmt->fetchColumn();
    } while ($existe > 0);
    return $matricula;
}

// Verifica campos obrigatórios
$camposObrigatorios = ['nome', 'cpf', 'data_nascimento', 'cargo'];
foreach ($camposObrigatorios as $campo) {
    if (empty($_POST[$campo])) {
        die("Erro: O campo '$campo' é obrigatório.");
    }
}

// Prepara os dados
$nome = $_POST['nome'];
$cpf = $_POST['cpf'];
$data_nascimento = $_POST['data_nascimento'];
$sexo = $_POST['sexo'] ?? null;
$telefone = $_POST['telefone'] ?? null;
$email = $_POST['email'] ?? null;
$cep = $_POST['cep'] ?? null;
$rua = $_POST['rua'] ?? null;
$bairro = $_POST['bairro'] ?? null;
$cidade = $_POST['cidade'] ?? null;
$estado = $_POST['estado'] ?? null;
$cargo = $_POST['cargo'];
$registro_classe = $_POST['registro_classe'] ?? null;
$departamento = $_POST['departamento'] ?? null;
$data_admissao = $_POST['data_admissao'] ?? null;
$regime = $_POST['regime'] ?? null;
$jornada = $_POST['jornada'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;
$situacao = $_POST['situacao'] ?? 'Ativo';
$matricula = gerarMatriculaUnica($pdo);

// Upload da foto (opcional)
$fotoPath = null;
if (!empty($_FILES['foto']['name'])) {
    $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $nomeFoto = uniqid('foto_') . "." . $extensao;
    $destino = 'uploads/' . $nomeFoto;

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
        $fotoPath = $destino;
    } else {
        die("Erro ao fazer upload da foto.");
    }
}

// Insere no banco de dados
$sql = "INSERT INTO funcionarios (
    nome, cpf, matricula, data_nascimento, sexo, telefone, email, cep, rua, bairro, cidade, estado,
    cargo, registro_classe, departamento, data_admissao, regime, jornada, observacoes, foto, situacao
) VALUES (
    :nome, :cpf, :matricula, :data_nascimento, :sexo, :telefone, :email, :cep, :rua, :bairro, :cidade, :estado,
    :cargo, :registro_classe, :departamento, :data_admissao, :regime, :jornada, :observacoes, :foto, :situacao
)";
$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([
        ':nome' => $nome,
        ':cpf' => $cpf,
        ':matricula' => $matricula,
        ':data_nascimento' => $data_nascimento,
        ':sexo' => $sexo,
        ':telefone' => $telefone,
        ':email' => $email,
        ':cep' => $cep,
        ':rua' => $rua,
        ':bairro' => $bairro,
        ':cidade' => $cidade,
        ':estado' => $estado,
        ':cargo' => $cargo,
        ':registro_classe' => $registro_classe,
        ':departamento' => $departamento,
        ':data_admissao' => $data_admissao,
        ':regime' => $regime,
        ':jornada' => $jornada,
        ':observacoes' => $observacoes,
        ':foto' => $fotoPath,
        ':situacao' => $situacao
    ]);

    header('Location: gerenciar_funcionarios.php?sucesso=cadastro');
    exit;

} catch (PDOException $e) {
    die("Erro ao cadastrar funcionário: " . $e->getMessage());
}
