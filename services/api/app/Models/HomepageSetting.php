<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    use HasUuids;

    public const SINGLETON_ID = '00000000-0000-0000-0000-000000000002';

    protected $fillable = ['id', 'theme_family', 'hero_layout', 'updated_by'];
}
