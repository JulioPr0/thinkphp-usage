<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\exception\ValidationException;
use app\service\AuthService;
use think\Response;
use think\facade\Session;

final class Auth extends BaseController
{
    public function form(): Response
    {
        if ((new AuthService())->current()) {
            return redirect('/dashboard');
        }
        return $this->render('auth/login');
    }

    public function login(): Response
    {
        $errors = [];
        try {
            if ((new AuthService())->login($this->request->post(), (string) $this->request->server('REMOTE_ADDR', 'unknown'))) {
                return redirect('/dashboard');
            }
            $errors['login'] = 'Email atau password tidak sesuai.';
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }
        return $this->render('auth/login', ['errors' => $errors, 'email' => mb_substr($this->scalarPost('email'), 0, 190)], 422);
    }

    public function logout(): Response
    {
        Session::destroy();
        return redirect('/login');
    }

    public function account(): Response
    {
        return $this->render('auth/account');
    }

    public function password(): Response
    {
        try {
            (new AuthService())->changePassword((int) $this->request->user['id'], $this->request->post());
        } catch (ValidationException $exception) {
            return $this->render('auth/account', ['errors' => $exception->errors()], 422);
        }
        $this->flash('Password diperbarui. Silakan masuk kembali.');
        return redirect('/login');
    }
}
