<?php
// ============================================================
// register.php — PERSOON B
// Vereisten afgedekt:
//   ✓ PHP server-side (validatie, password_hash)
//   ✓ SQL INSERT in users tabel
//   ✓ JavaScript client-side (wachtwoord sterkte, match check)
// ============================================================
session_start();

if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

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
        // SQL SELECT: controleer of email/username al bestaat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $error = 'Dit e-mailadres of deze gebruikersnaam is al in gebruik.';
        } else {
            // SQL INSERT: nieuwe gebruiker opslaan
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hash]);

            $_SESSION['user_id']  = $pdo->lastInsertId();
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
        .auth-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .brand-logo { display: block; text-align: center; font-family: var(--font-title); font-size: 2rem; letter-spacing: 3px; color: var(--clr-accent); margin-bottom: 2rem; }
        .brand-logo span { color: var(--clr-text); }
        .strength-bar { height: 4px; border-radius: 2px; background: var(--clr-border); margin-top: 0.4rem; overflow: hidden; }
        .strength-fill { height: 100%; width: 0%; border-radius: 2px; transition: width 0.3s ease, background-color 0.3s ease; }
        .strength-label { font-size: 0.75rem; margin-top: 0.3rem; color: var(--clr-muted); }
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

    <div class="auth-wrap">
        <div style="width:100%; max-width:440px;">
            <a href="index.php" class="brand-logo">FILM<span>TRACKER</span></a>
            <div class="form-card">
                <h1 class="form-title">Registreren</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php" id="registerForm">
                    <div class="form-group">
                        <label class="form-label">Gebruikersnaam</label>
                        <input class="form-input" type="text" name="username" placeholder="bijv. filmfan99" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" minlength="3" maxlength="50" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mailadres</label>
                        <input class="form-input" type="email" name="email" placeholder="jouw@email.be" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Wachtwoord</label>
                        <input class="form-input" type="password" id="password" name="password" placeholder="••••••••" minlength="6" required>
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Wachtwoord herhalen</label>
                        <input class="form-input" type="password" id="password2" name="password2" placeholder="••••••••" required>
                        <div class="form-error" id="matchError" style="display:none;">Wachtwoorden komen niet overeen.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Account aanmaken</button>
                </form>

                <div class="divider">of</div>
                <div class="form-footer">Al een account? <a href="login.php">Log hier in</a></div>
            </div>
        </div>
    </div>

    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    // JavaScript: wachtwoord sterkte indicator
    const pw  = document.getElementById('password');
    const pw2 = document.getElementById('password2');

    pw.addEventListener('input', function() {
        const v = this.value;
        let score = 0;
        if (v.length >= 6)  score++;
        if (v.length >= 10) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        const levels = [
            { label: '',           color: '',        width: '0%'   },
            { label: 'Zwak',       color: '#e05252', width: '25%'  },
            { label: 'Matig',      color: '#e0a052', width: '50%'  },
            { label: 'Goed',       color: '#e0d052', width: '75%'  },
            { label: 'Sterk',      color: '#3dba7a', width: '90%'  },
            { label: 'Zeer sterk', color: '#3dba7a', width: '100%' },
        ];
        const l = levels[Math.min(score, 5)];
        document.getElementById('strengthFill').style.cssText = `width:${l.width}; background-color:${l.color}`;
        document.getElementById('strengthLabel').textContent = l.label;
        document.getElementById('strengthLabel').style.color = l.color;
    });

    pw2.addEventListener('input', function() {
        document.getElementById('matchError').style.display =
            (this.value && this.value !== pw.value) ? 'block' : 'none';
    });

    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (pw.value !== pw2.value) {
            e.preventDefault();
            document.getElementById('matchError').style.display = 'block';
            pw2.focus();
        }
    });
    </script>
</body>
</html>
