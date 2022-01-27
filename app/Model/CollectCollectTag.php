<?php

declare (strict_types=1);
namespace App\Model;

use Taoran\HyperfPackage\Traits\RepositoryTrait;

/**
 */
class CollectCollectTag extends Model
{
    use RepositoryTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collect_collect_tag';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];
}