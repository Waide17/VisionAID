<?php
require_once __DIR__ . '/../config/database.php';

// Funzione login
function loginUser($email, $password) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user; // ritorna array utente
    }
    return false;
}

// Funzione middleware per proteggere pagine
function checkAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}
