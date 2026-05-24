<?php
// ============================================================
// login.php — PERSOON B
// Vereisten afgedekt:
//   ✓ PHP server-side (sessie, password_verify)
//   ✓ SQL SELECT uit users tabel
//   ✓ JavaScript client-side (form validatie)
// ============================================================
session_start();

if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vul alle velden in.';
    } else {
        // SQL SELECT: gebruiker opzoeken op e-mail
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
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
        .auth-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .brand-logo { display: block; text-align: center; font-family: var(--font-title); font-size: 2rem; letter-spacing: 3px; color: var(--clr-accent); margin-bottom: 2rem; }
        .brand-logo span { color: var(--clr-text); }
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

    <div class="auth-wrap">
        <div style="width:100%; max-width:440px;">
            <a href="index.php" class="brand-logo">FILM<span>TRACKER</span></a>
            <div class="form-card">
                <h1 class="form-title">Inloggen</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">E-mailadres</label>
                        <input class="form-input" type="email" name="email" placeholder="jouw@email.be" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Wachtwoord</label>
                        <input class="form-input" type="password" id="password" name="password" placeholder="••••••••" required>
                        <div class="form-error" id="pwError" style="display:none;">Vul een wachtwoord in.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:0.5rem;">Inloggen</button>
                </form>

                <div class="divider">of</div>
                <div class="form-footer">Nog geen account? <a href="register.php">Registreer hier</a></div>
            </div>
        </div>
    </div>

    <footer><p>FilmTracker &copy; <?= date('Y') ?></p></footer>

    <script>
    // JavaScript: client-side validatie
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const pw = document.getElementById('password').value;
        const err = document.getElementById('pwError');
        if (!pw) {
            e.preventDefault();
            err.style.display = 'block';
        } else {
            err.style.display = 'none';
        }
    });
    </script>
</body>
</html>
