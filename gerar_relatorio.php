<?php
require 'conexao.php';
require_once(__DIR__ . '/fpdf/fpdf.php');

// Parâmetros do filtro
$nome        = trim($_GET['nome']        ?? '');
$data_inicio = trim($_GET['data_inicio'] ?? '');
$data_fim    = trim($_GET['data_fim']    ?? '');

// --- Helper functions ---

/**
 * Formata segundos como HHhMMmSSs (ex: 08h30m00s).
 */
function formatar_segundos(int $segundos): string {
    $h = (int)floor($segundos / 3600);
    $m = (int)floor(($segundos % 3600) / 60);
    $s = $segundos % 60;
    return sprintf('%02dh%02dm%02ds', $h, $m, $s);
}

/**
 * Agrupa eventos em pares entrada/saída por dia.
 * Retorna array de ['dia', 'entrada', 'saida'|null, 'segundos'].
 * Saída null significa entrada sem saída correspondente (em aberto).
 */
function agrupar_pares(array $eventos): array {
    // Agrupa por dia
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
                // Se havia entrada sem saída, registra como em aberto antes de sobrescrever
                if ($entrada !== null) {
                    $pares[] = [
                        'dia'      => $dia,
                        'entrada'  => $entrada,
                        'saida'    => null,
                        'segundos' => 0,
                    ];
                }
                $entrada = $ev['data_hora'];
            } elseif ($ev['tipo'] === 'saida' && $entrada !== null) {
                $diff = strtotime($ev['data_hora']) - strtotime($entrada);
                $pares[] = [
                    'dia'      => $dia,
                    'entrada'  => $entrada,
                    'saida'    => $ev['data_hora'],
                    'segundos' => max(0, $diff),
                ];
                $entrada = null;
            }
        }
        // Entrada sem saída correspondente
        if ($entrada !== null) {
            $pares[] = [
                'dia'      => $dia,
                'entrada'  => $entrada,
                'saida'    => null,
                'segundos' => 0,
            ];
        }
    }
    return $pares;
}

/**
 * Soma os segundos trabalhados de um array de pares.
 */
function calcular_total_pares(array $pares): int {
    $total = 0;
    foreach ($pares as $par) {
        $total += $par['segundos'];
    }
    return $total;
}

// --- Fetch data ---
if ($nome !== '') {
    $sql = "
        SELECT f.nome, f.cargo, f.departamento, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
        WHERE f.nome LIKE :nome
    ";
    $params = [':nome' => '%' . $nome . '%'];
    if ($data_inicio !== '' && $data_fim !== '') {
        $sql .= " AND rp.data_hora BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim']    = $data_fim    . ' 23:59:59';
    }
    $sql .= " ORDER BY rp.data_hora";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        die("Nenhum registro encontrado para o filtro informado.");
    }

    $eventos = array_map(fn($r) => ['tipo' => $r['tipo'], 'data_hora' => $r['data_hora']], $rows);
    $pares   = agrupar_pares($eventos);

    $funcionarios_pdf = [[
        'nome'  => $rows[0]['nome'],
        'cargo' => $rows[0]['cargo'] ?? '',
        'depto' => $rows[0]['departamento'] ?? '',
        'pares' => $pares,
        'total' => calcular_total_pares($pares),
    ]];
} else {
    $sql = "
        SELECT f.id, f.nome, f.cargo, f.departamento, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
    ";
    $params = [];
    if ($data_inicio !== '' && $data_fim !== '') {
        $sql .= " WHERE rp.data_hora BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim']    = $data_fim    . ' 23:59:59';
    }
    $sql .= " ORDER BY f.nome, rp.data_hora";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Group by employee
    $byEmp = [];
    foreach ($rows as $r) {
        $id = $r['id'];
        if (!isset($byEmp[$id])) {
            $byEmp[$id] = [
                'nome'    => $r['nome'],
                'cargo'   => $r['cargo']        ?? '',
                'depto'   => $r['departamento'] ?? '',
                'eventos' => [],
            ];
        }
        $byEmp[$id]['eventos'][] = ['tipo' => $r['tipo'], 'data_hora' => $r['data_hora']];
    }

    $funcionarios_pdf = [];
    foreach ($byEmp as $emp) {
        $pares = agrupar_pares($emp['eventos']);
        $funcionarios_pdf[] = [
            'nome'  => $emp['nome'],
            'cargo' => $emp['cargo'],
            'depto' => $emp['depto'],
            'pares' => $pares,
            'total' => calcular_total_pares($pares),
        ];
    }

    if (empty($funcionarios_pdf)) {
        die("Nenhum registro encontrado para o filtro informado.");
    }
}

