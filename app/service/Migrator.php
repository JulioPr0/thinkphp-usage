<?php
declare(strict_types=1);

namespace app\service;

use PDO;
use Throwable;

final class Migrator
{
    public function run(PDO $pdo, string $directory): array
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 10000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($directory . '/*.php') ?: [];
        sort($files);
        $completed = [];
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (in_array($version, $applied, true)) {
                continue;
            }
            if ($driver === 'sqlite') {
                $pdo->beginTransaction();
            }
            try {
                $migration = require $file;
                $migration($pdo, $driver);
                $statement = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
                $statement->execute([$version, date('Y-m-d H:i:s')]);
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $completed[] = $version;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
        
        return $completed;
    }
}
