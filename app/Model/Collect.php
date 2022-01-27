<?php

declare (strict_types=1);
namespace App\Model;

use Taoran\HyperfPackage\Traits\RepositoryTrait;

/**
 */
class Collect extends Model
{
    use RepositoryTrait;

    protected $dateFormat = 'Uv';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collect';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /** @var int 未审核 */
    const STATUS_UNCHECKED = 0;
    /** @var int 通过 */
    const STATUS_ALLOW = 1;
    /** @var int 拒绝 */
    const STATUS_REJECT = 2;

    /**
     * 分类表
     *
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function collectCategory()
    {
        return $this->hasOne(\App\Model\CollectCategory::class, 'id', 'cat_id')->where('is_on', 1);
    }

    /**
     * 标签表
     *
     * @return \Hyperf\Database\Model\Relations\HasOne
     */
    public function collectTags()
    {
        return $this->belongsToMany(\App\Model\CollectTag::class, 'collect_collect_tag', 'collect_id', 'tag_id')->where('is_on', 1);
    }
}