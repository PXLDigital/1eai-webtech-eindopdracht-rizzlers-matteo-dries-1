<?php
// =============================================
// db.php — PostgreSQL connectie via PDO
// Gedeeld door beide personen
// =============================================

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'filmtracker');
define('DB_USER', 'postgres');
define('DB_PASS', 'admin'); // ← aanpassen!

try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('DB connectie mislukt: ' . $e->getMessage());
    die('Databasefout. Probeer later opnieuw.');
}
