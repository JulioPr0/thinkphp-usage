<?php
declare(strict_types=1);

return static function (PDO $pdo, string $driver): void {
    $id = $driver === 'mysql' ? 'INT UNSIGNED PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $id, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL, session_version INTEGER NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
    )$suffix");
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id $id, name VARCHAR(80) NOT NULL UNIQUE, slug VARCHAR(160) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
    )$suffix");
    $pdo->exec("CREATE TABLE IF NOT EXISTS articles (
        id $id, title VARCHAR(150) NOT NULL, summary VARCHAR(255) NULL, content TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft', published_at DATETIME NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
    )$suffix");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        throttle_key VARCHAR(64) PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0,
        window_started INTEGER NOT NULL
    )$suffix");
};
