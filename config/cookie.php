<?php
return [
    'expire' => 0, 'path' => '/', 'domain' => '',
    'secure' => (bool) env('COOKIE_SECURE', false),
    'httponly' => true, 'setcookie' => true, 'samesite' => 'Lax',
];
