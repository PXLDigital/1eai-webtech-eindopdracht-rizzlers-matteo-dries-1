<?php
session_start();

// Al ingelogd? Stuur door naar index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vul alle velden in.';
    } else {
        // Gebruiker opzoeken op e-mail
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Inloggen gelukt — sessie instellen
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'E-mail of wachtwoord is onjuist.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .login-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .brand-logo {
            display: block;
            text-align: center;
            font-family: var(--font-title);
            font-size: 2rem;
            letter-spacing: 3px;
            color: var(--clr-accent);
            margin-bottom: 2rem;
        }

        .brand-logo span {
            color: var(--clr-text);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--clr-muted);
            font-size: 0.8rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--clr-border);
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="register.php" class="btn btn-primary btn-sm">Registreren</a></li>
        </ul>
    </nav>

    <div class="login-wrap">
        <div style="width: 100%; max-width: 440px;">

            <a href="index.php" class="brand-logo">FILM<span>TRACKER</span></a>

            <div class="form-card">
                <h1 class="form-title">Inloggen</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">

                    <div class="form-group">
                        <label class="form-label" for="email">E-mailadres</label>
                        <input
                            class="form-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="jouw@email.be"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Wachtwoord</label>
                        <input
                            class="form-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:0.5rem;">
                        Inloggen
                    </button>

                </form>

                <div class="divider">of</div>

                <div class="form-footer">
                    Nog geen account?
                    <a href="register.php">Registreer hier</a>
                </div>
            </div>

        </div>
    </div>

    <footer>
        <p>FilmTracker &copy; <?= date('Y') ?> — Gemaakt als schoolproject</p>
    </footer>

</body>
</html>

