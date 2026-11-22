<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'timezone', 'address', 'address_line_1', 'address_line_2',
        'locality', 'region_code', 'region_name', 'postal_code', 'country_code',
        'country_name', 'latitude', 'longitude', 'is_primary', 'is_public',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_public' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
