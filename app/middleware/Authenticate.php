<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AuthService;
use Closure;
use think\Request;
use think\Response;

final class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (new AuthService())->current();
        if (!$user) {
            return redirect('/login');
        }
        $request->user = $user;
        return $next($request);
    }
}
