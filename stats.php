<?php
// ============================================================
// stats.php — PERSOON B
// Vereisten afgedekt:
//   ✓ PHP server-side (data aggregatie uit DB)
//   ✓ SQL SELECT met COUNT, AVG, GROUP BY
//   ✓ JavaScript + Chart.js (grafieken)
//   ✓ jQuery + Ajax (data live ophalen voor grafieken)
//   ✓ REST API: endpoint dat JSON teruggeeft voor extern apparaat
// ============================================================
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once 'db.php';

// ----------------------------------------------------------
// REST API endpoint: geeft statistieken als JSON terug
// Extern apparaat (smartphone) kan dit opvragen via:
//   GET stats.php?api=1&user_id=X
// Dit is de sensor/REST vereiste voor persoon B
// ----------------------------------------------------------
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $user_id = (int)($_GET['user_id'] ?? $_SESSION['user_id']);

    // SQL SELECT: overzichtsstatistieken
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as totaal,
            COUNT(CASE WHEN status = 'watched'  THEN 1 END) as bekeken,
            COUNT(CASE WHEN status = 'watching' THEN 1 END) as bezig,
            COUNT(CASE WHEN status = 'plan'     THEN 1 END) as plan,
            ROUND(AVG(rating)::numeric, 1) as gem_rating
        FROM watchlist WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    echo json_encode($stats);
    exit;
}

// ----------------------------------------------------------
// Ajax endpoint: grafiekdata ophalen
// ----------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'charts') {
    header('Content-Type: application/json');

    // SQL SELECT: verdeling per status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as n FROM watchlist WHERE user_id = ? GROUP BY status");
    $stmt->execute([$_SESSION['user_id']]);
    $status_data = $stmt->fetchAll();

    // SQL SELECT: verdeling per rating
    $stmt = $pdo->prepare("SELECT rating, COUNT(*) as n FROM watchlist WHERE user_id = ? AND rating IS NOT NULL GROUP BY rating ORDER BY rating");
    $stmt->execute([$_SESSION['user_id']]);
    $rating_data = $stmt->fetchAll();

    // SQL SELECT: films per dag (laatste 3 dagen)
    $stmt = $pdo->prepare("
        SELECT TO_CHAR(added_at, 'DD Mon') as maand, COUNT(*) as n
        FROM watchlist WHERE user_id = ?
        AND added_at >= NOW() - INTERVAL '3 days'
        GROUP BY TO_CHAR(added_at, 'DD Mon'), DATE_TRUNC('day', added_at)
        ORDER BY DATE_TRUNC('day', added_at)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $maand_data = $stmt->fetchAll();

    echo json_encode([
        'status' => $status_data,
        'rating' => $rating_data,
        'maand'  => $maand_data,
    ]);
    exit;
}

