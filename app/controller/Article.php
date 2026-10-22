<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\exception\ConflictException;
use app\exception\ValidationException;
use app\service\ArticleService;
use app\support\ArticleFilters;
use app\support\Csv;
use think\Response;
use think\facade\Db;

final class Article extends BaseController
{
    public function index(): Response
    {
        $filters = ArticleFilters::fromArray($this->request->get());
        return $this->render('articles/index', [
            'filters' => $filters, 'listing' => (new ArticleService())->listing($filters),
            'categories' => Db::name('categories')->order('name')->select()->toArray(),
            'statuses' => ArticleFilters::STATUSES, 'sorts' => ArticleFilters::SORTS,
            'stats' => (new ArticleService())->statistics(),
            'exportUrl' => ArticleFilters::url($filters, [], '/articles/export'),
        ]);
    }

    public function create(): Response
    {
        return $this->form();
    }

    public function save(): Response
    {
        return $this->persist();
    }

    public function edit(int $id): Response
    {
        return $this->form((new ArticleService())->find($id));
    }

    public function update(int $id): Response
    {
        (new ArticleService())->find($id);
        return $this->persist($id);
    }

    private function persist(?int $id = null): Response
    {
        $input = $this->request->post();
        try {
            $savedId = (new ArticleService())->save($input, $id);
        } catch (ValidationException $exception) {
            return $this->form($input, $id, $exception->errors(), 422);
        } catch (ConflictException $exception) {
            return $this->form($input, $id, ['version' => $exception->getMessage()], 409);
        }
        $this->flash($id ? 'Perubahan artikel berhasil disimpan.' : 'Artikel baru berhasil dibuat.');
        return redirect('/articles/' . $savedId);
    }

    private function form(array $data = [], ?int $id = null, array $errors = [], int $status = 200): Response
    {
        $id ??= isset($data['id']) ? (int) $data['id'] : null;
        $defaults = ['title' => '', 'slug' => '', 'summary' => '', 'content' => '', 'status' => 'draft', 'category_id' => '', 'published_at' => '', 'version' => '1'];
        foreach ($defaults as $key => $value) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $defaults[$key] = (string) $data[$key];
            }
        }
        if ($defaults['published_at'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $defaults['published_at'])) {
            $defaults['published_at'] = substr(str_replace(' ', 'T', $defaults['published_at']), 0, 16);
        }
        return $this->render('articles/form', [
            'article' => $defaults, 'id' => $id,
            'heading' => $id ? 'Edit artikel' : 'Artikel baru',
            'action' => $id ? '/articles/' . $id . '/update' : '/articles',
            'categories' => Db::name('categories')->order('name')->select()->toArray(),
            'statuses' => ArticleFilters::STATUSES, 'errors' => $errors,
        ], $status);
    }

    public function read(int $id): Response
    {
        $article = (new ArticleService())->find($id);
        $article['category_name'] = $article['category_id'] ? Db::name('categories')->where('id', $article['category_id'])->value('name') : null;
        return $this->render('articles/show', [
            'article' => $article,
            'isPublic' => $article['status'] === 'published' && $article['published_at'] <= date('Y-m-d H:i:s'),
        ]);
    }

    public function trash(int $id): Response
    {
        (new ArticleService())->bulk([$id], 'trash');
        $this->flash('Artikel dipindahkan ke sampah dan dapat dipulihkan.');
        return redirect('/articles');
    }

    public function restore(int $id): Response
    {
        (new ArticleService())->bulk([$id], 'restore');
        $this->flash('Artikel dipulihkan sebagai draft.');
        return redirect('/articles?view=trash');
    }

    public function duplicate(int $id): Response
    {
        $newId = (new ArticleService())->duplicate($id);
        $this->flash('Salinan draft berhasil dibuat.');
        return redirect('/articles/' . $newId . '/edit');
    }

    public function bulk(): Response
    {
        $ids = $this->request->post('ids', []);
        try {
            $count = (new ArticleService())->bulk(is_array($ids) ? $ids : [], $this->scalarPost('action'));
            $this->flash($count . ' artikel berhasil diproses.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (ConflictException $exception) {
            $this->flash($exception->getMessage(), 'error');
        }
        return redirect($this->scalarPost('return_view') === 'trash' ? '/articles?view=trash' : '/articles');
    }

    public function export(): Response
    {
        $filters = ArticleFilters::fromArray($this->request->get());
        $query = (new ArticleService())->query($filters);
        if ((clone $query)->count('a.id') > 5000) {
            abort(422, 'Ekspor dibatasi 5.000 artikel. Persempit filter pencarian.');
        }
        $rows = $query->field('a.id,a.title,a.slug,a.summary,a.status,c.name AS category,a.published_at,a.created_at,a.updated_at')->limit(5000)->select()->toArray();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ID', 'Judul', 'Slug', 'Ringkasan', 'Status', 'Kategori', 'Waktu terbit', 'Dibuat', 'Diperbarui'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map([Csv::class, 'cell'], array_values($row)), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return response($csv)->header([
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="articles-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
