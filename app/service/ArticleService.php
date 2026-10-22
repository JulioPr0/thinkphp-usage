<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ConflictException;
use app\exception\ValidationException;
use app\support\ArticleFilters;
use app\support\Slug;
use app\validate\ArticleInput;
use think\facade\Db;

final class ArticleService
{
    public function listing(array $filters, bool $public = false): array
    {
        $query = $this->query($filters, $public);
        $total = (clone $query)->count('a.id');
        $pages = max(1, (int) ceil($total / $filters['per_page']));
        $page = min($filters['page'], $pages);
        $rows = $query->field('a.*, c.name AS category_name')->page($page, $filters['per_page'])->select()->toArray();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function query(array $filters, bool $public = false)
    {
        $query = Db::name('articles')->alias('a')->leftJoin('categories c', 'c.id = a.category_id');
        if (!$public && $filters['view'] === 'trash') {
            $query->whereNotNull('a.deleted_at');
        } else {
            $query->whereNull('a.deleted_at');
        }
        if ($public) {
            $query->where('a.status', 'published')->where('a.published_at', '<=', date('Y-m-d H:i:s'));
        } elseif ($filters['status'] !== '') {
            $query->where('a.status', $filters['status']);
        }
        if ($filters['category_id'] > 0) {
            $query->where('a.category_id', $filters['category_id']);
        }
        if ($filters['q'] !== '') {
            $needle = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $query->whereRaw("(a.title LIKE :search_title ESCAPE '!' OR a.summary LIKE :search_summary ESCAPE '!' OR a.content LIKE :search_content ESCAPE '!')", [
                'search_title' => $needle, 'search_summary' => $needle, 'search_content' => $needle,
            ]);
        }
        // Sorting starts here, with no preceding default order that could override it.
        switch ($filters['sort']) {
            case 'title': $query->order('a.title', 'asc'); break;
            case 'oldest': $query->order('a.created_at', 'asc'); break;
            case 'published': $query->order('a.published_at', 'desc'); break;
            default: $query->order('a.created_at', 'desc');
        }
        return $query->order('a.id', 'desc');
    }

    public function find(int $id, bool $trashed = false): array
    {
        $query = Db::name('articles')->where('id', $id);
        $trashed ? $query->whereNotNull('deleted_at') : $query->whereNull('deleted_at');
        $article = $query->find();
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan.');
        }
        return $article;
    }

    public function save(array $input, ?int $id = null): int
    {
        $data = ArticleInput::validate($input);
        return Db::transaction(function () use ($data, $input, $id): int {
            $current = $id !== null ? $this->find($id) : null;
            $payload = $data;
            if ($payload['category_id'] !== null && !Db::name('categories')->where('id', $payload['category_id'])->find()) {
                throw new ValidationException(['category_id' => 'Kategori sudah tidak tersedia.']);
            }
            if ($payload['slug'] === '') {
                $payload['slug'] = $current['slug'] ?? $this->uniqueSlug($payload['title']);
            } else {
                $query = Db::name('articles')->where('slug', $payload['slug']);
                if ($id !== null) {
                    $query->where('id', '<>', $id);
                }
                if ($query->count() > 0) {
                    throw new ValidationException(['slug' => 'Slug sudah digunakan, termasuk artikel di sampah.']);
                }
            }
            $payload['updated_at'] = date('Y-m-d H:i:s');
            if ($id === null) {
                $payload['created_at'] = $payload['updated_at'];
                $payload['version'] = 1;
                return (int) Db::name('articles')->insertGetId($payload);
            }
            $version = filter_var($input['version'] ?? null, FILTER_VALIDATE_INT);
            if ($version === false || $version < 1) {
                throw new ValidationException(['version' => 'Versi artikel tidak valid. Muat ulang halaman edit.']);
            }
            $payload['version'] = $version + 1;
            $affected = Db::name('articles')->where('id', $id)->whereNull('deleted_at')->where('version', $version)->update($payload);
            if ($affected !== 1) {
                throw new ConflictException('Artikel telah berubah di tab atau sesi lain. Salin perubahan Anda, lalu muat ulang versi terbaru.');
            }
            return $id;
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Slug::make($title);
        $slug = $base;
        $number = 2;
        while (Db::name('articles')->where('slug', $slug)->count() > 0) {
            $slug = $base . '-' . $number++;
        }
        return $slug;
    }

    public function duplicate(int $id): int
    {
        $original = $this->find($id);
        $original['title'] = mb_substr($original['title'], 0, 139) . ' (salinan)';
        $original['slug'] = '';
        $original['status'] = 'draft';
        $original['published_at'] = '';
        return $this->save($original);
    }

    public function bulk(array $inputIds, string $action): int
    {
        if (!$inputIds || count($inputIds) > 100 || !in_array($action, ['publish', 'draft', 'archive', 'trash', 'restore'], true)) {
            throw new ValidationException(['bulk' => 'Pilih 1–100 artikel dan aksi yang tersedia.']);
        }
        $ids = [];
        foreach ($inputIds as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new ValidationException(['bulk' => 'Pilihan artikel tidak valid.']);
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        return Db::transaction(function () use ($ids, $action): int {
            $query = Db::name('articles')->whereIn('id', $ids);
            $action === 'restore' ? $query->whereNotNull('deleted_at') : $query->whereNull('deleted_at');
            if ((clone $query)->count() !== count($ids)) {
                throw new ConflictException('Sebagian artikel telah berubah atau tidak tersedia. Muat ulang daftar.');
            }
            $now = date('Y-m-d H:i:s');
            $data = ['updated_at' => $now, 'version' => Db::raw('version + 1')];
            switch ($action) {
                case 'publish': $data += ['status' => 'published', 'published_at' => $now]; break;
                case 'draft': $data += ['status' => 'draft', 'published_at' => null]; break;
                case 'archive': $data += ['status' => 'archived']; break;
                case 'trash': $data += ['deleted_at' => $now]; break;
                // Restoring always produces a draft so it never republishes unexpectedly.
                case 'restore': $data += ['deleted_at' => null, 'status' => 'draft', 'published_at' => null]; break;
            }
            $affected = $query->update($data);
            if ($affected !== count($ids)) {
                throw new ConflictException('Data berubah saat aksi diproses. Silakan muat ulang.');
            }
            return $affected;
        });
    }

    public function statistics(): array
    {
        $now = date('Y-m-d H:i:s');
        $active = Db::name('articles')->whereNull('deleted_at');
        return [
            'total' => (clone $active)->count(),
            'published' => (clone $active)->where('status', 'published')->where('published_at', '<=', $now)->count(),
            'scheduled' => (clone $active)->where('status', 'published')->where('published_at', '>', $now)->count(),
            'draft' => (clone $active)->where('status', 'draft')->count(),
            'archived' => (clone $active)->where('status', 'archived')->count(),
            'trash' => Db::name('articles')->whereNotNull('deleted_at')->count(),
        ];
    }
}
