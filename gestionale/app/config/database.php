<?php
// ============================================
// app/config/database.php
// Configurazione connessione database
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ticket_manager');
define('DB_USER', 'root');       // Cambia con il tuo utente MySQL
define('DB_PASS', '');           // Cambia con la tua password MySQL
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Errore connessione DB: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}