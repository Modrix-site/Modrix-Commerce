<?php

namespace App\Domains\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $slug
 */
class Page extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'content', 'status', 'meta_title', 'meta_description'];
}
