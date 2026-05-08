<?php
session_start();
require_once 'db.php';

define('TMDB_KEY', 'd9220da51ccdd49872dc86d6f845da79');
define('TMDB_BASE', 'https://api.themoviedb.org/3');

// =============================================
// AJAX endpoint — live zoekresultaten
// Aangeroepen door JavaScript fetch() hieronder
// =============================================
if (isset($_GET['ajax']) && isset($_GET['q'])) {
    header('Content-Type: application/json');

    $query = trim($_GET['q']);

    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    $url = TMDB_BASE . '/search/multi?api_key=' . TMDB_KEY
         . '&query=' . urlencode($query)
         . '&language=nl-BE&page=1&include_adult=false';

    $response = file_get_contents($url);

    if ($response === false) {
        echo json_encode(['error' => 'TMDB niet bereikbaar']);
        exit;
    }

    $data    = json_decode($response, true);
    $results = [];

    foreach ($data['results'] as $item) {
        // Alleen films en series tonen
        if (!in_array($item['media_type'], ['movie', 'tv'])) continue;

        $results[] = [
            'id'         => $item['id'],
            'type'       => $item['media_type'],
            'title'      => $item['media_type'] === 'movie' ? ($item['title'] ?? '') : ($item['name'] ?? ''),
            'year'       => substr($item['media_type'] === 'movie' ? ($item['release_date'] ?? '') : ($item['first_air_date'] ?? ''), 0, 4),
            'poster'     => $item['poster_path'] ?? null,
            'overview'   => $item['overview'] ?? '',
            'rating'     => round($item['vote_average'] ?? 0, 1),
        ];

        if (count($results) >= 8) break;
    }

    echo json_encode($results);
    exit;
}

