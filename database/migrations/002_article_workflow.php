<?php
declare(strict_types=1);

use app\support\Slug;

return static function (PDO $pdo, string $driver): void {
    $columns = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW COLUMNS FROM articles')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $additions = [
        'slug' => 'VARCHAR(160) NULL',
        'category_id' => $driver === 'mysql' ? 'INT UNSIGNED NULL' : 'INTEGER NULL REFERENCES categories(id) ON DELETE RESTRICT',
        'deleted_at' => 'DATETIME NULL',
        'version' => 'INTEGER NOT NULL DEFAULT 1',
    ];
    foreach ($additions as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN $column $definition");
        }
    }
    if ($driver === 'mysql') {
        $pdo->exec("ALTER TABLE articles MODIFY status VARCHAR(20) NOT NULL DEFAULT 'draft'");
    }
    $rows = $pdo->query("SELECT id, title FROM articles WHERE slug IS NULL OR slug = ''")->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE articles SET slug = ? WHERE id = ?');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ?');
    foreach ($rows as $row) {
        $base = Slug::make($row['title']) . '-' . $row['id'];
        $slug = $base;
        $n = 1;
        do {
            $exists->execute([$slug]);
            $occupied = (int) $exists->fetchColumn() > 0;
            if ($occupied) {
                $slug = $base . '-' . $n++;
            }
        } while ($occupied);
        $update->execute([$slug, $row['id']]);
    }
    $pdo->exec("UPDATE articles SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");
    $indexes = $driver === 'sqlite'
        ? array_column($pdo->query('PRAGMA index_list(articles)')->fetchAll(PDO::FETCH_ASSOC), 'name')
        : array_column($pdo->query('SHOW INDEX FROM articles')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
    foreach ([
        'uq_articles_slug' => 'UNIQUE INDEX uq_articles_slug ON articles(slug)',
        'idx_articles_listing' => 'INDEX idx_articles_listing ON articles(deleted_at, status, published_at)',
        'idx_articles_category' => 'INDEX idx_articles_category ON articles(category_id)',
        'idx_articles_created' => 'INDEX idx_articles_created ON articles(created_at, id)',
    ] as $name => $definition) {
        if (!in_array($name, $indexes, true)) {
            $pdo->exec('CREATE ' . $definition);
        }
    }
    if ($driver === 'mysql') {
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND CONSTRAINT_NAME = 'fk_articles_category'")->fetchColumn();
        if ((int) $exists === 0) {
            $pdo->exec('ALTER TABLE articles ADD CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT');
        }
    }
};
