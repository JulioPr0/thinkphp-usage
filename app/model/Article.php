<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Article extends Model
{
    protected $table = 'articles';
    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'           => 'int',
        'title'        => 'string',
        'summary'      => 'string',
        'content'      => 'string',
        'status'       => 'string',
        'published_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected $type = [
        'published_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public const STATUSES = [
        'draft'     => 'Draft',
        'published' => 'Published',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function getStatusLabelAttr(): string
    {
        return self::STATUSES[$this->getAttr('status')] ?? ucfirst((string) $this->getAttr('status'));
    }
}
