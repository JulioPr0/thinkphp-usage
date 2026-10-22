<?php
declare(strict_types=1);

namespace app\support;

final class Slug
{
    public static function make(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $text));
        
        return trim(substr(trim($slug, '-'), 0, 140), '-') ?: 'artikel';
    }
}
