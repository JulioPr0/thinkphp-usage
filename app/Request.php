<?php
declare(strict_types=1);

namespace app;

class Request extends \think\Request
{
    public ?array $user = null;
}
