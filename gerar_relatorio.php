<?php
require 'conexao.php';
require_once(__DIR__ . '/fpdf/fpdf.php');

// ── Parâmetros do filtro ──────────────────────────────────────────────────────
$nome        = trim($_GET['nome']        ?? '');
$cargo       = trim($_GET['cargo']       ?? '');
$data_inicio = trim($_GET['data_inicio'] ?? '');
$data_fim    = trim($_GET['data_fim']    ?? '');

// ── Helper functions ──────────────────────────────────────────────────────────

/**
 * Formata segundos como HHhMMmSSs.
 */
function formatar_segundos(int $segundos): string {
    $h = (int)floor($segundos / 3600);
    $m = (int)floor(($segundos % 3600) / 60);
    $s = $segundos % 60;
    return sprintf('%02dh%02dm%02ds', $h, $m, $s);
}

/**
 * Agrupa eventos em pares entrada/saída por dia.
 */
function agrupar_pares(array $eventos): array {
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
                if ($entrada !== null) {
                    $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => null, 'segundos' => 0];
                }
                $entrada = $ev['data_hora'];
            } elseif ($ev['tipo'] === 'saida' && $entrada !== null) {
                $diff  = strtotime($ev['data_hora']) - strtotime($entrada);
                $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => $ev['data_hora'], 'segundos' => max(0, $diff)];
                $entrada = null;
            }
        }
        if ($entrada !== null) {
            $pares[] = ['dia' => $dia, 'entrada' => $entrada, 'saida' => null, 'segundos' => 0];
        }
    }
    return $pares;
}

function calcular_total_pares(array $pares): int {
    return (int)array_sum(array_column($pares, 'segundos'));
}

// ── Busca de dados ────────────────────────────────────────────────────────────

if ($nome !== '') {
    // Relatório individual
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
        die(utf8_decode("Nenhum registro encontrado para o funcionário informado."));
    }

    $eventos = array_map(fn($r) => ['tipo' => $r['tipo'], 'data_hora' => $r['data_hora']], $rows);
    $pares   = agrupar_pares($eventos);

    $funcionarios_pdf = [[
        'nome'  => $rows[0]['nome'],
        'cargo' => $rows[0]['cargo']        ?? '',
        'depto' => $rows[0]['departamento'] ?? '',
        'pares' => $pares,
        'total' => calcular_total_pares($pares),
    ]];
    $modoRelatorio = 'individual';

} else {
    // Relatório geral (todos ou filtrado por cargo)
    $sql = "
        SELECT f.id, f.nome, f.cargo, f.departamento, rp.tipo, rp.data_hora
        FROM registro_ponto rp
        INNER JOIN funcionarios f ON f.id = rp.funcionario_id
    ";
    $params = [];
    $where  = [];

    if ($data_inicio !== '' && $data_fim !== '') {
        $where[] = "rp.data_hora BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim']    = $data_fim    . ' 23:59:59';
    }
    if ($cargo !== '') {
        $where[] = "f.cargo = :cargo";
        $params[':cargo'] = $cargo;
    }
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY f.cargo, f.nome, rp.data_hora";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

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
        die(utf8_decode("Nenhum registro encontrado para o filtro informado."));
    }
    $modoRelatorio = 'geral';
}

// ── Classe PDF ────────────────────────────────────────────────────────────────

class PDF extends FPDF
{
    public string $reportTitle  = '';
    public string $reportSubtitle = '';
    public string $reportPeriod = '';
    public string $geradoEm    = '';

