<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Article as ArticleModel;
use app\validate\ArticleValidate;
use think\facade\Session;
use think\facade\View;
use think\exception\ValidateException;

class Article extends BaseController
{
    public function index()
    {
        $search  = $this->request->get('q');
        $status  = $this->request->get('status');
        $perPage = (int) $this->request->get('per_page', 8);
        $sort    = $this->request->get('sort', 'recent');

        $query = ArticleModel::order('created_at', 'desc');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->whereLike('title|summary|content', "%{$search}%");
        }

        $query = match ($sort) {
            'title'     => $query->order('title', 'asc'),
            'published' => $query->orderRaw('published_at IS NULL')->order('published_at', 'desc'),
            default     => $query->order('created_at', 'desc'),
        };

        $articles = $query->paginate([
            'list_rows' => $perPage > 0 ? $perPage : 8,
            'query'     => $this->request->get(),
        ]);

        $flash = [
            'message' => Session::pull('flash.message'),
            'type'    => Session::pull('flash.type', 'info'),
        ];

        $errors = Session::pull('errors', []);
        $old    = Session::pull('old', []);
        $openModal = $this->request->get('modal') === 'create' || !empty($errors);
        $stats = [
            'total'     => ArticleModel::count(),
            'published' => ArticleModel::where('status', 'published')->count(),
            'draft'     => ArticleModel::where('status', 'draft')->count(),
        ];
        $latestPublished = ArticleModel::published()->order('published_at', 'desc')->limit(5)->select();

        $publishedRatio = $stats['total'] > 0 ? round(($stats['published'] / $stats['total']) * 100, 1) : 0;

        return View::fetch('articles/index', [
            'articles'  => $articles,
            'search'    => $search,
            'status'    => $status,
            'statuses'  => ArticleModel::STATUSES,
            'flash'     => $flash,
            'perPage'   => $perPage,
            'errors'    => $errors,
            'old'       => $old,
            'openModal' => $openModal,
            'sort'      => $sort,
            'stats'     => $stats,
            'latestPublished' => $latestPublished,
            'publishedRatio'  => $publishedRatio,
        ]);
    }

    public function create()
    {
        Session::flash('flash.message', 'Gunakan form modal di halaman daftar untuk membuat artikel');
        Session::flash('flash.type', 'info');
        return redirect((string) url('/articles', ['modal' => 'create']));
    }

    public function save()
    {
        try {
            $data = $this->validatedPayload();
        } catch (ValidateException $e) {
            Session::flash('flash.message', 'Periksa kembali form!');
            Session::flash('flash.type', 'error');
            return redirect((string) url('/articles', ['modal' => 'create']));
        }

        ArticleModel::create($data);

        Session::flash('flash.message', 'Artikel baru berhasil dibuat');
        Session::flash('flash.type', 'success');

        return redirect((string) url('/articles'));
    }

    public function read(int $id)
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan');
        }

        return View::fetch('articles/show', [
            'article'  => $article,
            'statuses' => ArticleModel::STATUSES,
            'flash'    => [
                'message' => Session::pull('flash.message'),
                'type'    => Session::pull('flash.type', 'info'),
            ],
        ]);
    }

    public function edit(int $id)
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan');
        }

        return View::fetch('articles/edit', [
            'article'  => $article,
            'statuses' => ArticleModel::STATUSES,
            'errors'   => Session::pull('errors', []),
            'old'      => Session::pull('old', []),
        ]);
    }

    public function update(int $id)
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan');
        }

        try {
            $data = $this->validatedPayload();
        } catch (ValidateException $e) {
            Session::flash('flash.message', 'Periksa kembali form!');
            Session::flash('flash.type', 'error');
            return redirect((string) url('/articles/' . $id . '/edit'));
        }
        $article->save($data);

        Session::flash('flash.message', 'Artikel diperbarui');
        Session::flash('flash.type', 'success');

        return redirect((string) url('/articles/' . $id));
    }

    public function delete(int $id)
    {
        $article = ArticleModel::find($id);
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan');
        }

        $article->delete();

        Session::flash('flash.message', 'Artikel dihapus');
        Session::flash('flash.type', 'success');

        return redirect((string) url('/articles'));
    }

    protected function validatedPayload(): array
    {
        $data = $this->request->only([
            'title',
            'summary',
            'content',
            'status',
            'published_at',
        ], 'post');

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        $data['status'] = $data['status'] ?? 'draft';

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if (!empty($data['published_at'])) {
            $timestamp = strtotime($data['published_at']);
            $data['published_at'] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
        } else {
            $data['published_at'] = null;
        }

        try {
            $this->validate($data, ArticleValidate::class);
        } catch (ValidateException $e) {
            Session::flash('errors', $e->getError());
            Session::flash('old', $data);
            throw $e;
        }

        return $data;
    }
}
