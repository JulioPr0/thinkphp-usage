<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;

class IgnoreDeprecations
{
    public function handle(Request $request, Closure $next)
    {
        $level = error_reporting();

        if (defined('E_DEPRECATED')) {
            $level &= ~E_DEPRECATED;
        }

        if (defined('E_USER_DEPRECATED')) {
            $level &= ~E_USER_DEPRECATED;
        }

        error_reporting($level);

        return $next($request);
    }
}