// --- PDF class ---
class PDF extends FPDF
{
    public string $reportTitle  = '';
    public string $reportPeriod = '';

    function Header(): void
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('Relatório de Ponto'), 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        if ($this->reportTitle !== '') {
            $this->Cell(0, 7, utf8_decode($this->reportTitle), 0, 1, 'L');
        }
        if ($this->reportPeriod !== '') {
            $this->Cell(0, 7, utf8_decode($this->reportPeriod), 0, 1, 'L');
        }
        $this->Ln(3);
    }

    function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();

// Set report title and period
if ($nome !== '' && !empty($funcionarios_pdf)) {
    $f0    = $funcionarios_pdf[0];
    $title = 'Funcionário: ' . $f0['nome'];
    if ($f0['cargo'] !== '') $title .= ' | Cargo: ' . $f0['cargo'];
    if ($f0['depto'] !== '') $title .= ' | Departamento: ' . $f0['depto'];
    $pdf->reportTitle = $title;
} else {
    $pdf->reportTitle = utf8_decode('Todos os funcionários');
}

if ($data_inicio !== '' && $data_fim !== '') {
    $pdf->reportPeriod = utf8_decode('Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)));
} else {
    $pdf->reportPeriod = utf8_decode('Período: todos os registros');
}

$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Column widths (total ≈ 155mm, fits A4 portrait margins)
$wData    = 32;
$wEntrada = 38;
$wSaida   = 38;
$wTrab    = 42;
$wTotal   = $wData + $wEntrada + $wSaida;

foreach ($funcionarios_pdf as $func) {
    // Employee section header (multi-employee report)
    if ($nome === '') {
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 12);
        $label = ' ' . $func['nome'];
        if ($func['cargo'] !== '') $label .= '  —  ' . $func['cargo'];
        $pdf->Cell(0, 9, utf8_decode($label), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Ln(1);
    }

    // Table header
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($wData,    8, utf8_decode('Data'),     1, 0, 'C', true);
    $pdf->Cell($wEntrada, 8, utf8_decode('Entrada'),  1, 0, 'C', true);
    $pdf->Cell($wSaida,   8, utf8_decode('Saída'),    1, 0, 'C', true);
    $pdf->Cell($wTrab,    8, utf8_decode('Trabalhado'), 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 11);

    if (empty($func['pares'])) {
        $pdf->Cell($wData + $wEntrada + $wSaida + $wTrab, 8,
            utf8_decode('Sem registros no período'), 1, 1, 'C');
    } else {
        foreach ($func['pares'] as $par) {
            $diaFmt     = date('d/m/Y', strtotime($par['dia']));
            $entradaFmt = date('H:i:s', strtotime($par['entrada']));
            $saidaFmt   = $par['saida'] !== null
                ? date('H:i:s', strtotime($par['saida']))
                : '---';
            $trabFmt    = $par['saida'] !== null
                ? formatar_segundos($par['segundos'])
                : utf8_decode('Em aberto');

            $pdf->Cell($wData,    8, $diaFmt,     1, 0, 'C');
            $pdf->Cell($wEntrada, 8, $entradaFmt, 1, 0, 'C');
            $pdf->Cell($wSaida,   8, $saidaFmt,   1, 0, 'C');
            $pdf->Cell($wTrab,    8, $trabFmt,     1, 1, 'C');
        }
    }

    // Total row
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($wTotal, 8, utf8_decode('Total trabalhado no período:'), 1, 0, 'R');
    $pdf->Cell($wTrab,  8, formatar_segundos($func['total']),           1, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Ln(5);
}

$pdf->Output('I', 'relatorio_ponto.pdf');
exit;
?>