    function Header(): void
    {
        // Faixa azul no topo
        $this->SetFillColor(30, 90, 160);
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->SetY(4);
        $this->Cell(0, 10, utf8_decode('Relatório de Ponto'), 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(22);

        // Subtítulo e período
        $this->SetFont('Arial', 'B', 10);
        if ($this->reportTitle !== '') {
            $this->Cell(0, 6, utf8_decode($this->reportTitle), 0, 1, 'L');
        }
        if ($this->reportSubtitle !== '') {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, utf8_decode($this->reportSubtitle), 0, 1, 'L');
        }
        $this->SetFont('Arial', '', 9);
        if ($this->reportPeriod !== '') {
            $this->Cell(0, 5, utf8_decode($this->reportPeriod), 0, 1, 'L');
        }

        // Linha separadora
        $this->SetDrawColor(30, 90, 160);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY() + 1, $this->GetPageWidth() - 10, $this->GetY() + 1);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(0, 0, 0);
        $this->Ln(5);
    }

    function Footer(): void
    {
        $this->SetY(-13);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, utf8_decode('Página ') . $this->PageNo() . '/{nb}  —  ' . utf8_decode($this->geradoEm), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Paisagem para caberem mais colunas
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->geradoEm = 'Gerado em: ' . date('d/m/Y H:i:s');

// Título do relatório
if ($modoRelatorio === 'individual' && !empty($funcionarios_pdf)) {
    $f0 = $funcionarios_pdf[0];
    $pdf->reportTitle = 'Funcionário: ' . $f0['nome'];
    $sub = [];
    if ($f0['cargo'] !== '') $sub[] = 'Cargo: ' . $f0['cargo'];
    if ($f0['depto'] !== '') $sub[] = 'Departamento: ' . $f0['depto'];
    $pdf->reportSubtitle = implode('   |   ', $sub);
} else {
    $pdf->reportTitle = $cargo !== ''
        ? 'Cargo: ' . $cargo
        : 'Todos os funcionários';
    $pdf->reportSubtitle = '';
}

$pdf->reportPeriod = ($data_inicio !== '' && $data_fim !== '')
    ? 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))
    : 'Período: todos os registros';

// ── Larguras de colunas (paisagem A4 ≈ 277mm útil) ───────────────────────────
$wData    = 28;
$wEntrada = 28;
$wSaida   = 28;
$wTrab    = 30;
$wTotal   = $wData + $wEntrada + $wSaida; // para célula de totais

// Função auxiliar: cabeçalho de tabela
function pdf_table_header(PDF $pdf, int $wData, int $wEntrada, int $wSaida, int $wTrab): void {
    $pdf->SetFillColor(200, 215, 240);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($wData,    7, utf8_decode('Data'),       1, 0, 'C', true);
    $pdf->Cell($wEntrada, 7, utf8_decode('Entrada'),    1, 0, 'C', true);
    $pdf->Cell($wSaida,   7, utf8_decode('Saída'),      1, 0, 'C', true);
    $pdf->Cell($wTrab,    7, utf8_decode('Trabalhado'), 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 9);
}

// ── Geração das páginas ───────────────────────────────────────────────────────

// Agrupa por cargo para relatório geral
$porCargo = [];
foreach ($funcionarios_pdf as $func) {
    $c = $func['cargo'] !== '' ? $func['cargo'] : '(Sem cargo)';
    $porCargo[$c][] = $func;
}
ksort($porCargo);

$primeiroCargo = true;

