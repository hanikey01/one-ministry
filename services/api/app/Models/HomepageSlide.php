<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HomepageSlide extends Model
{
    use HasUuids;

    protected $fillable = ['position', 'is_published', 'media_type', 'media_url', 'eyebrow', 'title', 'body', 'button_label', 'button_url', 'alt_text', 'updated_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