// SQL SELECT: basis statistieken voor de pagina
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as totaal,
        COUNT(CASE WHEN status = 'watched'  THEN 1 END) as bekeken,
        COUNT(CASE WHEN status = 'watching' THEN 1 END) as bezig,
        COUNT(CASE WHEN status = 'plan'     THEN 1 END) as plan,
        ROUND(AVG(rating)::numeric, 1) as gem_rating,
        COUNT(CASE WHEN rating IS NOT NULL  THEN 1 END) as aantal_rated
    FROM watchlist WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistieken — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .remote-box { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1.5rem; margin: 2rem 0; }
        .remote-title { font-family: var(--font-title); font-size: 1.4rem; letter-spacing: 1px; margin-bottom: 0.5rem; }
        .remote-desc { font-size: 0.88rem; color: var(--clr-muted); margin-bottom: 1rem; }
        .api-url { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-sm); padding: 0.6rem 1rem; font-family: monospace; font-size: 0.85rem; color: var(--clr-accent); word-break: break-all; }
        #remoteResult { margin-top: 1rem; font-size: 0.88rem; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="search.php">Zoeken</a></li>
            <li><a href="watchlist.php">Watchlist</a></li>
            <li><a href="stats.php" class="active">Statistieken</a></li>
            <li><a href="export.php" class="btn btn-secondary btn-sm">📄 PDF</a></li>
            <li><a href="logout.php" class="btn btn-secondary btn-sm">Uitloggen</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Mijn <span>Statistieken</span></h1>
            <p class="page-subtitle"><?= htmlspecialchars($_SESSION['username']) ?> — overzicht van je kijkgedrag</p>
        </div>

        <!-- Statistieken blokken -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['totaal'] ?></div>
                <div class="stat-label">Totaal</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bekeken'] ?></div>
                <div class="stat-label">Bekeken</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bezig'] ?></div>
                <div class="stat-label">Bezig</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['plan'] ?></div>
                <div class="stat-label">Wil ik zien</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['gem_rating'] ?? '—' ?></div>
                <div class="stat-label">Gem. rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['aantal_rated'] ?></div>
                <div class="stat-label">Beoordeeld</div>
            </div>
        </div>

        <!-- Grafieken (gevuld via jQuery Ajax) -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-title">Status verdeling</div>
                <canvas id="statusChart" height="220"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">Ratings verdeling</div>
                <canvas id="ratingChart" height="220"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <div class="chart-title">Toegevoegd per dag</div>
            <canvas id="maandChart" height="100"></canvas>
        </div>

        <!-- REST API sectie: extern apparaat -->
        <div class="remote-box">
            <h2 class="remote-title">📱 Bekijk op extern apparaat</h2>
            <p class="remote-desc">
                Open deze URL op je smartphone of een andere laptop om je statistieken op te vragen via de REST API:
            </p>
            <div class="api-url" id="apiUrl">
                <?php
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['PHP_SELF']);
                echo "http://{$host}{$path}/stats.php?api=1&user_id=" . $_SESSION['user_id'];
                ?>
            </div>
            <button class="btn btn-secondary btn-sm" style="margin-top:1rem;" id="testApiBtn">
                🔄 Test API antwoord
            </button>
            <div id="remoteResult"></div>
        </div>

    </main>

    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    const userId = <?= $_SESSION['user_id'] ?>;

    // Chart.js kleurenthema
    const kleuren = ['#e8b84b', '#3dba7a', '#6688ee', '#e05252', '#e0a052'];
    Chart.defaults.color = '#888899';
    Chart.defaults.borderColor = '#2a2a33';

    // jQuery Ajax: grafiekdata ophalen en renderen
    $.ajax({
        url: 'stats.php',
        method: 'GET',
        data: { ajax: 'charts' },
        dataType: 'json',
        success: function(data) {
            // Donut: status verdeling
            const statusLabels = data.status.map(r => ({ plan: 'Wil ik zien', watching: 'Bezig', watched: 'Bekeken' }[r.status] || r.status));
            const statusData   = data.status.map(r => r.n);

            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{ data: statusData, backgroundColor: kleuren, borderWidth: 2, borderColor: '#1e1e24' }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });

            // Bar: ratings
            const ratingLabels = ['1 ★', '2 ★★', '3 ★★★', '4 ★★★★', '5 ★★★★★'];
            const ratingCounts = [0, 0, 0, 0, 0];
            data.rating.forEach(r => { if (r.rating >= 1 && r.rating <= 5) ratingCounts[r.rating - 1] = parseInt(r.n); });

            new Chart(document.getElementById('ratingChart'), {
                type: 'bar',
                data: {
                    labels: ratingLabels,
                    datasets: [{ label: 'Aantal films', data: ratingCounts, backgroundColor: '#e8b84b', borderRadius: 6 }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });

            // Lijn: per maand
            const maandLabels = data.maand.map(r => r.maand);
            const maandData   = data.maand.map(r => r.n);

            new Chart(document.getElementById('maandChart'), {
                type: 'line',
                data: {
                    labels: maandLabels,
                    datasets: [{
                        label: 'Films toegevoegd',
                        data: maandData,
                        borderColor: '#e8b84b',
                        backgroundColor: 'rgba(232,184,75,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#e8b84b',
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    });

    // jQuery Ajax: REST API testen
    $('#testApiBtn').on('click', function() {
        $.ajax({
            url: 'stats.php',
            method: 'GET',
            data: { api: 1, user_id: userId },
            dataType: 'json',
            success: function(data) {
                $('#remoteResult').html(
                    '<div class="alert alert-success" style="margin-top:0.5rem;">' +
                    '✓ API antwoord: ' + JSON.stringify(data) +
                    '</div>'
                );
            },
            error: function() {
                $('#remoteResult').html('<div class="alert alert-error" style="margin-top:0.5rem;">Fout bij API call.</div>');
            }
        });
    });
    </script>
</body>
</html>