foreach ($porCargo as $cargoNome => $funcs) {
    // Cabeçalho de seção por cargo (apenas no relatório geral)
    if ($modoRelatorio === 'geral') {
        if ($primeiroCargo) {
            $pdf->AddPage();
            $primeiroCargo = false;
        } else {
            $pdf->AddPage();
        }
        $pdf->SetFillColor(60, 60, 60);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, utf8_decode(' Cargo: ' . $cargoNome), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
    } else {
        $pdf->AddPage();
    }

    foreach ($funcs as $idx => $func) {
        // Evita quebra de página no meio de um funcionário se possível
        // (AddPage já é chamado acima para o primeiro; para os demais no modo geral adicionamos separação)
        if ($modoRelatorio === 'geral' && $idx > 0) {
            $pdf->Ln(3);
            // Linha divisória
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line(10, $pdf->GetY(), $pdf->GetPageWidth() - 10, $pdf->GetY());
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Ln(3);
        }

        // Bloco do funcionário (nome + cargo no modo geral)
        $pdf->SetFillColor(230, 240, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $label = ' ' . $func['nome'];
        if ($modoRelatorio === 'geral' && $func['depto'] !== '') {
            $label .= '   —   Departamento: ' . $func['depto'];
        }
        $pdf->Cell(0, 7, utf8_decode($label), 0, 1, 'L', true);
        $pdf->Ln(1);

        pdf_table_header($pdf, $wData, $wEntrada, $wSaida, $wTrab);

        if (empty($func['pares'])) {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell($wData + $wEntrada + $wSaida + $wTrab, 7,
                utf8_decode('Sem registros no período'), 1, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
        } else {
            $alt = false;
            foreach ($func['pares'] as $par) {
                $diaFmt     = date('d/m/Y', strtotime($par['dia']));
                $entradaFmt = date('H:i:s', strtotime($par['entrada']));
                $saidaFmt   = $par['saida'] !== null
                    ? date('H:i:s', strtotime($par['saida']))
                    : '---';
                $trabFmt    = $par['saida'] !== null
                    ? formatar_segundos($par['segundos'])
                    : utf8_decode('Em aberto');

                if ($alt) {
                    $pdf->SetFillColor(245, 248, 255);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }
                $fill = true;
                $pdf->Cell($wData,    6, $diaFmt,     1, 0, 'C', $fill);
                $pdf->Cell($wEntrada, 6, $entradaFmt, 1, 0, 'C', $fill);
                $pdf->Cell($wSaida,   6, $saidaFmt,   1, 0, 'C', $fill);
                $pdf->Cell($wTrab,    6, $trabFmt,     1, 1, 'C', $fill);
                $alt = !$alt;
            }
        }

        // Linha de total
        $pdf->SetFillColor(200, 215, 240);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($wTotal, 7, utf8_decode('Total trabalhado no período:'), 1, 0, 'R', true);
        $pdf->Cell($wTrab,  7, formatar_segundos($func['total']),           1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Ln(2);
    }
}

// ── Página de resumo (apenas relatório geral com múltiplos funcionários) ──────
if ($modoRelatorio === 'geral' && count($funcionarios_pdf) > 1) {
    $pdf->AddPage();
    $pdf->SetFillColor(30, 90, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode(' Resumo Geral'), 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);

    // Cabeçalho da tabela resumo
    $wN   = 70;
    $wC   = 55;
    $wD   = 45;
    $wTot = 40;
    $pdf->SetFillColor(200, 215, 240);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($wN,   7, utf8_decode('Funcionário'), 1, 0, 'C', true);
    $pdf->Cell($wC,   7, utf8_decode('Cargo'),       1, 0, 'C', true);
    $pdf->Cell($wD,   7, utf8_decode('Departamento'),1, 0, 'C', true);
    $pdf->Cell($wTot, 7, utf8_decode('Total Horas'), 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 9);

    $grandTotal = 0;
    $alt = false;
    $currentCargo = null;

    foreach ($porCargo as $cargoNome => $funcs) {
        // Subtítulo por cargo
        $pdf->SetFillColor(220, 228, 245);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($wN + $wC + $wD + $wTot, 6, utf8_decode(' ' . $cargoNome), 0, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);

        foreach ($funcs as $func) {
            $fill = $alt ? [245, 248, 255] : [255, 255, 255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($wN,   6, utf8_decode($func['nome']),  1, 0, 'L', true);
            $pdf->Cell($wC,   6, utf8_decode($func['cargo']), 1, 0, 'L', true);
            $pdf->Cell($wD,   6, utf8_decode($func['depto']), 1, 0, 'L', true);
            $pdf->Cell($wTot, 6, formatar_segundos($func['total']), 1, 1, 'C', true);
            $grandTotal += $func['total'];
            $alt = !$alt;
        }
    }

    // Rodapé com total geral
    $pdf->SetFillColor(30, 90, 160);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($wN + $wC + $wD, 8, utf8_decode('  Total Geral'), 1, 0, 'R', true);
    $pdf->Cell($wTot,            8, formatar_segundos($grandTotal),   1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Output('I', 'relatorio_ponto.pdf');
exit;
?>
