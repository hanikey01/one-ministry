<?php

namespace App\Actions;

use App\Models\MinistrySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateMinistrySettings
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes, User $actor, ?string $ipAddress): MinistrySetting
    {
        return DB::transaction(function () use ($attributes, $actor, $ipAddress): MinistrySetting {
            $setting = MinistrySetting::query()->lockForUpdate()->findOrFail(MinistrySetting::SINGLETON_ID);
            $before = $setting->only(array_keys($attributes));
            $setting->fill($attributes);
            $changes = $setting->getDirty();

            if ($changes !== []) {
                $setting->save();
                DB::table('audit_events')->insert([
                    'id' => (string) Str::uuid(),
                    'actor_user_id' => $actor->id,
                    'action' => 'ministry_settings.updated',
                    'subject_type' => 'ministry_settings',
                    'subject_id' => $setting->id,
                    'changes_json' => json_encode(['before' => array_intersect_key($before, $changes), 'after' => $changes], JSON_THROW_ON_ERROR),
                    'ip_address' => $ipAddress,
                    'created_at' => now(),
                ]);
            }

            return $setting;
        });
    }
}
