<?php
// ============================================================
// film-detail.php — PERSOON A
// Vereisten afgedekt:
//   ✓ PHP server-side (TMDB API call, review opslaan)
//   ✓ SQL SELECT + UPDATE (rating en review)
//   ✓ JavaScript client-side (ster-rating interactie)
//   ✓ jQuery + Ajax (review opslaan zonder reload)
// ============================================================
session_start();
require_once 'db.php';

// TMDB API configuratie
define('TMDB_KEY',  'd9220da51ccdd49872dc86d6f845da79');
define('TMDB_BASE', 'https://api.themoviedb.org/3');

$tmdb_id = (int)($_GET['id'] ?? 0);
if (!$tmdb_id) { header('Location: search.php'); exit; }

// Ajax: rating + review opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_review') {
    header('Content-Type: application/json');

    // Validatie: alleen ingelogde gebruikers kunnen reviews opslaan
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Niet ingelogd']); exit;
    }

    // Validatie: rating moet tussen 1-5 zijn
    $rating = (int)($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Geef een rating van 1-5']); exit;
    }

    // SQL UPDATE: rating en review opslaan in watchlist
    $stmt = $pdo->prepare("
        UPDATE watchlist SET rating = ?, review = ?
        WHERE user_id = ? AND tmdb_id = ?
    ");
    $stmt->execute([$rating, $review, $_SESSION['user_id'], $tmdb_id]);

    // Controleer of de update succesvol was (rowCount > 0)
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Film niet in je watchlist']); exit;
    }

    echo json_encode(['success' => true, 'message' => 'Review opgeslagen!']);
    exit;
}

// PHP: filmdetails ophalen van TMDB API
$type = $_GET['type'] ?? 'movie';
if (!in_array($type, ['movie', 'tv'])) $type = 'movie';

// Probeer eerst het opgegeven type (movie of tv)
$url      = TMDB_BASE . '/' . $type . '/' . $tmdb_id . '?api_key=' . TMDB_KEY . '&language=nl-BE&append_to_response=credits';
$response = @file_get_contents($url); // @ onderdrukt de warning bij 404
$film     = $response ? json_decode($response, true) : null;

// Als het opgegeven type niet bestaat, probeer het andere type
if (!$film || isset($film['status_code'])) {
    // Probeer het andere type (movie <-> tv)
    $andere_type = $type === 'movie' ? 'tv' : 'movie';
    $url         = TMDB_BASE . '/' . $andere_type . '/' . $tmdb_id . '?api_key=' . TMDB_KEY . '&language=nl-BE&append_to_response=credits';
    $response    = @file_get_contents($url);
    $film        = $response ? json_decode($response, true) : null;
}

// Als de film of serie nog steeds niet gevonden is, redirect naar search.php
if (!$film || isset($film['status_code'])) { header('Location: search.php'); exit; }

// PHP: variabelen voor de HTML
$titel    = $film['title'] ?? $film['name'] ?? 'Onbekend';
$jaar     = substr($film['release_date'] ?? $film['first_air_date'] ?? '', 0, 4);
$overview = $film['overview'] ?? '';
$poster   = $film['poster_path'] ?? null;
$genres   = array_column($film['genres'] ?? [], 'name');
$score    = round($film['vote_average'] ?? 0, 1);
$cast     = array_slice($film['credits']['cast'] ?? [], 0, 6);

