<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceOccurrence extends Model
{
    use HasUuids;

    protected $fillable = ['service_schedule_id', 'service_template_id', 'location_id', 'title', 'service_type', 'timezone', 'starts_at', 'ends_at', 'state', 'manual_stream_override', 'rehearsal_mode', 'schedule_override'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'manual_stream_override' => 'boolean', 'rehearsal_mode' => 'boolean', 'schedule_override' => 'boolean'];
    }
}
