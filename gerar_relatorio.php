<?php
require 'conexao.php';
require_once(__DIR__ . '/fpdf/fpdf.php');

// Parâmetros do filtro
$nome = $_GET['nome'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Funções de cálculo
function calcular_total_trabalhado($eventos) {
    $entrada = null;
    $total_segundos = 0;
    foreach ($eventos as $evento) {
        if ($evento['tipo'] === 'entrada') {
            $entrada = strtotime($evento['data_hora']);
        } elseif ($evento['tipo'] === 'saida' && $entrada) {
            $saida = strtotime($evento['data_hora']);
            $intervalo = $saida - $entrada;
            if ($intervalo > 0) {
                $total_segundos += $intervalo;
            }
            $entrada = null;
        }
    }
    return $total_segundos;
}
function formatar_segundos($segundos) {
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segundos = $segundos % 60;
    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
}

if ($nome) {
    // Busca eventos de um funcionário específico
    $sql = "
        SELECT f.nome, f.cargo, f.departamento, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE f.nome LIKE :nome
    ";
    $params = [':nome' => '%' . $nome . '%'];
    if ($data_inicio && $data_fim) {
        $sql .= " AND rp.data_hora BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim'] = $data_fim . ' 23:59:59';
    }
    $sql .= " ORDER BY rp.data_hora";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll();

    if (empty($registros)) {
        die("Nenhum registro encontrado para o filtro.");
    }
} else {
    // Busca eventos de todos os funcionários no período
    $sql = "
        SELECT f.id, f.nome, f.cargo, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
    ";
    $params = [];
    if ($data_inicio && $data_fim) {
        $sql .= " WHERE rp.data_hora BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim'] = $data_fim . ' 23:59:59';
    }
    $sql .= " ORDER BY f.nome, rp.data_hora";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll();
}

// Agrupa eventos por funcionário
$funcionarios = [];
if (!$nome) {
    foreach ($registros as $r) {
        $id = $r['id'];
        if (!isset($funcionarios[$id])) {
            $funcionarios[$id] = [
                'nome' => $r['nome'],
                'cargo' => $r['cargo'],
                'eventos' => []
            ];
        }
        $funcionarios[$id]['eventos'][] = [
            'tipo' => $r['tipo'],
            'data_hora' => $r['data_hora']
        ];
    }
}

class PDF extends FPDF
{
    function Header()
    {
        global $nome, $data_inicio, $data_fim;
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0,10,utf8_decode('Relatório de Ponto'),0,1,'C');
        $this->SetFont('Arial', '', 12);
        if ($nome) {
            global $registros;
            $nomeCompleto = $registros[0]['nome'] ?? '';
            $cargo = $registros[0]['cargo'] ?? '';
            $departamento = $registros[0]['departamento'] ?? '';
            $linha_nome_departamento = "Funcionário: $nomeCompleto";
            if ($departamento) {
                $linha_nome_departamento .= " | Departamento: $departamento";
            }
            $this->Cell(0,8,utf8_decode($linha_nome_departamento),0,1,'L');
            if ($cargo) {
                $this->Cell(0,8,utf8_decode("Cargo: $cargo"),0,1,'L');
            }
            if ($data_inicio && $data_fim) {
                $this->Cell(0,8,utf8_decode("Período: ".date('d/m/Y', strtotime($data_inicio))." a ".date('d/m/Y', strtotime($data_fim))),0,1,'L');
            }
        } else {
            $periodo = "";
            if ($data_inicio && $data_fim) {
                $periodo = "Período: ".date('d/m/Y', strtotime($data_inicio))." a ".date('d/m/Y', strtotime($data_fim));
            }
            $this->Cell(0,8,utf8_decode("Total trabalhado por funcionário"),0,1,'L');
            if ($periodo) {
                $this->Cell(0,8,utf8_decode($periodo),0,1,'L');
            }
        }
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

if ($nome) {
    $total_segundos = calcular_total_trabalhado($registros);
    $total_trabalhado = formatar_segundos($total_segundos);

    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(50,8,utf8_decode('Data'),1,0,'C',true);
    $pdf->Cell(40,8,utf8_decode('Tipo'),1,0,'C',true);
    $pdf->Cell(50,8,utf8_decode('Horário'),1,1,'C',true);

    foreach($registros as $r) {
        $pdf->Cell(50,8,date('d/m/Y', strtotime($r['data_hora'])),1,0,'C');
        $pdf->Cell(40,8,utf8_decode(ucfirst($r['tipo'])),1,0,'C');
        $pdf->Cell(50,8,date('H:i:s', strtotime($r['data_hora'])),1,1,'C');
    }
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_decode("Total trabalhado no período: $total_trabalhado"),0,1,'R');
    $pdf->SetFont('Arial','',12);
} else {
    // Relatório resumido: nome, cargo, total trabalhado
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(80,8,utf8_decode('Funcionário'),1,0,'C',true);
    $pdf->Cell(60,8,utf8_decode('Cargo'),1,0,'C',true);
    $pdf->Cell(40,8,utf8_decode('Total Trabalhado'),1,1,'C',true);
    $pdf->SetFont('Arial','',12);

    foreach($funcionarios as $f) {
        $total_segundos = calcular_total_trabalhado($f['eventos']);
        $total_trabalhado = formatar_segundos($total_segundos);
        $pdf->Cell(80,8,utf8_decode($f['nome']),1,0,'L');
        $pdf->Cell(60,8,utf8_decode($f['cargo']),1,0,'L');
        $pdf->Cell(40,8,$total_trabalhado,1,1,'C');
    }
}

$pdf->Output('I','relatorio_ponto.pdf');
exit;
?>
