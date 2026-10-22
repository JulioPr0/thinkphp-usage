<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Session;

final class Security
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::get('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        if ((int) $request->server('CONTENT_LENGTH', '0') > 262144) {
            abort(413, 'Ukuran formulir melebihi batas 256 KB.');
        }
        $method = strtoupper((string) $request->server('REQUEST_METHOD', 'GET'));
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $token = $request->post('_token', $request->header('X-CSRF-Token', ''));
            if (!is_string($token) || !hash_equals((string) Session::get('csrf_token'), $token)) {
                abort(419, 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.');
            }
        }
        return $next($request)->header([
            'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }
}
