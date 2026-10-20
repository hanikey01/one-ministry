<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceChatMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['service_occurrence_id', 'user_id', 'guest_key_hash', 'display_name', 'is_host', 'content', 'moderated_at', 'moderated_by', 'moderation_reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'moderated_at' => 'datetime', 'is_host' => 'boolean'];
    }
}