// =============================================
// AJAX endpoint — film toevoegen aan watchlist
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']);
        exit;
    }

    $tmdb_id = (int)($_POST['tmdb_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $poster  = trim($_POST['poster'] ?? '');
    $status  = $_POST['status'] ?? 'plan';

    if (!in_array($status, ['plan', 'watching', 'watched'])) {
        $status = 'plan';
    }

    if (!$tmdb_id || !$title) {
        echo json_encode(['success' => false, 'message' => 'Ongeldige data']);
        exit;
    }

    try {
        // INSERT — als film al bestaat voor deze user, update de status
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, tmdb_id, title, poster, status)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (user_id, tmdb_id)
            DO UPDATE SET status = EXCLUDED.status
        ");
        $stmt->execute([$_SESSION['user_id'], $tmdb_id, $title, $poster, $status]);

        echo json_encode(['success' => true, 'message' => '"' . $title . '" toegevoegd aan watchlist!']);
    } catch (PDOException $e) {
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
    <style>
        .search-hero {
            padding: 3rem 0 2rem;
            text-align: center;
        }

        .search-hero-title {
            font-family: var(--font-title);
            font-size: clamp(2rem, 5vw, 3.5rem);
            letter-spacing: 2px;
            margin-bottom: 1.5rem;
        }

        .search-hero-title span {
            color: var(--clr-accent);
        }

        .search-wrapper-center {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        /* Resultaten grid */
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 2rem 0;
        }

        .result-card {
            background: var(--clr-card);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: transform 0.2s ease, border-color 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .result-card:hover {
            transform: translateY(-4px);
            border-color: var(--clr-accent);
        }

        .result-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            background: var(--clr-surface);
        }

        .result-poster-placeholder {
            width: 100%;
            aspect-ratio: 2/3;
            background: var(--clr-surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--clr-border);
        }

        .result-body {
            padding: 0.9rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .result-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--clr-text);
            line-height: 1.3;
        }

        .result-meta {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            font-size: 0.78rem;
            color: var(--clr-muted);
        }

        .result-type {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            padding: 1px 7px;
            border-radius: var(--radius-pill);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .result-overview {
            font-size: 0.78rem;
            color: var(--clr-muted);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .result-actions {
            margin-top: 0.75rem;
            display: flex;
            gap: 0.4rem;
        }

        .result-actions select {
            flex: 1;
            padding: 0.4rem 0.5rem;
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            color: var(--clr-text);
            font-size: 0.78rem;
            font-family: var(--font-body);
            outline: none;
            cursor: pointer;
        }

        .result-actions select:focus {
            border-color: var(--clr-accent);
        }

        .btn-add {
            padding: 0.4rem 0.75rem;
            background: var(--clr-accent);
            color: #0d0d0f;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }

        .btn-add:hover {
            background: var(--clr-accent-dim);
        }

        .btn-add:disabled {
            background: var(--clr-border);
            color: var(--clr-muted);
            cursor: not-allowed;
        }

        /* Toast melding */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--clr-card);
            border: 1px solid var(--clr-border);
            border-left: 4px solid var(--clr-accent);
            color: var(--clr-text);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            z-index: 999;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 320px;
        }

        #toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        #toast.error {
            border-left-color: var(--clr-red);
        }

        /* Login melding */
        .login-notice {
            text-align: center;
            padding: 1rem;
            background: var(--clr-card);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            color: var(--clr-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .results-status {
            color: var(--clr-muted);
            font-size: 0.88rem;
            padding: 0.5rem 0;
            min-height: 1.5rem;
        }

        @media (max-width: 600px) {
            .results-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

    <!-- Navigatie -->
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

        <!-- Zoek hero -->
        <div class="search-hero">
            <h1 class="search-hero-title">Zoek een <span>film</span> of serie</h1>

            <div class="search-wrapper-center">
                <span class="search-icon">🔍</span>
                <input
                    type="text"
                    id="searchInput"
                    class="search-input"
                    placeholder="bijv. Inception, Breaking Bad..."
                    autocomplete="off"
                >
            </div>
        </div>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-notice">
                💡 <a href="login.php">Log in</a> of <a href="register.php">registreer</a> om films toe te voegen aan je watchlist.
            </div>
        <?php endif; ?>

        <!-- Status tekst -->
        <div class="results-status" id="resultsStatus"></div>

        <!-- Spinner -->
        <div class="spinner" id="spinner" style="display:none;"></div>

        <!-- Resultaten grid -->
        <div class="results-grid" id="resultsGrid"></div>

    </main>

    <!-- Toast melding -->
    <div id="toast"></div>

    <footer>
        <p>FilmTracker &copy; <?= date('Y') ?> — Gemaakt als schoolproject</p>
    </footer>

    <script>
        const searchInput   = document.getElementById('searchInput');
        const resultsGrid   = document.getElementById('resultsGrid');
        const resultsStatus = document.getElementById('resultsStatus');
        const spinner       = document.getElementById('spinner');
        const toast         = document.getElementById('toast');
        const isLoggedIn    = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

        let debounceTimer = null;

        // --- Live zoeken met debounce (wacht 400ms na laatste toetsaanslag) ---
        searchInput.addEventListener('input', function () {
            const query = this.value.trim();

            clearTimeout(debounceTimer);
            resultsGrid.innerHTML = '';

            if (query.length < 2) {
                resultsStatus.textContent = '';
                spinner.style.display = 'none';
                return;
            }

            spinner.style.display = 'block';
            resultsStatus.textContent = 'Zoeken...';

            debounceTimer = setTimeout(() => zoek(query), 400);
        });

        // --- Zoekfunctie via fetch (Ajax) ---
        function zoek(query) {
            fetch('search.php?ajax=1&q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    spinner.style.display = 'none';

                    if (data.error) {
                        resultsStatus.textContent = 'Fout: ' + data.error;
                        return;
                    }

                    if (data.length === 0) {
                        resultsStatus.textContent = 'Geen resultaten gevonden voor "' + query + '"';
                        return;
                    }

                    resultsStatus.textContent = data.length + ' resultaten voor "' + query + '"';
                    toonResultaten(data);
                })
                .catch(() => {
                    spinner.style.display = 'none';
                    resultsStatus.textContent = 'Er ging iets mis. Probeer opnieuw.';
                });
        }

        // --- Resultaten renderen ---
        function toonResultaten(films) {
            resultsGrid.innerHTML = '';

            films.forEach(film => {
                const poster = film.poster
                    ? `<img src="https://image.tmdb.org/t/p/w342${film.poster}" alt="${escHtml(film.title)}" class="result-poster" loading="lazy">`
                    : `<div class="result-poster-placeholder">🎬</div>`;

                const typeLabel = film.type === 'movie' ? 'Film' : 'Serie';
                const year      = film.year ? film.year : '—';
                const rating    = film.rating > 0 ? '⭐ ' + film.rating : '';

                const actionsHtml = isLoggedIn ? `
                    <div class="result-actions">
                        <select id="status-${film.id}">
                            <option value="plan">Wil ik zien</option>
                            <option value="watching">Bezig</option>
                            <option value="watched">Bekeken</option>
                        </select>
                        <button
                            class="btn-add"
                            onclick="voegToe(${film.id}, '${escJs(film.title)}', '${escJs(film.poster || '')}', '${film.type}')"
                            id="btn-${film.id}"
                        >+ Voeg toe</button>
                    </div>
                ` : '';

                const card = document.createElement('div');
                card.className = 'result-card';
                card.innerHTML = `
                    ${poster}
                    <div class="result-body">
                        <div class="result-title">${escHtml(film.title)}</div>
                        <div class="result-meta">
                            <span class="result-type">${typeLabel}</span>
                            <span>${year}</span>
                            ${rating ? `<span>${rating}</span>` : ''}
                        </div>
                        ${film.overview ? `<div class="result-overview">${escHtml(film.overview)}</div>` : ''}
                        ${actionsHtml}
                    </div>
                `;

                resultsGrid.appendChild(card);
            });
        }

        // --- Film toevoegen aan watchlist via fetch (Ajax POST) ---
        function voegToe(tmdbId, title, poster, type) {
            const btn    = document.getElementById('btn-' + tmdbId);
            const status = document.getElementById('status-' + tmdbId).value;

            btn.disabled     = true;
            btn.textContent  = '...';

            const formData = new FormData();
            formData.append('action',  'add');
            formData.append('tmdb_id', tmdbId);
            formData.append('title',   title);
            formData.append('poster',  poster);
            formData.append('status',  status);

            fetch('search.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        btn.textContent = '✓ Toegevoegd';
                        toonToast(data.message, false);
                    } else {
                        btn.disabled    = false;
                        btn.textContent = '+ Voeg toe';
                        toonToast(data.message, true);
                    }
                })
                .catch(() => {
                    btn.disabled    = false;
                    btn.textContent = '+ Voeg toe';
                    toonToast('Er ging iets mis.', true);
                });
        }

        // --- Toast melding tonen ---
        function toonToast(bericht, isError = false) {
            toast.textContent = bericht;
            toast.className   = 'show' + (isError ? ' error' : '');

            setTimeout(() => {
                toast.className = '';
            }, 3000);
        }

        // --- Hulpfuncties om XSS te voorkomen ---
        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function escJs(str) {
            return String(str).replace(/'/g, "\\'").replace(/\\/g, '\\\\');
        }
    </script>

</body>
</html>