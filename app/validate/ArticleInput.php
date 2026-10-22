<?php
declare(strict_types=1);

namespace app\validate;

use app\exception\ValidationException;
use app\support\ArticleFilters;
use DateTimeImmutable;

final class ArticleInput
{
    public static function validate(array $input, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $errors = [];
        $data = [];
        foreach (['title', 'slug', 'summary', 'content', 'status', 'published_at'] as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) {
                $errors[$field] = 'Isian harus berupa teks.';
                $value = '';
            }
            $data[$field] = trim($value);
            if (!mb_check_encoding($data[$field], 'UTF-8')) {
                $errors[$field] = 'Isian harus menggunakan teks UTF-8 yang valid.';
            }
        }
        if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Judul wajib diisi, maksimal 150 karakter.';
        }
        if (mb_strlen($data['summary']) > 255) {
            $errors['summary'] = 'Ringkasan maksimal 255 karakter.';
        }
        if (mb_strlen($data['content']) < 10 || strlen($data['content']) > 60000) {
            $errors['content'] = 'Konten minimal 10 karakter dan maksimal 60 KB.';
        }
        if ($data['slug'] !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['slug'])) {
            $errors['slug'] = 'Slug hanya memakai huruf kecil, angka, dan tanda hubung.';
        }
        if (strlen($data['slug']) > 160) {
            $errors['slug'] = 'Slug maksimal 160 karakter.';
        }
        if (!isset(ArticleFilters::STATUSES[$data['status']])) {
            $errors['status'] = 'Pilih status artikel yang tersedia.';
        }
        $category = $input['category_id'] ?? '';
        if ($category === '' || $category === null) {
            $data['category_id'] = null;
        } elseif (filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            $errors['category_id'] = 'Kategori tidak valid.';
        } else {
            $data['category_id'] = (int) $category;
        }
        $date = null;
        if ($data['published_at'] !== '') {
            foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s'] as $format) {
                $candidate = DateTimeImmutable::createFromFormat($format, $data['published_at'], $now->getTimezone());
                $issues = DateTimeImmutable::getLastErrors();
                if ($candidate && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                    $date = $candidate;
                    break;
                }
            }
            if (!$date || (int) $date->format('Y') < 1000) {
                $errors['published_at'] = 'Gunakan tanggal dan waktu terbit yang valid.';
            }
        }
        $data['published_at'] = match ($data['status']) {
            'published' => ($date ?? $now)->format('Y-m-d H:i:s'),
            'archived' => $date?->format('Y-m-d H:i:s'),
            default => null,
        };
        if ($errors) {
            throw new ValidationException($errors);
        }
        
        return $data;
    }
}
