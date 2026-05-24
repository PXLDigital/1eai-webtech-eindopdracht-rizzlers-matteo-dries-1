<?php
// ============================================================
// watchlist.php — PERSOON A
// Vereisten afgedekt:
//   ✓ PHP server-side (sessie, queries, status update)
//   ✓ SQL SELECT + UPDATE + DELETE
//   ✓ JavaScript client-side (filter, bevestiging)
//   ✓ jQuery + Ajax (status wijzigen zonder reload)
// ============================================================
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// Ajax: status updaten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    header('Content-Type: application/json');
    $id     = (int)($_POST['id']     ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['plan', 'watching', 'watched'])) {
        echo json_encode(['success' => false]); exit;
    }

    // SQL UPDATE
    $stmt = $pdo->prepare("UPDATE watchlist SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$status, $id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

// Ajax: film verwijderen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);

    // SQL DELETE
    $stmt = $pdo->prepare("DELETE FROM watchlist WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

// SQL SELECT: alle films ophalen
$filter = $_GET['filter'] ?? 'all';
$allowed = ['all', 'plan', 'watching', 'watched'];
if (!in_array($filter, $allowed)) $filter = 'all';

if ($filter === 'all') {
    $stmt = $pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? ORDER BY added_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? AND status = ? ORDER BY added_at DESC");
    $stmt->execute([$_SESSION['user_id'], $filter]);
}
$films = $stmt->fetchAll();

// Tellingen voor filter tabs
$counts = $pdo->prepare("SELECT status, COUNT(*) as n FROM watchlist WHERE user_id = ? GROUP BY status");
$counts->execute([$_SESSION['user_id']]);
$tellen = ['all' => 0, 'plan' => 0, 'watching' => 0, 'watched' => 0];
foreach ($counts->fetchAll() as $r) {
    $tellen[$r['status']] = $r['n'];
    $tellen['all'] += $r['n'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watchlist — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .filter-tab { padding: 0.45rem 1.1rem; border-radius: var(--radius-pill); border: 1px solid var(--clr-border); background: transparent; color: var(--clr-muted); font-family: var(--font-body); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); text-decoration: none; }
        .filter-tab:hover { border-color: var(--clr-muted); color: var(--clr-text); }
        .filter-tab.active { background: var(--clr-accent); border-color: var(--clr-accent); color: #0d0d0f; font-weight: 700; }
        .filter-tab .count { opacity: 0.7; font-size: 0.75rem; margin-left: 4px; }
        .watchlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
        .wl-card { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); overflow: hidden; position: relative; transition: border-color var(--transition); }
        .wl-card:hover { border-color: var(--clr-accent); }
        .wl-poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; background: var(--clr-surface); }
        .wl-poster-placeholder { width: 100%; aspect-ratio: 2/3; background: var(--clr-surface); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--clr-border); }
        .wl-body { padding: 0.75rem; }
        .wl-title { font-weight: 600; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.5rem; }
        .wl-select { width: 100%; padding: 0.35rem 0.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-sm); color: var(--clr-text); font-size: 0.78rem; font-family: var(--font-body); cursor: pointer; outline: none; margin-bottom: 0.4rem; }
        .wl-select:focus { border-color: var(--clr-accent); }
        .wl-delete { width: 100%; padding: 0.3rem; background: transparent; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); color: var(--clr-muted); font-size: 0.75rem; cursor: pointer; transition: all var(--transition); font-family: var(--font-body); }
        .wl-delete:hover { background: var(--clr-red); border-color: var(--clr-red); color: white; }
        .wl-rating { font-size: 0.78rem; color: var(--clr-accent); margin-bottom: 0.4rem; }
        .wl-added { font-size: 0.72rem; color: var(--clr-muted); }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="search.php">Zoeken</a></li>
            <li><a href="watchlist.php" class="active">Watchlist</a></li>
            <li><a href="stats.php">Statistieken</a></li>
            <li><a href="logout.php" class="btn btn-secondary btn-sm">Uitloggen</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Mijn <span>Watchlist</span></h1>
            <p class="page-subtitle">Welkom, <?= htmlspecialchars($_SESSION['username']) ?></p>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="?filter=all"      class="filter-tab <?= $filter === 'all'      ? 'active' : '' ?>">Alles <span class="count"><?= $tellen['all'] ?></span></a>
            <a href="?filter=plan"     class="filter-tab <?= $filter === 'plan'     ? 'active' : '' ?>">Wil ik zien <span class="count"><?= $tellen['plan'] ?></span></a>
            <a href="?filter=watching" class="filter-tab <?= $filter === 'watching' ? 'active' : '' ?>">Bezig <span class="count"><?= $tellen['watching'] ?></span></a>
            <a href="?filter=watched"  class="filter-tab <?= $filter === 'watched'  ? 'active' : '' ?>">Bekeken <span class="count"><?= $tellen['watched'] ?></span></a>
            <?php if ($tellen['watched'] > 0): ?>
                <a href="export.php" class="filter-tab" style="margin-left:auto; border-color:var(--clr-accent); color:var(--clr-accent);">📄 Export PDF</a>
            <?php endif; ?>
        </div>

        <!-- Watchlist grid -->
        <?php if (empty($films)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎬</div>
                <h3>Geen films hier</h3>
                <p><a href="search.php">Zoek een film</a> om toe te voegen.</p>
            </div>
        <?php else: ?>
            <div class="watchlist-grid" id="watchlistGrid">
                <?php foreach ($films as $film): ?>
                    <div class="wl-card" id="card-<?= $film['id'] ?>">
                        <?php if ($film['poster']): ?>
                            <img src="https://image.tmdb.org/t/p/w342<?= htmlspecialchars($film['poster']) ?>" alt="" class="wl-poster" loading="lazy">
                        <?php else: ?>
                            <div class="wl-poster-placeholder">🎬</div>
                        <?php endif; ?>
                        <div class="wl-body">
                            <div class="wl-title" title="<?= htmlspecialchars($film['title']) ?>"><?= htmlspecialchars($film['title']) ?></div>
                            <?php if ($film['rating']): ?>
                                <div class="wl-rating"><?= str_repeat('★', $film['rating']) ?><?= str_repeat('☆', 5 - $film['rating']) ?></div>
                            <?php endif; ?>
                            <!-- jQuery Ajax: status wijzigen -->
                            <select class="wl-select status-select" data-id="<?= $film['id'] ?>">
                                <option value="plan"     <?= $film['status'] === 'plan'     ? 'selected' : '' ?>>Wil ik zien</option>
                                <option value="watching" <?= $film['status'] === 'watching' ? 'selected' : '' ?>>Bezig</option>
                                <option value="watched"  <?= $film['status'] === 'watched'  ? 'selected' : '' ?>>Bekeken</option>
                            </select>
                            <div class="wl-added"><?= date('d/m/Y', strtotime($film['added_at'])) ?></div>
                            <button class="wl-delete delete-btn" data-id="<?= $film['id'] ?>">🗑 Verwijderen</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="toast"></div>
    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    // jQuery Ajax: status wijzigen
    $(document).on('change', '.status-select', function() {
        const id     = $(this).data('id');
        const status = $(this).val();

        $.ajax({
            url: 'watchlist.php',
            method: 'POST',
            data: { action: 'update_status', id: id, status: status },
            dataType: 'json',
            success: function(data) {
                if (data.success) toonToast('Status bijgewerkt!', false);
                else toonToast('Fout bij opslaan.', true);
            }
        });
    });

    // jQuery Ajax: verwijderen
    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Deze film verwijderen uit je watchlist?')) return;
        const id   = $(this).data('id');
        const card = $(`#card-${id}`);

        $.ajax({
            url: 'watchlist.php',
            method: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    card.fadeOut(300, function() { $(this).remove(); });
                    toonToast('Film verwijderd.', false);
                }
            }
        });
    });

    function toonToast(bericht, isError) {
        const t = $('#toast');
        t.text(bericht).attr('class', 'show' + (isError ? ' error' : ''));
        setTimeout(() => t.attr('class', ''), 3000);
    }
    </script>
</body>
</html>