<?php
session_start();

// Niet ingelogd? Stuur door naar login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'fpdf/fpdf.php'; // download: http://www.fpdf.org/

// =============================================
// Data ophalen uit database
// =============================================
$stmt = $pdo->prepare("
    SELECT title, status, rating, review, added_at
    FROM watchlist
    WHERE user_id = ?
    ORDER BY added_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$films = $stmt->fetchAll();

// Statistieken berekenen
$totaal   = count($films);
$bekeken  = count(array_filter($films, fn($f) => $f['status'] === 'watched'));
$bezig    = count(array_filter($films, fn($f) => $f['status'] === 'watching'));
$plan     = count(array_filter($films, fn($f) => $f['status'] === 'plan'));
$ratings  = array_filter(array_column($films, 'rating'));
$gem_rating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;

// Status vertaling
function statusLabel($status) {
    return match($status) {
        'watched'  => 'Bekeken',
        'watching' => 'Bezig',
        'plan'     => 'Wil ik zien',
        default    => $status,
    };
}

// Sterren tekst
function sterren($rating) {
    if (!$rating) return '-';
    return str_repeat('*', $rating) . str_repeat('.', 5 - $rating) . ' (' . $rating . '/5)';
}

// =============================================
// FPDF klasse uitbreiden met header en footer
// =============================================
class WatchlistPDF extends FPDF {

    public string $gebruiker = '';

    function Header() {
        // Donkere achtergrond balk bovenaan
        $this->SetFillColor(13, 13, 15);
        $this->Rect(0, 0, 210, 22, 'F');

        // Logo tekst
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(232, 184, 75); // goud
        $this->SetXY(10, 5);
        $this->Cell(40, 12, 'FILM', 0, 0);
        $this->SetTextColor(240, 237, 232); // wit
        $this->Cell(40, 12, 'TRACKER', 0, 0);

        // Gebruikersnaam rechts
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(136, 136, 153);
        $this->SetXY(0, 8);
        $this->Cell(200, 8, 'Watchlist van ' . $this->gebruiker, 0, 0, 'R');

        // Gouden lijn onder header
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

    // Gekleurde sectietitel
    function SectieTitle($tekst) {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(232, 184, 75);
        $this->SetFillColor(30, 30, 36);
        $this->Cell(0, 9, $tekst, 0, 1, 'L', true);
        $this->Ln(2);
    }

    // Speciale tekens omzetten voor FPDF (UTF-8 naar windows-1252)
    function cleanText($text) {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text ?? '');
    }

    // Statistiek blokje
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

// =============================================
// PDF aanmaken
// =============================================
$pdf = new WatchlistPDF('P', 'mm', 'A4');
$pdf->gebruiker = $_SESSION['username'];
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// --- Statistieken sectie ---
$pdf->SectieTitle('  Overzicht');
$pdf->Ln(2);

$pdf->StatBlok('Totaal',       $totaal,     12,  36);
$pdf->StatBlok('Bekeken',      $bekeken,    58,  36);
$pdf->StatBlok('Bezig',        $bezig,      104, 36);
$pdf->StatBlok('Wil ik zien',  $plan,       150, 36);

$pdf->Ln(26);

// Gem. rating apart
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(136, 136, 153);
$pdf->Cell(0, 6, 'Gemiddelde rating: ' . ($gem_rating > 0 ? $gem_rating . ' / 5' : 'Nog geen ratings'), 0, 1, 'R');
$pdf->Ln(4);

// --- Tabelhoofding ---
$pdf->SectieTitle('  Filmlijst');
$pdf->Ln(1);

// Kolomhoofden
$pdf->SetFillColor(22, 22, 26);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetTextColor(136, 136, 153);
$pdf->SetDrawColor(42, 42, 51);
$pdf->SetLineWidth(0.2);

$pdf->Cell(80, 7, 'TITEL',        'B', 0, 'L', true);
$pdf->Cell(30, 7, 'STATUS',       'B', 0, 'C', true);
$pdf->Cell(25, 7, 'RATING',       'B', 0, 'C', true);
$pdf->Cell(45, 7, 'TOEGEVOEGD',   'B', 1, 'C', true);

// Tabelrijen
$rij = 0;
foreach ($films as $film) {
    // Afwisselende rijkleur
    if ($rij % 2 === 0) {
        $pdf->SetFillColor(22, 22, 26);
    } else {
        $pdf->SetFillColor(16, 16, 18);
    }

    // Statuskleur
    $status = $film['status'];
    if ($status === 'watched') {
        $pdf->SetTextColor(61, 186, 122);  // groen
    } elseif ($status === 'watching') {
        $pdf->SetTextColor(224, 208, 82);  // geel
    } else {
        $pdf->SetTextColor(136, 136, 204); // paars
    }

    $pdf->SetFont('Helvetica', '', 8.5);

    // Titel (zwart-wit)
    $pdf->SetTextColor(240, 237, 232);
    $pdf->Cell(80, 7, $pdf->cleanText($film['title']), 0, 0, 'L', true);

    // Status
    if ($status === 'watched') {
        $pdf->SetTextColor(61, 186, 122);
    } elseif ($status === 'watching') {
        $pdf->SetTextColor(224, 208, 82);
    } else {
        $pdf->SetTextColor(136, 136, 204);
    }
    $pdf->Cell(30, 7, statusLabel($status), 0, 0, 'C', true);

    // Rating
    $pdf->SetTextColor(232, 184, 75);
    $rating_text = $film['rating'] ? $film['rating'] . ' / 5' : '-';
    $pdf->Cell(25, 7, $rating_text, 0, 0, 'C', true);

    // Datum
    $pdf->SetTextColor(136, 136, 153);
    $datum = $film['added_at'] ? date('d/m/Y', strtotime($film['added_at'])) : '-';
    $pdf->Cell(45, 7, $datum, 0, 1, 'C', true);

    // Review (indien aanwezig)
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

// =============================================
// PDF uitsturen naar browser als download
// =============================================
$bestandsnaam = 'watchlist_' . preg_replace('/[^a-z0-9]/i', '_', $_SESSION['username']) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $bestandsnaam); // 'D' = download
exit;

?>
