<?php
// login.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Se già loggato, vai alla dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

require_once 'app/config/database.php';
require_once 'app/auth/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $user = loginUser($email, $password);
        if ($user) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nome']  = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_ruolo'] = $user['ruolo'];
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error = 'Email o password non corretti.';
        }
    } else {
        $error = 'Compila tutti i campi.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi — TicketDesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/log.css">
</head>
<body>
<div class="grid-bg"></div>

<div class="login-wrap">
    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
        </div>
        <h1>Ticket<span>Desk</span></h1>
        <p>Sistema di gestione ticket</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
        <div class="alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="nome@azienda.it" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Accedi →</button>
        </form>
    </div>

    <p class="hint">Demo: <code>admin@gestionale.it</code> / <code>password</code></p>
</div>
</body>
</html>