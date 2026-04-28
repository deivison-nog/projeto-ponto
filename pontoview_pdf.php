<?php
session_start();

// ── Guard: só funciona se houver sessão ativa de funcionário ─────────────────
if (empty($_SESSION['pv_id'])) {
    header('Location: pontoview.php');
    exit;
}

require 'conexao.php';
require_once(__DIR__ . '/fpdf/fpdf.php');

// ── Dados da sessão ──────────────────────────────────────────────────────────
$funcId    = (int)$_SESSION['pv_id'];
$funcNome  = $_SESSION['pv_nome']  ?? '';
$funcCargo = $_SESSION['pv_cargo'] ?? '';
$funcDepto = $_SESSION['pv_depto'] ?? '';

// ── Parâmetros de período ────────────────────────────────────────────────────
$data_inicio = trim($_GET['data_inicio'] ?? date('Y-m-01'));
$data_fim    = trim($_GET['data_fim']    ?? date('Y-m-t'));

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatar_segundos_pvpdf(int $segundos): string {
    $h = (int)floor($segundos / 3600);
    $m = (int)floor(($segundos % 3600) / 60);
    $s = $segundos % 60;
    return sprintf('%02dh%02dm%02ds', $h, $m, $s);
}

function agrupar_pares_pvpdf(array $eventos): array {
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
                $diff   = strtotime($ev['data_hora']) - strtotime($entrada);
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

// ── Busca de dados ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT tipo, data_hora
    FROM registro_ponto
    WHERE funcionario_id = :id
      AND data_hora BETWEEN :data_inicio AND :data_fim
    ORDER BY data_hora
");
$stmt->execute([
    ':id'          => $funcId,
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim'    => $data_fim    . ' 23:59:59',
]);
$eventos  = $stmt->fetchAll();
$pares    = agrupar_pares_pvpdf($eventos);
$totalSeg = (int)array_sum(array_column($pares, 'segundos'));

// ── Classe PDF ────────────────────────────────────────────────────────────────
class PDFFuncionario extends FPDF
{
    public string $funcNome  = '';
    public string $funcCargo = '';
    public string $funcDepto = '';
    public string $periodo   = '';
    public string $geradoEm  = '';

    function Header(): void
    {
        // Faixa azul
        $this->SetFillColor(30, 90, 160);
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->SetY(4);
        $this->Cell(0, 10, utf8_decode('Relatório de Ponto — ' . $this->funcNome), 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(22);

        $this->SetFont('Arial', '', 9);
        $sub = [];
        if ($this->funcCargo !== '') $sub[] = 'Cargo: ' . $this->funcCargo;
        if ($this->funcDepto !== '') $sub[] = 'Departamento: ' . $this->funcDepto;
        if (!empty($sub)) {
            $this->Cell(0, 5, utf8_decode(implode('   |   ', $sub)), 0, 1, 'L');
        }
        $this->Cell(0, 5, utf8_decode($this->periodo), 0, 1, 'L');

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
        $this->Cell(0, 5,
            utf8_decode('Página ') . $this->PageNo() . '/{nb}  —  ' . utf8_decode($this->geradoEm),
            0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

// ── Geração ───────────────────────────────────────────────────────────────────
$pdf = new PDFFuncionario('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->funcNome  = $funcNome;
$pdf->funcCargo = $funcCargo;
$pdf->funcDepto = $funcDepto;
$pdf->periodo   = ($data_inicio !== '' && $data_fim !== '')
    ? 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))
    : 'Período: todos os registros';
$pdf->geradoEm  = 'Gerado em: ' . date('d/m/Y H:i:s');

$pdf->AddPage();

// Larguras: total útil em A4 portrait com margens 15mm = 180mm
$wData    = 35;
$wEntrada = 40;
$wSaida   = 40;
$wTrab    = 45;
$wTotal   = $wData + $wEntrada + $wSaida;

if (empty($pares)) {
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, utf8_decode('Nenhum registro encontrado para o período selecionado.'), 0, 1, 'C');
} else {
    // Cabeçalho da tabela
    $pdf->SetFillColor(200, 215, 240);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($wData,    8, utf8_decode('Data'),        1, 0, 'C', true);
    $pdf->Cell($wEntrada, 8, utf8_decode('Entrada'),     1, 0, 'C', true);
    $pdf->Cell($wSaida,   8, utf8_decode('Saída'),       1, 0, 'C', true);
    $pdf->Cell($wTrab,    8, utf8_decode('Trabalhado'),  1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 10);

    $alt = false;
    foreach ($pares as $par) {
        $diaFmt     = date('d/m/Y', strtotime($par['dia']));
        $entradaFmt = date('H:i:s', strtotime($par['entrada']));
        $saidaFmt   = $par['saida'] !== null
            ? date('H:i:s', strtotime($par['saida']))
            : '---';
        $trabFmt    = $par['saida'] !== null
            ? formatar_segundos_pvpdf($par['segundos'])
            : utf8_decode('Em aberto');

        $alt ? $pdf->SetFillColor(245, 248, 255) : $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($wData,    7, $diaFmt,     1, 0, 'C', true);
        $pdf->Cell($wEntrada, 7, $entradaFmt, 1, 0, 'C', true);
        $pdf->Cell($wSaida,   7, $saidaFmt,   1, 0, 'C', true);
        $pdf->Cell($wTrab,    7, $trabFmt,     1, 1, 'C', true);
        $alt = !$alt;
    }

    // Linha de total
    $pdf->SetFillColor(200, 215, 240);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($wTotal, 8, utf8_decode('Total trabalhado no período:'), 1, 0, 'R', true);
    $pdf->Cell($wTrab,  8, formatar_segundos_pvpdf($totalSeg),           1, 1, 'C', true);
}

$nomeArquivo = 'ponto_' . preg_replace('/[^a-z0-9]/i', '_', $funcNome) . '_' . $data_inicio . '.pdf';
$pdf->Output('I', $nomeArquivo);
exit;
?>
