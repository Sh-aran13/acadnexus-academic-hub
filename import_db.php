<?php
$host = 'localhost';
$port = '5432';
$dbname = 'acadnexus';
$user = 'postgres';
$pass = '1328';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $sql = file_get_contents(__DIR__ . '/database.sql');
    $pdo->exec($sql);
    echo "database.sql imported successfully into PostgreSQL acadnexus database!\n";
} catch (PDOException $e) {
    echo "Database import error: " . $e->getMessage() . "\n";
}
