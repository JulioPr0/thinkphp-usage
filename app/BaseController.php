<?php
declare(strict_types=1);

namespace app;

use think\App;
use think\Request;
use think\Response;
use think\facade\Session;
use think\facade\View;

abstract class BaseController
{
    protected App $app;
    protected Request $request;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
    }

    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        $shared = [
            'appName' => (string) env('APP_NAME', 'ThinkPress'),
            'csrf' => (string) Session::get('csrf_token', ''),
            'user' => $this->request->user ?? null,
            'flash' => Session::pull('flash', []),
            'errors' => [], 'old' => [], 'pagePath' => '/articles',
        ];
        return response(View::fetch($template, array_merge($shared, $data)), $status);
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        Session::flash('flash', ['message' => $message, 'type' => $type]);
    }

    protected function scalarPost(string $key, string $default = ''): string
    {
        $value = $this->request->post($key, $default);
        return is_string($value) ? $value : $default;
    }
}
