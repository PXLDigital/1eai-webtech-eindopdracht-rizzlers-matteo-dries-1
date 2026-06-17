<?php
// ============================================================
// search.php — PERSOON A
// Vereisten afgedekt:
//   ✓ PHP server-side (TMDB API call, watchlist opslaan)
//   ✓ SQL INSERT + SELECT
//   ✓ JavaScript client-side (debounce, render, escaping)
//   ✓ jQuery + Ajax (live zoeken + toevoegen)
//   ✓ REST API extern apparaat (smartphone voegt film toe via POST)
// ============================================================
session_start();
require_once 'db.php';

define('TMDB_KEY',  'd9220da51ccdd49872dc86d6f845da79');
define('TMDB_BASE', 'https://api.themoviedb.org/3');

// ----------------------------------------------------------
// AJAX 1: Live zoeken via jQuery $.ajax()
// ----------------------------------------------------------
if (isset($_GET['ajax']) && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $query = trim($_GET['q']);

    if (strlen($query) < 2) { echo json_encode([]); exit; }

    $url      = TMDB_BASE . '/search/multi?api_key=' . TMDB_KEY . '&query=' . urlencode($query) . '&language=nl-BE&include_adult=false';
    $response = file_get_contents($url);

    if (!$response) { echo json_encode(['error' => 'TMDB niet bereikbaar']); exit; }

    $data    = json_decode($response, true);
    $results = [];

    foreach ($data['results'] as $item) {
        if (!in_array($item['media_type'], ['movie', 'tv'])) continue;
        $results[] = [
            'id'       => $item['id'],
            'type'     => $item['media_type'],
            'title'    => $item['media_type'] === 'movie' ? ($item['title'] ?? '') : ($item['name'] ?? ''),
            'year'     => substr($item['media_type'] === 'movie' ? ($item['release_date'] ?? '') : ($item['first_air_date'] ?? ''), 0, 4),
            'poster'   => $item['poster_path'] ?? null,
            'overview' => $item['overview'] ?? '',
            'rating'   => round($item['vote_average'] ?? 0, 1),
        ];
        if (count($results) >= 8) break;
    }

    echo json_encode($results);
    exit;
}

// ----------------------------------------------------------
// AJAX 2: Film toevoegen aan watchlist via jQuery $.ajax()
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']); exit;
    }

    $tmdb_id = (int)($_POST['tmdb_id'] ?? 0);
    $title   = trim($_POST['title']   ?? '');
    $poster  = trim($_POST['poster']  ?? '');
    $status  = in_array($_POST['status'] ?? '', ['plan','watching','watched']) ? $_POST['status'] : 'plan';

    if (!$tmdb_id || !$title) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige data']); exit;
    }

    try {
        // SQL INSERT — als film al bestaat: update status
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, tmdb_id, title, poster, status)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (user_id, tmdb_id) DO UPDATE SET status = EXCLUDED.status
        ");
        $stmt->execute([$_SESSION['user_id'], $tmdb_id, $title, $poster, $status]);
        echo json_encode(['success' => true, 'message' => '"' . $title . '" toegevoegd!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Databasefout']);
    }
    exit;
}

