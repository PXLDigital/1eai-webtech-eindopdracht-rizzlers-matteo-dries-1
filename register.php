<?php
session_start();

// Al ingelogd? Stuur door naar index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // --- Validatie ---
    if (empty($username) || empty($email) || empty($password) || empty($password2)) {
        $error = 'Vul alle velden in.';

    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Gebruikersnaam moet tussen 3 en 50 tekens zijn.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vul een geldig e-mailadres in.';

    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minstens 6 tekens zijn.';

    } elseif ($password !== $password2) {
        $error = 'Wachtwoorden komen niet overeen.';

    } else {
        // Controleer of username of email al bestaat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $error = 'Dit e-mailadres of deze gebruikersnaam is al in gebruik.';
        } else {
            // Wachtwoord hashen en gebruiker opslaan
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hash]);

            // Automatisch inloggen na registratie
            $new_id = $pdo->lastInsertId();
            $_SESSION['user_id']  = $new_id;
            $_SESSION['username'] = $username;

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren — FilmTracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .register-wrap {
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

        .password-hint {
            font-size: 0.78rem;
            color: var(--clr-muted);
            margin-top: 0.3rem;
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

        /* Wachtwoord sterkte indicator */
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: var(--clr-border);
            margin-top: 0.4rem;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-label {
            font-size: 0.75rem;
            margin-top: 0.3rem;
            color: var(--clr-muted);
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="nav-logo">FILM<span>TRACKER</span></a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="login.php" class="btn btn-secondary btn-sm">Inloggen</a></li>
        </ul>
    </nav>

    <div class="register-wrap">
        <div style="width: 100%; max-width: 440px;">

            <a href="index.php" class="brand-logo">FILM<span>TRACKER</span></a>

            <div class="form-card">
                <h1 class="form-title">Registreren</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php" id="registerForm">

                    <div class="form-group">
                        <label class="form-label" for="username">Gebruikersnaam</label>
                        <input
                            class="form-input"
                            type="text"
                            id="username"
                            name="username"
                            placeholder="bijv. filmfan99"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            minlength="3"
                            maxlength="50"
                            required
                            autofocus
                        >
                    </div>

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
                            minlength="6"
                            required
                        >
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password2">Wachtwoord herhalen</label>
                        <input
                            class="form-input"
                            type="password"
                            id="password2"
                            name="password2"
                            placeholder="••••••••"
                            required
                        >
                        <div class="form-error" id="matchError" style="display:none;">
                            Wachtwoorden komen niet overeen.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:0.5rem;">
                        Account aanmaken
                    </button>

                </form>

                <div class="divider">of</div>

                <div class="form-footer">
                    Al een account?
                    <a href="login.php">Log hier in</a>
                </div>
            </div>

        </div>
    </div>

    <footer>
        <p>FilmTracker &copy; <?= date('Y') ?> — Gemaakt als schoolproject</p>
    </footer>

    <script>
        // --- Wachtwoord sterkte indicator ---
        const passwordInput = document.getElementById('password');
        const strengthFill  = document.getElementById('strengthFill');
        const strengthLabel = document.getElementById('strengthLabel');

        passwordInput.addEventListener('input', function () {
            const val = this.value;
            let score = 0;

            if (val.length >= 6)  score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { label: '',          color: '',        width: '0%'   },
                { label: 'Zwak',      color: '#e05252', width: '25%'  },
                { label: 'Matig',     color: '#e0a052', width: '50%'  },
                { label: 'Goed',      color: '#e0d052', width: '75%'  },
                { label: 'Sterk',     color: '#3dba7a', width: '90%'  },
                { label: 'Zeer sterk',color: '#3dba7a', width: '100%' },
            ];

            const level = levels[Math.min(score, 5)];
            strengthFill.style.width           = level.width;
            strengthFill.style.backgroundColor = level.color;
            strengthLabel.textContent          = level.label;
            strengthLabel.style.color          = level.color;
        });

        // --- Wachtwoord match check ---
        const password2Input = document.getElementById('password2');
        const matchError     = document.getElementById('matchError');

        password2Input.addEventListener('input', function () {
            if (this.value && this.value !== passwordInput.value) {
                matchError.style.display = 'block';
            } else {
                matchError.style.display = 'none';
            }
        });

        // --- Voorkom submit als wachtwoorden niet overeenkomen ---
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            if (passwordInput.value !== password2Input.value) {
                e.preventDefault();
                matchError.style.display = 'block';
                password2Input.focus();
            }
        });
    </script>

</body>
</html>
