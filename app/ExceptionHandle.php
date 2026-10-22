<?php
declare(strict_types=1);

namespace app;

use app\exception\ConflictException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    protected $ignoreReport = [HttpException::class, HttpResponseException::class];

    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse();
        }
        if (env('APP_DEBUG', false) && !$exception instanceof HttpException) {
            return parent::render($request, $exception);
        }
        $status = $exception instanceof HttpException ? $exception->getStatusCode() : ($exception instanceof ConflictException ? 409 : 500);
        $message = $exception instanceof HttpException || $exception instanceof ConflictException ? $exception->getMessage() : 'Terjadi kesalahan. Silakan coba kembali.';
        $html = '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $status . ' | ThinkPress</title><link rel="stylesheet" href="/static/css/app.css">'
            . '<main class="container error-page"><p class="eyebrow">THINKPRESS</p><h1>' . $status . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p><a class="button" href="/">Kembali ke beranda</a></main></html>';
        return response($html, $status)->header(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
