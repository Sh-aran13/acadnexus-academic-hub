<?php
// config/database.php

// Load .env file if present in project root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}

defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '5432');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'acadnexus');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'postgres');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '1328');

/**
 * Returns a shared, singleton PDO instance configured securely for PostgreSQL.
 *
 * @param bool $reset Force a new connection (useful in test suites)
 * @return PDO
 */
function getDBConnection($reset = false) {
    static $pdo = null;
    if ($pdo === null || $reset) {
        try {
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5
            ]);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            if (getenv('APP_ENV') === 'development') {
                die("Database connection failed: " . htmlspecialchars($e->getMessage()));
            }
            die("Database service temporarily unavailable. Please try again later.");
        }
    }
    return $pdo;
}
