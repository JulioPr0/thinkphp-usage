<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ValidationException;
use app\support\Slug;
use think\facade\Db;

final class CategoryService
{
    public function listing(): array
    {
        return Db::name('categories')->alias('c')->leftJoin('articles a', 'a.category_id = c.id')
            ->field('c.id,c.name,c.slug,COUNT(a.id) AS article_count')->group('c.id,c.name,c.slug')
            ->order('c.name')->select()->toArray();
    }

    public function save(string $name, ?int $id = null): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80 || !mb_check_encoding($name, 'UTF-8')) {
            throw new ValidationException(['name' => 'Nama kategori wajib diisi, maksimal 80 karakter UTF-8.']);
        }
        Db::transaction(function () use ($name, $id): void {
            if ($id !== null && !Db::name('categories')->where('id', $id)->find()) {
                abort(404, 'Kategori tidak ditemukan.');
            }
            $duplicates = Db::name('categories')->where('name', $name);
            if ($id !== null) {
                $duplicates->where('id', '<>', $id);
            }
            if ($duplicates->count() > 0) {
                throw new ValidationException(['name' => 'Nama kategori sudah digunakan.']);
            }
            $now = date('Y-m-d H:i:s');
            if ($id !== null) {
                // Renaming keeps the category slug stable.
                Db::name('categories')->where('id', $id)->update(['name' => $name, 'updated_at' => $now]);
                return;
            }
            $base = Slug::make($name);
            $slug = $base;
            $number = 2;
            while (Db::name('categories')->where('slug', $slug)->count() > 0) {
                $slug = $base . '-' . $number++;
            }
            Db::name('categories')->insert(['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        });
    }

    public function delete(int $id): void
    {
        Db::transaction(function () use ($id): void {
            if (!Db::name('categories')->where('id', $id)->find()) {
                abort(404, 'Kategori tidak ditemukan.');
            }
            if (Db::name('articles')->where('category_id', $id)->count() > 0) {
                throw new ValidationException(['category' => 'Kategori masih digunakan. Ubah kategori artikel terlebih dahulu, termasuk artikel di sampah.']);
            }
            Db::name('categories')->where('id', $id)->delete();
        });
    }
}
