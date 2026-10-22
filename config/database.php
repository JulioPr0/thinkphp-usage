<?php
declare(strict_types=1);

$database = (string) env('DB_DATABASE', 'database/app.sqlite');
if ($database !== ':memory:' && !preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $database)) {
    $database = root_path() . $database;
}

return [
    'default' => env('DB_DRIVER', 'sqlite'),
    'auto_timestamp' => false,
    'datetime_format' => 'Y-m-d H:i:s',
    'connections' => [
        'sqlite' => [
            'type' => 'sqlite', 'database' => $database, 'prefix' => '',
            'fields_strict' => true, 'fields_cache' => false,
            'trigger_sql' => (bool) env('APP_DEBUG', false),
            'params' => [PDO::ATTR_TIMEOUT => 10],
        ],
        'mysql' => [
            'type' => 'mysql', 'hostname' => env('DB_HOST', '127.0.0.1'),
            'hostport' => env('DB_PORT', '3306'), 'database' => env('DB_NAME', 'articles_db'),
            'username' => env('DB_USER', 'articles_app'), 'password' => env('DB_PASS', ''),
            'charset' => 'utf8mb4', 'prefix' => '', 'fields_strict' => true,
            'fields_cache' => false, 'break_reconnect' => false,
            'trigger_sql' => (bool) env('APP_DEBUG', false),
        ],
    ],
];
