<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'timezone', 'address', 'latitude', 'longitude', 'is_primary', 'is_public'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_public' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
