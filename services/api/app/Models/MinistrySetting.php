<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinistrySetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'tagline', 'logo_path', 'dark_logo_path', 'favicon_path', 'public_domain', 'contact_email', 'contact_phone', 'public_address', 'website_label', 'social_links',
        'timezone', 'homepage_page_id', 'primary_color', 'accent_color',
        'light_canvas_color', 'light_surface_color', 'light_sidebar_color',
        'dark_canvas_color', 'dark_surface_color', 'dark_sidebar_color',
        'staff_font', 'staff_sidebar', 'staff_density',
        'terminology',
    ];

    public const SINGLETON_ID = '00000000-0000-0000-0000-000000000001';

    protected function casts(): array
    {
        return ['social_links' => 'array', 'terminology' => 'array'];
    }
}
