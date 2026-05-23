<?php
session_start();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hero { min-height: 88vh; display: flex; align-items: center; position: relative; overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 70% 50%, rgba(232,184,75,0.07) 0%, transparent 70%); pointer-events: none; }
        .filmstrip { position: absolute; top: 0; left: 0; right: 0; height: 6px; background: repeating-linear-gradient(90deg, var(--clr-accent) 0px, var(--clr-accent) 18px, transparent 18px, transparent 26px); opacity: 0.6; }
        .hero-title { font-family: var(--font-title); font-size: clamp(3.5rem, 8vw, 6.5rem); line-height: 0.95; letter-spacing: 2px; margin-bottom: 1.5rem; }
        .hero-title .accent { color: var(--clr-accent); display: block; }
        .hero-desc { color: var(--clr-muted); font-size: 1.05rem; max-width: 48ch; margin-bottom: 2.5rem; }
        .hero-cta { display: flex; gap: 1rem; flex-wrap: wrap; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; }
        .feature-card { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 1.75rem; transition: border-color var(--transition), transform var(--transition); }
        .feature-card:hover { border-color: var(--clr-accent); transform: translateY(-3px); }
        .feature-icon { font-size: 1.8rem; margin-bottom: 1rem; }
        .feature-title { font-weight: 600; margin-bottom: 0.5rem; }
        .feature-desc { font-size: 0.875rem; color: var(--clr-muted); line-height: 1.6; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .fade-up { animation: fadeUp 0.6s ease both; }
        .fade-up:nth-child(1) { animation-delay: 0.05s; }
        .fade-up:nth-child(2) { animation-delay: 0.15s; }
        .fade-up:nth-child(3) { animation-delay: 0.25s; }
    </style>
</head>
<body>
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

    <section class="hero">
        <div class="filmstrip"></div>
        <div class="hero-bg"></div>
        <div class="container">
            <div style="max-width:640px;">
                <h1 class="hero-title fade-up">
                    Volg wat
                    <span class="accent">je kijkt.</span>
                </h1>
                <p class="hero-desc fade-up">
                    Bewaar films en series, schrijf reviews, geef sterren en bekijk je statistieken — alles op één plek.
                </p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="hero-cta fade-up">
                        <a href="register.php" class="btn btn-primary">Gratis starten</a>
                        <a href="login.php" class="btn btn-secondary">Inloggen</a>
                    </div>
                <?php else: ?>
                    <div class="hero-cta fade-up">
                        <a href="search.php" class="btn btn-primary">🔍 Film zoeken</a>
                        <a href="watchlist.php" class="btn btn-secondary">Mijn watchlist</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <main class="container">
        <section class="section">
            <h2 class="section-title">Wat kan je doen?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <div class="feature-title">Films zoeken</div>
                    <p class="feature-desc">Zoek uit miljoenen films via TMDB. Live resultaten terwijl je typt.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <div class="feature-title">Watchlist beheren</div>
                    <p class="feature-desc">Voeg films toe als "wil ik zien", "bezig" of "bekeken".</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⭐</div>
                    <div class="feature-title">Beoordelen</div>
                    <p class="feature-desc">Geef een ster-rating en schrijf een persoonlijke review.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title">Statistieken</div>
                    <p class="feature-desc">Grafieken van je kijkgedrag: genres, ratings en meer.</p>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>FilmTracker &copy; <?= date('Y') ?> — Schoolproject Webtech</p>
    </footer>
</body>
</html>
