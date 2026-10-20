<?php
// database/migrate.php
// AcadNexus Database Migration Runner CLI

require_once __DIR__ . '/../config/database.php';

class MigrationRunner {
    private $pdo;
    private $migrationsDir;

    public function __construct() {
        $this->pdo = getDBConnection();
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->ensureMigrationTable();
    }

    private function ensureMigrationTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id SERIAL PRIMARY KEY,
                version VARCHAR(50) UNIQUE NOT NULL,
                migration_name VARCHAR(255) NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                execution_time_ms INT DEFAULT 0
            )
        ");
    }

    public function getAppliedMigrations() {
        $stmt = $this->pdo->query("SELECT version FROM schema_migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getAvailableMigrations() {
        if (!is_dir($this->migrationsDir)) {
            mkdir($this->migrationsDir, 0755, true);
        }
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d+)_(.+)\.sql$/', $filename, $m)) {
                $migrations[$m[1]] = [
                    'version'  => $m[1],
                    'name'     => $m[2],
                    'filename' => $filename,
                    'path'     => $file
                ];
            }
        }
        return $migrations;
    }

    public function status() {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();

        echo "\n=== AcadNexus Database Migration Status ===\n";
        printf("%-10s %-45s %-15s\n", "Version", "Migration Name", "Status");
        echo str_repeat("-", 72) . "\n";

        foreach ($available as $version => $m) {
            $isApplied = in_array($version, $applied, true);
            $status = $isApplied ? "\033[32mApplied\033[0m" : "\033[33mPending\033[0m";
            printf("%-10s %-45s %-15s\n", $version, $m['name'], $status);
        }
        echo "\n";
    }

    public function run() {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();

        $pending = [];
        foreach ($available as $version => $m) {
            if (!in_array($version, $applied, true)) {
                $pending[$version] = $m;
            }
        }

        if (empty($pending)) {
            echo "Database is up to date. No pending migrations.\n";
            return 0;
        }

        echo "Found " . count($pending) . " pending migration(s).\n";

        foreach ($pending as $version => $m) {
            echo "Applying migration [{$version}] {$m['name']}... ";
            $sql = file_get_contents($m['path']);
            if (empty(trim($sql))) {
                echo "SKIPPED (empty file)\n";
                continue;
            }

            $startTime = microtime(true);
            try {
                $this->pdo->beginTransaction();
                $this->pdo->exec($sql);

                $elapsedMs = (int)round((microtime(true) - $startTime) * 1000);
                $rec = $this->pdo->prepare("
                    INSERT INTO schema_migrations (version, migration_name, execution_time_ms)
                    VALUES (?, ?, ?)
                ");
                $rec->execute([$version, $m['name'], $elapsedMs]);

                $this->pdo->commit();
                echo "\033[32mSUCCESS\033[0m ({$elapsedMs}ms)\n";
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                echo "\033[31mFAILED\033[0m\n";
                echo "Error: " . $e->getMessage() . "\n";
                return 1;
            }
        }

        echo "\nAll migrations applied successfully!\n";
        return 0;
    }
}

// CLI entry point
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $runner = new MigrationRunner();
    $action = $argv[1] ?? '--migrate';

    if ($action === '--status') {
        $runner->status();
    } elseif ($action === '--migrate') {
        exit($runner->run());
    } else {
        echo "Usage: php database/migrate.php [--status | --migrate]\n";
    }
}
