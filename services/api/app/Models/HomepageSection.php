<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    use HasUuids;

    protected $fillable = ['section_type', 'position', 'is_enabled', 'settings', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'settings' => 'array'];
    }
}
