<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ArticleService;
use app\support\ArticleFilters;
use think\Response;
use think\facade\Db;

final class Index extends BaseController
{
    public function index(): Response
    {
        $service = new ArticleService();
        return $this->render('index/index', [
            'stats' => $service->statistics(),
            'recent' => $service->listing(ArticleFilters::fromArray([]))['rows'],
            'categoryCount' => Db::name('categories')->count(),
        ]);
    }
}
