<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class ArticleValidate extends Validate
{
    protected $rule = [
        'title'   => 'require|max:150',
        'summary' => 'max:255',
        'content' => 'require|min:10',
        'status'  => 'require|in:draft,published',
    ];

    protected $message = [
        'title.require'  => 'Judul wajib diisi.',
        'title.max'      => 'Judul maksimal 150 karakter.',
        'summary.max'    => 'Ringkasan maksimal 255 karakter.',
        'content.require'=> 'Konten wajib diisi.',
        'content.min'    => 'Konten minimal 10 karakter.',
        'status.require' => 'Status wajib diisi.',
        'status.in'      => 'Status harus draft atau published.',
    ];
}
