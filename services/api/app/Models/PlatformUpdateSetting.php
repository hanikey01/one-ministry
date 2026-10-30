<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformUpdateSetting extends Model
{
    protected $table = 'platform_update_settings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];
}
