<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\exception\ValidationException;
use app\service\CategoryService;
use think\Response;

final class Category extends BaseController
{
    public function index(): Response
    {
        return $this->page();
    }

    private function page(array $errors = [], string $name = '', int $status = 200): Response
    {
        return $this->render('categories/index', [
            'categories' => (new CategoryService())->listing(), 'errors' => $errors, 'name' => $name,
        ], $status);
    }

    public function save(): Response
    {
        return $this->persist();
    }

    public function update(int $id): Response
    {
        return $this->persist($id);
    }

    private function persist(?int $id = null): Response
    {
        $name = $this->scalarPost('name');
        try {
            (new CategoryService())->save($name, $id);
        } catch (ValidationException $exception) {
            return $this->page($exception->errors(), $id === null ? $name : '', 422);
        }
        $this->flash($id === null ? 'Kategori berhasil dibuat.' : 'Nama kategori diperbarui.');
        return redirect('/categories');
    }

    public function delete(int $id): Response
    {
        try {
            (new CategoryService())->delete($id);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
            return redirect('/categories');
        }
        $this->flash('Kategori berhasil dihapus.');
        return redirect('/categories');
    }
}
