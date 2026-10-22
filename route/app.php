<?php
declare(strict_types=1);

use app\controller\{Article, Auth, Blog, Category, Index};
use app\middleware\Authenticate;
use think\facade\Route;

Route::get('/', static fn () => redirect('/blog'));
Route::get('blog', [Blog::class, 'index']);
Route::get('read/:slug', [Blog::class, 'read'])->pattern(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*']);
Route::get('login', [Auth::class, 'form']);
Route::post('login', [Auth::class, 'login']);

Route::group('', function (): void {
    Route::get('dashboard', [Index::class, 'index']);
    Route::post('logout', [Auth::class, 'logout']);
    Route::get('account', [Auth::class, 'account']);
    Route::post('account/password', [Auth::class, 'password']);
    // Static paths must precede numeric article IDs.
    Route::get('articles', [Article::class, 'index']);
    Route::get('articles/create', [Article::class, 'create']);
    Route::get('articles/export', [Article::class, 'export']);
    Route::post('articles/bulk', [Article::class, 'bulk']);
    Route::post('articles', [Article::class, 'save']);
    Route::get('articles/:id/edit', [Article::class, 'edit'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::get('articles/:id', [Article::class, 'read'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::post('articles/:id/update', [Article::class, 'update'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::post('articles/:id/trash', [Article::class, 'trash'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::post('articles/:id/restore', [Article::class, 'restore'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::post('articles/:id/duplicate', [Article::class, 'duplicate'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::get('categories', [Category::class, 'index']);
    Route::post('categories', [Category::class, 'save']);
    Route::post('categories/:id/update', [Category::class, 'update'])->pattern(['id' => '[1-9][0-9]{0,9}']);
    Route::post('categories/:id/delete', [Category::class, 'delete'])->pattern(['id' => '[1-9][0-9]{0,9}']);
})->middleware(Authenticate::class);
