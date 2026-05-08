<?php
session_start();

// Haal de 6 meest recente films op uit de database als de gebruiker is ingelogd
// Anders toon je een lege array voor de hero sectie
$recent_films = [];

if (isset($_SESSION['user_id'])) {
    require_once 'db.php';

    $stmt = $pdo->prepare("
        SELECT w.tmdb_id, w.title, w.poster, w.status, w.rating
        FROM watchlist w
        WHERE w.user_id = ?
        ORDER BY w.added_at DESC
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_films = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FilmTracker — Jouw filmbibliotheek</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Hero sectie */
        .hero {
            position: relative;
            min-height: 88vh;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 70% 50%, rgba(232,184,75,0.07) 0%, transparent 70%),
                radial-gradient(ellipse 50% 80% at 20% 30%, rgba(232,184,75,0.04) 0%, transparent 60%);
            pointer-events: none;
        }

        /* Decoratieve filmstrook bovenaan */
        .filmstrip {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: repeating-linear-gradient(
                90deg,
                var(--clr-accent) 0px,
                var(--clr-accent) 18px,
                transparent 18px,
                transparent 26px
            );
            opacity: 0.6;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 640px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--clr-accent);
            margin-bottom: 1.5rem;
        }

        .hero-eyebrow::before {
            content: '';
            display: block;
            width: 28px;
            height: 2px;
            background: var(--clr-accent);
        }

        .hero-title {
            font-family: var(--font-title);
            font-size: clamp(3.5rem, 8vw, 6.5rem);
            line-height: 0.95;
            letter-spacing: 2px;
            color: var(--clr-text);
            margin-bottom: 1.5rem;
        }

        .hero-title .accent {
            color: var(--clr-accent);
            display: block;
        }

        .hero-desc {
            color: var(--clr-muted);
            font-size: 1.05rem;
            max-width: 48ch;
            margin-bottom: 2.5rem;
            line-height: 1.8;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .hero-stats {
            display: flex;
            gap: 2.5rem;
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid var(--clr-border);
        }

        .hero-stat-num {
            font-family: var(--font-title);
            font-size: 2rem;
            color: var(--clr-text);
            line-height: 1;
        }

        .hero-stat-label {
            font-size: 0.78rem;
            color: var(--clr-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Drijvende posterachtergrond rechts */
        .hero-posters {
            position: absolute;
            right: -40px;
            top: 50%;
            transform: translateY(-50%);
            display: grid;
            grid-template-columns: repeat(3, 120px);
            grid-template-rows: repeat(3, 180px);
            gap: 10px;
            opacity: 0.18;
            pointer-events: none;
            rotate: -8deg;
        }

        .hero-poster-placeholder {
            background: var(--clr-card);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            width: 120px;
            height: 180px;
        }

        .hero-poster-placeholder:nth-child(even) {
            background: var(--clr-surface);
        }

        /* Sectie: recent toegevoegd */
        .section {
            padding: 4rem 0;
        }

        .section-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .section-title {
            font-family: var(--font-title);
            font-size: 1.8rem;
            letter-spacing: 1px;
        }

        /* Feature kaartjes */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-top: 1.5rem;
        }

        .feature-card {
            background-color: var(--clr-card);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 1.75rem;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .feature-card:hover {
            border-color: var(--clr-accent);
            transform: translateY(-3px);
        }

        .feature-icon {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .feature-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--clr-text);
        }

        .feature-desc {
            font-size: 0.875rem;
            color: var(--clr-muted);
            line-height: 1.6;
        }

        /* Welkom banner voor ingelogde gebruikers */
        .welcome-banner {
            background: linear-gradient(135deg, var(--clr-card) 0%, var(--clr-surface) 100%);
            border: 1px solid var(--clr-border);
            border-left: 4px solid var(--clr-accent);
            border-radius: var(--radius-md);
            padding: 1.5rem 2rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-text strong {
            display: block;
            font-size: 1.1rem;
            color: var(--clr-text);
        }

        .welcome-text span {
            font-size: 0.875rem;
            color: var(--clr-muted);
        }

        /* Animaties bij laden */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fade-up {
            animation: fadeUp 0.6s ease both;
        }

        .fade-up:nth-child(1) { animation-delay: 0.05s; }
        .fade-up:nth-child(2) { animation-delay: 0.15s; }
        .fade-up:nth-child(3) { animation-delay: 0.25s; }
        .fade-up:nth-child(4) { animation-delay: 0.35s; }

        @media (max-width: 768px) {
            .hero {
                min-height: auto;
                padding: 4rem 0 3rem;
            }

            .hero-posters { display: none; }

            .hero-stats {
                gap: 1.5rem;
                flex-wrap: wrap;
            }

            .welcome-banner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- Navigatie -->
    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php" class="active">Home</a></li>
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

    <!-- Hero sectie -->
    <section class="hero">
        <div class="filmstrip"></div>
        <div class="hero-bg"></div>

        <!-- Decoratieve posterachtergrond -->
        <div class="hero-posters" aria-hidden="true">
            <?php for ($i = 0; $i < 9; $i++): ?>
                <div class="hero-poster-placeholder"></div>
            <?php endfor; ?>
        </div>

        <div class="container">
            <div class="hero-content">

                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Welkom terug banner -->
                    <div class="welcome-banner fade-up">
                        <div class="welcome-text">
                            <strong>👋 Welkom terug, <?= htmlspecialchars($_SESSION['username']) ?>!</strong>
                            <span>Verder kijken waar je gebleven was?</span>
                        </div>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                            <a href="watchlist.php" class="btn btn-primary btn-sm">Mijn Watchlist</a>
                            <a href="search.php" class="btn btn-secondary btn-sm">Film zoeken</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="hero-eyebrow fade-up">Jouw persoonlijke filmbibliotheek</div>

                <h1 class="hero-title fade-up">
                    Volg wat
                    <span class="accent">je kijkt.</span>
                </h1>

                <p class="hero-desc fade-up">
                    Bewaar films en series, schrijf reviews, geef sterren en bekijk je
                    kijkstatistieken — alles op één plek.
                </p>

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="hero-cta fade-up">
                        <a href="register.php" class="btn btn-primary">Gratis starten</a>
                        <a href="login.php" class="btn btn-secondary">Inloggen</a>
                    </div>
                <?php else: ?>
                    <div class="hero-cta fade-up">
                        <a href="search.php" class="btn btn-primary">🔍 Film zoeken</a>
                        <a href="stats.php" class="btn btn-secondary">Mijn statistieken</a>
                    </div>
                <?php endif; ?>

                <!-- Kleine statistieken onderaan hero -->
                <?php if (isset($_SESSION['user_id']) && !empty($recent_films)): ?>
                    <?php
                        $watched = array_filter($recent_films, fn($f) => $f['status'] === 'watched');
                        $avg_rating = count($recent_films) > 0
                            ? round(array_sum(array_column($recent_films, 'rating')) / count($recent_films), 1)
                            : 0;
                    ?>
                    <div class="hero-stats fade-up">
                        <div>
                            <div class="hero-stat-num"><?= count($recent_films) ?></div>
                            <div class="hero-stat-label">In watchlist</div>
                        </div>
                        <div>
                            <div class="hero-stat-num"><?= count($watched) ?></div>
                            <div class="hero-stat-label">Bekeken</div>
                        </div>
                        <div>
                            <div class="hero-stat-num"><?= $avg_rating > 0 ? $avg_rating : '—' ?></div>
                            <div class="hero-stat-label">Gem. rating</div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <main class="container">

        <!-- Recent toegevoegd (alleen als ingelogd en watchlist niet leeg) -->
        <?php if (isset($_SESSION['user_id']) && !empty($recent_films)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Recent toegevoegd</h2>
                <a href="watchlist.php" class="btn btn-secondary btn-sm">Alles bekijken →</a>
            </div>

            <div class="films-grid">
                <?php foreach ($recent_films as $film): ?>
                    <a href="film-detail.php?id=<?= htmlspecialchars($film['tmdb_id']) ?>" class="film-card">
                        <?php if (!empty($film['poster'])): ?>
                            <img
                                src="https://image.tmdb.org/t/p/w342<?= htmlspecialchars($film['poster']) ?>"
                                alt="<?= htmlspecialchars($film['title']) ?>"
                                class="film-card-poster"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="film-card-poster-placeholder">🎬</div>
                        <?php endif; ?>

                        <?php if ($film['status'] === 'watched'): ?>
                            <span class="film-card-badge watched">✓ Bekeken</span>
                        <?php elseif ($film['status'] === 'watching'): ?>
                            <span class="film-card-badge">▶ Bezig</span>
                        <?php endif; ?>

                        <div class="film-card-body">
                            <div class="film-card-title"><?= htmlspecialchars($film['title']) ?></div>
                            <?php if (!empty($film['rating'])): ?>
                                <div class="film-card-year">
                                    <?= str_repeat('★', $film['rating']) ?><?= str_repeat('☆', 5 - $film['rating']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Features sectie (altijd zichtbaar) -->
        <section class="section">
            <h2 class="section-title" style="margin-bottom:0.5rem;">Wat kan je doen?</h2>
            <p style="color:var(--clr-muted); font-size:0.9rem; margin-bottom:1.5rem;">
                Alles wat je nodig hebt om je filmervaring bij te houden.
            </p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <div class="feature-title">Films zoeken</div>
                    <p class="feature-desc">
                        Zoek uit miljoenen films en series via de TMDB-database. Live resultaten terwijl je typt.
                    </p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <div class="feature-title">Watchlist beheren</div>
                    <p class="feature-desc">
                        Voeg films toe als "wil ik zien", "bezig" of "bekeken". Exporteer je lijst als PDF.
                    </p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⭐</div>
                    <div class="feature-title">Beoordelen & reviewen</div>
                    <p class="feature-desc">
                        Geef een ster-rating en schrijf een persoonlijke review voor elke film die je bekeken hebt.
                    </p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title">Statistieken</div>
                    <p class="feature-desc">
                        Bekijk grafieken van je kijkgedrag: favoriete genres, gemiddelde ratings en meer.
                    </p>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer>
        <p>FilmTracker &copy; <?= date('Y') ?> — Gemaakt als schoolproject</p>
    </footer>

</body>
</html>
