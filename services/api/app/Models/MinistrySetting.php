<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinistrySetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'tagline', 'logo_path', 'dark_logo_path', 'favicon_path', 'public_domain', 'contact_email', 'contact_phone', 'public_address', 'website_label', 'social_links',
        'timezone', 'homepage_page_id', 'theme_preset', 'chrome_mode', 'chrome_color',
        'terminology',
    ];

    public const SINGLETON_ID = '00000000-0000-0000-0000-000000000001';

    protected function casts(): array
    {
        return ['social_links' => 'array', 'terminology' => 'array'];
    }
}
