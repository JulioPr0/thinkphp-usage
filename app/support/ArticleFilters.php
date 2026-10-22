<?php
declare(strict_types=1);

namespace app\support;

final class ArticleFilters
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Arsip'];
    public const SORTS = ['recent' => 'Terbaru', 'oldest' => 'Terlama', 'title' => 'Judul A–Z', 'published' => 'Waktu terbit'];

    public static function fromArray(array $input): array
    {
        $string = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $status = $string('status');
        $sort = $string('sort');
        $perPage = filter_var($input['per_page'] ?? 10, FILTER_VALIDATE_INT);
        $category = filter_var($input['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);

        return [
            'q' => mb_substr($string('q'), 0, 150),
            'status' => isset(self::STATUSES[$status]) ? $status : '',
            'sort' => isset(self::SORTS[$sort]) ? $sort : 'recent',
            'category_id' => $category !== false && $category > 0 ? min($category, 2147483647) : 0,
            'per_page' => in_array($perPage, [10, 20, 50], true) ? $perPage : 10,
            'page' => $page !== false ? max(1, min(100000, $page)) : 1,
            'view' => $string('view') === 'trash' ? 'trash' : 'active',
        ];
    }

    public static function url(array $filters, array $replace = [], string $path = '/articles'): string
    {
        $query = array_merge($filters, $replace);
        $query = array_filter($query, static fn ($value) => $value !== '' && $value !== null);
        
        return $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }
}