// PHP: check of de film in de watchlist van de ingelogde gebruiker staat
$watchlist_entry = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? AND tmdb_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tmdb_id]);
    $watchlist_entry = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titel) ?> — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        .detail-hero { display: grid; grid-template-columns: 240px 1fr; gap: 3rem; padding: 3rem 0; align-items: start; }
        .detail-poster { width: 100%; border-radius: var(--radius-md); box-shadow: var(--shadow-card); }
        .detail-poster-placeholder { width: 100%; aspect-ratio: 2/3; background: var(--clr-card); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: var(--clr-border); }
        .detail-title { font-family: var(--font-title); font-size: clamp(2rem, 5vw, 3.5rem); letter-spacing: 1px; line-height: 1.1; margin-bottom: 0.75rem; }
        .detail-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; color: var(--clr-muted); font-size: 0.9rem; margin-bottom: 1rem; }
        .detail-meta span::after { content: '·'; margin: 0 0.4rem; }
        .detail-meta span:last-child::after { content: ''; }
        .detail-overview { color: var(--clr-muted); font-size: 0.95rem; max-width: 60ch; margin: 1rem 0; line-height: 1.8; }
        .genre-tag { display: inline-block; padding: 3px 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-pill); font-size: 0.78rem; color: var(--clr-muted); margin-right: 4px; margin-bottom: 4px; }
        .tmdb-score { display: inline-flex; align-items: center; gap: 0.4rem; background: var(--clr-card); border: 1px solid var(--clr-border); padding: 0.3rem 0.8rem; border-radius: var(--radius-pill); font-size: 0.85rem; }
        .review-box { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1.5rem; margin-top: 2rem; max-width: 600px; }
        .review-title { font-family: var(--font-title); font-size: 1.4rem; letter-spacing: 1px; margin-bottom: 1rem; }
        .star-rating { display: flex; gap: 6px; margin-bottom: 1rem; }
        .star { font-size: 1.8rem; cursor: pointer; transition: transform var(--transition); color: var(--clr-border); user-select: none; }
        .star.active, .star:hover { color: var(--clr-accent); transform: scale(1.1); }
        .cast-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .cast-card { text-align: center; }
        .cast-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: var(--clr-card); margin: 0 auto 0.5rem; border: 2px solid var(--clr-border); }
        .cast-name { font-size: 0.78rem; font-weight: 600; }
        .cast-char { font-size: 0.7rem; color: var(--clr-muted); }
        @media (max-width: 768px) { .detail-hero { grid-template-columns: 1fr; } .detail-poster { max-width: 200px; } }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="search.php">Zoeken</a></li>
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
        <div class="detail-hero">
            <div>
                <?php if ($poster): ?>
                    <img src="https://image.tmdb.org/t/p/w500<?= htmlspecialchars($poster) ?>" alt="" class="detail-poster">
                <?php else: ?>
                    <div class="detail-poster-placeholder">🎬</div>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="detail-title"><?= htmlspecialchars($titel) ?></h1>
                <div class="detail-meta">
                    <?php if ($jaar): ?><span><?= $jaar ?></span><?php endif; ?>
                    <?php if ($score > 0): ?><span>⭐ <?= $score ?> / 10 (TMDB)</span><?php endif; ?>
                </div>
                <div style="margin-bottom:1rem;">
                    <?php foreach ($genres as $g): ?>
                        <span class="genre-tag"><?= htmlspecialchars($g) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($overview): ?>
                    <p class="detail-overview"><?= htmlspecialchars($overview) ?></p>
                <?php endif; ?>

                <!-- Watchlist status -->
                <?php if ($watchlist_entry): ?>
                    <div class="alert alert-info" style="max-width:400px;">
                        📋 In je watchlist als <strong><?= htmlspecialchars($watchlist_entry['status']) ?></strong>
                        — <a href="watchlist.php">Bekijk watchlist</a>
                    </div>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <a href="search.php" class="btn btn-secondary btn-sm">← Terug naar zoeken om toe te voegen</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cast -->
        <?php if (!empty($cast)): ?>
        <section class="section" style="padding-top:0;">
            <h2 class="section-title">Cast</h2>
            <div class="cast-grid">
                <?php foreach ($cast as $persoon): ?>
                    <div class="cast-card">
                        <?php if (!empty($persoon['profile_path'])): ?>
                            <img src="https://image.tmdb.org/t/p/w185<?= htmlspecialchars($persoon['profile_path']) ?>" alt="" class="cast-photo" loading="lazy">
                        <?php else: ?>
                            <div class="cast-photo" style="display:flex;align-items:center;justify-content:center;font-size:1.5rem;">👤</div>
                        <?php endif; ?>
                        <div class="cast-name"><?= htmlspecialchars($persoon['name']) ?></div>
                        <div class="cast-char"><?= htmlspecialchars($persoon['character'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Review sectie (alleen als film in watchlist staat) -->
        <?php if ($watchlist_entry): ?>
        <div class="review-box">
            <h2 class="review-title">Jouw review</h2>

            <div class="star-rating" id="starRating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= ($watchlist_entry['rating'] ?? 0) >= $i ? 'active' : '' ?>"
                          data-val="<?= $i ?>">★</span>
                <?php endfor; ?>
            </div>
            <input type="hidden" id="ratingVal" value="<?= $watchlist_entry['rating'] ?? 0 ?>">

            <div class="form-group">
                <label class="form-label">Review (optioneel)</label>
                <textarea class="form-textarea" id="reviewText" placeholder="Wat vond je van deze film?"><?= htmlspecialchars($watchlist_entry['review'] ?? '') ?></textarea>
            </div>

            <button class="btn btn-primary" id="saveReviewBtn">Review opslaan</button>
            <div id="reviewMsg" style="margin-top:0.75rem; font-size:0.88rem;"></div>
        </div>
        <?php endif; ?>
    </main>

    <div id="toast"></div>
    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    const tmdbId = <?= $tmdb_id ?>;

    // JavaScript: ster-rating interactie
    $('.star').on('mouseover', function() {
        const val = $(this).data('val');
        $('.star').each(function() {
            $(this).toggleClass('active', $(this).data('val') <= val);
        });
    }).on('mouseleave', function() {
        const val = parseInt($('#ratingVal').val()) || 0;
        $('.star').each(function() {
            $(this).toggleClass('active', $(this).data('val') <= val);
        });
    }).on('click', function() {
        const val = $(this).data('val');
        $('#ratingVal').val(val);
    });

    // jQuery Ajax: review opslaan
    $('#saveReviewBtn').on('click', function() {
        const rating = parseInt($('#ratingVal').val()) || 0;
        const review = $('#reviewText').val();

        if (rating < 1) {
            $('#reviewMsg').text('Geef eerst een ster-rating.').css('color', 'var(--clr-red)');
            return;
        }

        $.ajax({
            url: 'film-detail.php?id=' + tmdbId,
            method: 'POST',
            data: { action: 'save_review', rating: rating, review: review },
            dataType: 'json',
            success: function(data) {
                const kleur = data.success ? 'var(--clr-green)' : 'var(--clr-red)';
                $('#reviewMsg').text(data.message).css('color', kleur);
            }
        });
    });
    </script>
</body>
</html>
