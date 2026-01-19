<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\App;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        return View::fetch('index/index', [
            'thinkVersion' => App::version(),
            'phpVersion'   => PHP_VERSION,
        ]);
    }
}
