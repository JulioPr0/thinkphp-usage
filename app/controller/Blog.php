<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ArticleService;
use app\support\ArticleFilters;
use think\Response;
use think\facade\Db;

final class Blog extends BaseController
{
    public function index(): Response
    {
        $filters = ArticleFilters::fromArray($this->request->get());
        $filters['view'] = 'active';
        return $this->render('blog/index', [
            'filters' => $filters, 'pagePath' => '/blog', 'listing' => (new ArticleService())->listing($filters, true),
            'categories' => Db::name('categories')->order('name')->select()->toArray(),
        ]);
    }

    public function read(string $slug): Response
    {
        $article = Db::name('articles')->alias('a')->leftJoin('categories c', 'c.id = a.category_id')
            ->field('a.*, c.name AS category_name')->where('a.slug', $slug)
            ->whereNull('a.deleted_at')->where('a.status', 'published')
            ->where('a.published_at', '<=', date('Y-m-d H:i:s'))->find();
        if (!$article) {
            abort(404, 'Artikel tidak ditemukan.');
        }
        $words = preg_split('/\s+/u', trim($article['content']), -1, PREG_SPLIT_NO_EMPTY);
        return $this->render('blog/show', ['article' => $article, 'readingMinutes' => max(1, (int) ceil(count($words) / 200))]);
    }
}
