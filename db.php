<?php
// =============================================
// db.php — PostgreSQL connectie via PDO
// Zet dit bestand in de ROOT van je project
// en include het in elke pagina die de DB nodig heeft:
//   require_once 'db.php';
// =============================================

// --- Instellingen — pas aan naar jouw situatie ---
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');          // standaard PostgreSQL poort
define('DB_NAME', 'filmtracker');   // naam van jouw database
define('DB_USER', 'postgres');      // jouw PostgreSQL gebruikersnaam
define('DB_PASS', 'admin'); // jouw PostgreSQL wachtwoord

// --- Connectie aanmaken ---
try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST, DB_PORT, DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // gooit exceptions bij fouten
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // altijd associatieve arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                   // echte prepared statements
    ]);

} catch (PDOException $e) {
    // Toon NOOIT de echte foutmelding aan de gebruiker in productie!
    error_log('DB connectie mislukt: ' . $e->getMessage());
    die('Er is een databasefout opgetreden. Probeer later opnieuw.');
}