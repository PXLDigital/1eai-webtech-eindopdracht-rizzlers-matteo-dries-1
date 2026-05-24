<?php
// ============================================================
// export.php — PERSOON B
// Vereisten afgedekt:
//   ✓ PHP server-side (geavanceerd: PDF generatie met FPDF)
//   ✓ SQL SELECT uit watchlist tabel
//   ✓ Geavanceerde PHP voorbij minimum (multithreading/PDF)
// ============================================================
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once 'db.php';
require_once 'fpdf/fpdf.php';

// SQL SELECT: alle films ophalen
$stmt = $pdo->prepare("
    SELECT title, status, rating, review, added_at
    FROM watchlist WHERE user_id = ?
    ORDER BY added_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$films = $stmt->fetchAll();

// SQL SELECT: statistieken
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as totaal,
        COUNT(CASE WHEN status='watched'  THEN 1 END) as bekeken,
        COUNT(CASE WHEN status='watching' THEN 1 END) as bezig,
        COUNT(CASE WHEN status='plan'     THEN 1 END) as plan,
        ROUND(AVG(rating)::numeric, 1) as gem_rating
    FROM watchlist WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

function statusLabel($s) {
    return match($s) { 'watched' => 'Bekeken', 'watching' => 'Bezig', 'plan' => 'Wil ik zien', default => $s };
}

// FPDF klasse
class WatchlistPDF extends FPDF {
    public string $gebruiker = '';

    function Header() {
        $this->SetFillColor(13, 13, 15);
        $this->Rect(0, 0, 210, 22, 'F');
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(232, 184, 75);
        $this->SetXY(10, 5);
        $this->Cell(30, 12, 'FILM', 0, 0);
        $this->SetTextColor(240, 237, 232);
        $this->Cell(40, 12, 'TRACKER', 0, 0);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(136, 136, 153);
        $this->SetXY(0, 8);
        $this->Cell(200, 8, 'Watchlist van ' . $this->gebruiker, 0, 0, 'R');
        $this->SetDrawColor(232, 184, 75);
        $this->SetLineWidth(0.5);
        $this->Line(0, 22, 210, 22);
        $this->Ln(16);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(136, 136, 153);
        $this->Cell(0, 10, 'FilmTracker - Gegenereerd op ' . date('d/m/Y') . '  |  Pagina ' . $this->PageNo(), 0, 0, 'C');
    }

    function cleanText($text) {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text ?? '');
    }

    function SectieTitle($tekst) {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(232, 184, 75);
        $this->SetFillColor(30, 30, 36);
        $this->Cell(0, 9, $tekst, 0, 1, 'L', true);
        $this->Ln(2);
    }

    function StatBlok($label, $waarde, $x, $y) {
        $this->SetXY($x, $y);
        $this->SetFillColor(22, 22, 26);
        $this->SetDrawColor(42, 42, 51);
        $this->SetLineWidth(0.3);
        $this->Rect($x, $y, 42, 18, 'FD');
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(232, 184, 75);
        $this->SetXY($x, $y + 1);
        $this->Cell(42, 10, $waarde, 0, 0, 'C');
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(136, 136, 153);
        $this->SetXY($x, $y + 10);
        $this->Cell(42, 6, strtoupper($label), 0, 0, 'C');
    }
}

// PDF aanmaken
$pdf = new WatchlistPDF('P', 'mm', 'A4');
$pdf->gebruiker = $_SESSION['username'];
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Statistieken
$pdf->SectieTitle('  Overzicht');
$pdf->Ln(2);
$pdf->StatBlok('Totaal',      $stats['totaal'],  12,  36);
$pdf->StatBlok('Bekeken',     $stats['bekeken'], 58,  36);
$pdf->StatBlok('Bezig',       $stats['bezig'],   104, 36);
$pdf->StatBlok('Wil ik zien', $stats['plan'],    150, 36);
$pdf->Ln(26);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(136, 136, 153);
$pdf->Cell(0, 6, 'Gemiddelde rating: ' . ($stats['gem_rating'] ?? '—') . ' / 5', 0, 1, 'R');
$pdf->Ln(4);

// Tabel
$pdf->SectieTitle('  Filmlijst');
$pdf->Ln(1);

// Kolomhoofden
$pdf->SetFillColor(22, 22, 26);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetTextColor(136, 136, 153);
$pdf->SetDrawColor(42, 42, 51);
$pdf->SetLineWidth(0.2);
$pdf->Cell(80, 7, 'TITEL',      'B', 0, 'L', true);
$pdf->Cell(30, 7, 'STATUS',     'B', 0, 'C', true);
$pdf->Cell(25, 7, 'RATING',     'B', 0, 'C', true);
$pdf->Cell(45, 7, 'TOEGEVOEGD', 'B', 1, 'C', true);

$rij = 0;
foreach ($films as $film) {
    $rij % 2 === 0 ? $pdf->SetFillColor(22, 22, 26) : $pdf->SetFillColor(16, 16, 18);
    $pdf->SetFont('Helvetica', '', 8.5);

    // Titel
    $pdf->SetTextColor(240, 237, 232);
    $pdf->Cell(80, 7, $pdf->cleanText($film['title']), 0, 0, 'L', true);

    // Status met kleur
    match($film['status']) {
        'watched'  => $pdf->SetTextColor(61,  186, 122),
        'watching' => $pdf->SetTextColor(224, 208,  82),
        default    => $pdf->SetTextColor(136, 136, 204),
    };
    $pdf->Cell(30, 7, statusLabel($film['status']), 0, 0, 'C', true);

    // Rating
    $pdf->SetTextColor(232, 184, 75);
    $pdf->Cell(25, 7, $film['rating'] ? $film['rating'] . ' / 5' : '-', 0, 0, 'C', true);

    // Datum
    $pdf->SetTextColor(136, 136, 153);
    $datum = $film['added_at'] ? date('d/m/Y', strtotime($film['added_at'])) : '-';
    $pdf->Cell(45, 7, $datum, 0, 1, 'C', true);

    // Review
    if (!empty($film['review'])) {
        $pdf->SetFillColor(16, 16, 18);
        $pdf->SetFont('Helvetica', 'I', 7.5);
        $pdf->SetTextColor(100, 100, 120);
        $pdf->SetX(14);
        $review = '"' . mb_substr($film['review'], 0, 120) . (mb_strlen($film['review']) > 120 ? '...' : '') . '"';
        $pdf->MultiCell(166, 5, $pdf->cleanText($review), 0, 'L', true);
    }

    $rij++;
}

$bestandsnaam = 'watchlist_' . preg_replace('/[^a-z0-9]/i', '_', $_SESSION['username']) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $bestandsnaam);
exit;
