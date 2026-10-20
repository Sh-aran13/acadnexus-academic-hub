<?php
$host = 'localhost';
$port = '5432';
$user = 'postgres';
$pass = '1328';

try {
    // Connect to default 'postgres' db first to create 'acadnexus' if not exists
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=postgres", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'acadnexus'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE DATABASE acadnexus");
        echo "Database acadnexus created successfully!\n";
    } else {
        echo "Database acadnexus already exists.\n";
    }
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}
