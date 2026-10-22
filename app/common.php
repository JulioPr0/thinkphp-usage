<?php
declare(strict_types=1);

function app_escape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function article_label(array $article): string
{
    if ($article['status'] === 'published' && ($article['published_at'] ?? '') > date('Y-m-d H:i:s')) {
        return 'Terjadwal';
    }
    return \app\support\ArticleFilters::STATUSES[$article['status']] ?? 'Draft';
}

function display_date(?string $value): string
{
    return $value ? date('d M Y, H:i', strtotime($value)) : '—';
}