// ----------------------------------------------------------
// REST API endpoint voor EXTERN APPARAAT (smartphone)
// Smartphone stuurt: POST search.php met action=remote_add
// Dit is de sensor-vereiste: extern apparaat communiceert via REST
// ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remote_add') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // toestaan van extern apparaat

    $user_id = (int)($_POST['user_id'] ?? 0);
    $tmdb_id = (int)($_POST['tmdb_id'] ?? 0);
    $title   = trim($_POST['title']   ?? '');
    $poster  = trim($_POST['poster']  ?? '');

    if (!$user_id || !$tmdb_id || !$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ongeldige data']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, tmdb_id, title, poster, status)
            VALUES (?, ?, ?, ?, 'plan')
            ON CONFLICT (user_id, tmdb_id) DO NOTHING
        ");
        $stmt->execute([$user_id, $tmdb_id, $title, $poster]);
        echo json_encode(['success' => true, 'message' => 'Film toegevoegd via extern apparaat']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Databasefout']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoeken — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <!-- jQuery library -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        .search-hero { padding: 3rem 0 2rem; text-align: center; }
        .search-hero-title { font-family: var(--font-title); font-size: clamp(2rem, 5vw, 3.5rem); letter-spacing: 2px; margin-bottom: 1.5rem; }
        .search-hero-title span { color: var(--clr-accent); }
        .search-wrapper-center { max-width: 600px; margin: 0 auto; position: relative; }
        .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; padding: 2rem 0; }
        .result-card { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); overflow: hidden; transition: transform var(--transition), border-color var(--transition); display: flex; flex-direction: column; }
        .result-card:hover { transform: translateY(-4px); border-color: var(--clr-accent); }
        .result-poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; background: var(--clr-surface); }
        .result-poster-placeholder { width: 100%; aspect-ratio: 2/3; background: var(--clr-surface); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--clr-border); }
        .result-body { padding: 0.9rem; flex: 1; display: flex; flex-direction: column; gap: 0.4rem; }
        .result-title { font-weight: 600; font-size: 0.9rem; line-height: 1.3; }
        .result-meta { display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--clr-muted); }
        .result-type { background: var(--clr-surface); border: 1px solid var(--clr-border); padding: 1px 7px; border-radius: var(--radius-pill); font-size: 0.7rem; text-transform: uppercase; }
        .result-overview { font-size: 0.78rem; color: var(--clr-muted); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
        .result-actions { margin-top: 0.75rem; display: flex; gap: 0.4rem; }
        .result-actions select { flex: 1; padding: 0.4rem 0.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-sm); color: var(--clr-text); font-size: 0.78rem; font-family: var(--font-body); outline: none; cursor: pointer; }
        .result-actions select:focus { border-color: var(--clr-accent); }
        .btn-add { padding: 0.4rem 0.75rem; background: var(--clr-accent); color: #0d0d0f; border: none; border-radius: var(--radius-sm); font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: background var(--transition); white-space: nowrap; }
        .btn-add:hover { background: var(--clr-accent-dim); }
        .btn-add:disabled { background: var(--clr-border); color: var(--clr-muted); cursor: not-allowed; }
        .results-status { color: var(--clr-muted); font-size: 0.88rem; padding: 0.5rem 0; min-height: 1.5rem; }
        .login-notice { text-align: center; padding: 1rem; background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); color: var(--clr-muted); font-size: 0.9rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="search.php" class="active">Zoeken</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="watchlist.php">Watchlist</a></li>
                <li><a href="stats.php">Statistieken</a></li>
                <li><a href="logout.php" class="btn btn-secondary btn-sm">Uitloggen</a></li>
            <?php else: ?>
                <li><a href="login.php">Inloggen</a></li>
                <li><a href="register.php" class="btn btn-primary btn-sm">Registreren</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main class="container">
        <div class="search-hero">
            <h1 class="search-hero-title">Zoek een <span>film</span> of serie</h1>
            <div class="search-wrapper-center">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" class="search-input" placeholder="bijv. Inception, Breaking Bad..." autocomplete="off">
            </div>
        </div>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-notice">
                💡 <a href="login.php">Log in</a> of <a href="register.php">registreer</a> om films toe te voegen.
            </div>
        <?php endif; ?>

        <div class="results-status" id="resultsStatus"></div>
        <div class="spinner" id="spinner" style="display:none;"></div>
        <div class="results-grid" id="resultsGrid"></div>
    </main>

    <div id="toast"></div>
    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
    let debounceTimer = null;

    // jQuery: live zoeken met debounce
    $('#searchInput').on('input', function() {
        const query = $(this).val().trim();
        clearTimeout(debounceTimer);
        $('#resultsGrid').empty();

        if (query.length < 2) {
            $('#resultsStatus').text('');
            $('#spinner').hide();
            return;
        }

        $('#spinner').show();
        $('#resultsStatus').text('Zoeken...');

        debounceTimer = setTimeout(() => zoek(query), 400);
    });

    // jQuery Ajax: zoekresultaten ophalen
    function zoek(query) {
        $.ajax({
            url: 'search.php',
            method: 'GET',
            data: { ajax: 1, q: query },
            dataType: 'json',
            success: function(data) {
                $('#spinner').hide();
                if (data.error) {
                    $('#resultsStatus').text('Fout: ' + data.error);
                    return;
                }
                if (data.length === 0) {
                    $('#resultsStatus').text('Geen resultaten voor "' + query + '"');
                    return;
                }
                $('#resultsStatus').text(data.length + ' resultaten voor "' + query + '"');
                toonResultaten(data);
            },
            error: function() {
                $('#spinner').hide();
                $('#resultsStatus').text('Er ging iets mis. Probeer opnieuw.');
            }
        });
    }

    function toonResultaten(films) {
        $('#resultsGrid').empty();
        $.each(films, function(i, film) {
            const poster = film.poster
                ? `<img src="https://image.tmdb.org/t/p/w342${film.poster}" alt="${escHtml(film.title)}" class="result-poster" loading="lazy">`
                : `<div class="result-poster-placeholder">🎬</div>`;

            const actionsHtml = isLoggedIn ? `
                <div class="result-actions">
                    <select id="status-${film.id}">
                        <option value="plan">Wil ik zien</option>
                        <option value="watching">Bezig</option>
                        <option value="watched">Bekeken</option>
                    </select>
                    <button class="btn-add" id="btn-${film.id}" onclick="voegToe(${film.id}, '${escJs(film.title)}', '${escJs(film.poster || '')}')">
                        + Voeg toe
                    </button>
                </div>` : '';

            const card = `
                <a class="result-card" href="film-detail.php?id=${film.id}&type=${film.type}" style="text-decoration:none; color:inherit;">
                    ${poster}
                    <div class="result-body">
                        <div class="result-title">${escHtml(film.title)}</div>
                        <div class="result-meta">
                            <span class="result-type">${film.type === 'movie' ? 'Film' : 'Serie'}</span>
                            <span>${film.year || '—'}</span>
                            ${film.rating > 0 ? `<span>⭐ ${film.rating}</span>` : ''}
                        </div>
                        ${film.overview ? `<div class="result-overview">${escHtml(film.overview)}</div>` : ''}
                        ${actionsHtml}
                    </div>
                </a>`;
            $('#resultsGrid').append(card);
        });
    }

    // jQuery Ajax: film toevoegen
    function voegToe(tmdbId, title, poster) {
        const status = $(`#status-${tmdbId}`).val();
        const btn    = $(`#btn-${tmdbId}`);

        btn.prop('disabled', true).text('...');

        $.ajax({
            url: 'search.php',
            method: 'POST',
            data: { action: 'add', tmdb_id: tmdbId, title: title, poster: poster, status: status },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    btn.text('✓ Toegevoegd');
                    toonToast(data.message, false);
                } else {
                    btn.prop('disabled', false).text('+ Voeg toe');
                    toonToast(data.message, true);
                }
            },
            error: function() {
                btn.prop('disabled', false).text('+ Voeg toe');
                toonToast('Er ging iets mis.', true);
            }
        });
    }

    function toonToast(bericht, isError) {
        const t = $('#toast');
        t.text(bericht).attr('class', 'show' + (isError ? ' error' : ''));
        setTimeout(() => t.attr('class', ''), 3000);
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function escJs(str) {
        return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }
    </script>
</body>
</html>
