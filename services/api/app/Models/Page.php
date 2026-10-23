<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'blocks',
        'page_type',
        'source_key',
        'seo_title',
        'seo_description',
        'status',
        'review_notes',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function isHomepage(): bool
    {
        return $this->page_type === 'homepage' && $this->source_key === 'homepage';
    }
}
