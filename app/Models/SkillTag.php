<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SkillTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'name', 'position'];

    public static function options(): Collection
    {
        return static::orderBy('position')->orderBy('name')->get(['id', 'key', 'name']);
    }
}
