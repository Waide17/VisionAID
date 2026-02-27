<?php
// ============================================
// app/auth/auth.php
// Funzioni di autenticazione
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Verifica se l'utente è loggato, altrimenti reindirizza al login
 */
function checkAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        $depth = substr_count($_SERVER['PHP_SELF'], '/') - 1;
        $base  = str_repeat('../', $depth);
        header('Location: ' . $base . 'login.php');
        exit;
    }
}

/**
 * Verifica se l'utente ha un ruolo specifico
 */
function checkRole(string $ruolo): bool {
    return isset($_SESSION['user_ruolo']) && $_SESSION['user_ruolo'] === $ruolo;
}

/**
 * Login: verifica email e password, imposta sessione
 */
function loginUser(string $email, string $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM utenti WHERE email = ? AND attivo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

/**
 * Dati utente corrente dalla sessione
 */
function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'nome'  => $_SESSION['user_nome']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'ruolo' => $_SESSION['user_ruolo'] ?? '',
    ];
}