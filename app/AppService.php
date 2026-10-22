<?php
declare(strict_types=1);

namespace app;

use PDO;
use think\Service;
use think\facade\Db;

class AppService extends Service
{
    public function boot(): void
    {
        Db::event('after_connect', static function (PDO $pdo): void {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA busy_timeout = 10000');
            }
        });
    }
}
