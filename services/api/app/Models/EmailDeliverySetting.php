<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliverySetting extends Model
{
    public const SINGLETON_ID = '00000000-0000-0000-0000-000000000002';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'provider', 'enabled', 'from_address', 'from_name', 'host', 'port', 'encryption', 'username', 'password_encrypted', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'port' => 'integer'];
    }
}
