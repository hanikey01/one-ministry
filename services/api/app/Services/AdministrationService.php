<?php

namespace App\Services;

use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdministrationService
{
    public function createLocation(array $data, User $actor, ?string $ip): Location
    {
        return DB::transaction(function () use ($data, $actor, $ip): Location {
            $this->lockInstallation();
            $previousPrimaryId = null;
            if ($data['is_primary'] ?? false) {
                $previousPrimaryId = Location::query()->where('is_primary', true)->value('id');
                Location::query()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $slug = Str::slug($data['name']);
            if ($slug === '' || Location::query()->where('slug', $slug)->exists()) {
                $slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
            }
            $location = Location::create([
                ...$data,
                'slug' => $slug,
                'is_public' => $data['is_public'] ?? true,
                'is_primary' => $data['is_primary'] ?? false,
            ]);
            if ($location->is_primary) {
                MinistrySetting::query()->whereKey(MinistrySetting::SINGLETON_ID)
                    ->update(['public_address' => $location->address]);
            }
            $this->audit($actor, 'location.created', 'locations', $location->id, [
                'after' => $location->toArray(), 'previous_primary_id' => $previousPrimaryId,
            ], $ip);

            return $location;
        });
    }

    public function updateLocation(Location $location, array $data, User $actor, ?string $ip): Location
    {
        return DB::transaction(function () use ($location, $data, $actor, $ip): Location {
            $this->lockInstallation();
            $location->refresh();
            if ($location->is_primary && array_key_exists('is_primary', $data) && ! $data['is_primary']) {
                throw new ConflictHttpException('Choose another primary location first.');
            }
            $previousPrimaryId = null;
            if (($data['is_primary'] ?? false) && ! $location->is_primary) {
                $previousPrimaryId = Location::query()->where('is_primary', true)->value('id');
                Location::query()->where('is_primary', true)->update(['is_primary' => false]);
            }
            $before = $location->only(array_keys($data));
            $location->fill($data);
            $changes = $location->getDirty();
            if ($changes !== []) {
                $location->save();
                if ($location->is_primary) {
                    MinistrySetting::query()->whereKey(MinistrySetting::SINGLETON_ID)
                        ->update(['public_address' => $location->address]);
                }
                $this->audit($actor, 'location.updated', 'locations', $location->id, [
                    'before' => array_intersect_key($before, $changes), 'after' => $changes,
                    'previous_primary_id' => $previousPrimaryId,
                ], $ip);
            }

            return $location;
        });
    }

    public function deleteLocation(Location $location, User $actor, ?string $ip): void
    {
        DB::transaction(function () use ($location, $actor, $ip): void {
            $this->lockInstallation();
            $location->refresh();
            if ($location->is_primary) {
                throw new ConflictHttpException('Choose another primary location before deleting this one.');
            }
            $before = $location->toArray();
            $location->delete();
            $this->audit($actor, 'location.deleted', 'locations', $location->id, ['before' => $before], $ip);
        });
    }

    public function changeFeature(FeatureSetting $feature, string $state, User $actor, ?string $ip): FeatureSetting
    {
        abort_if($feature->key === 'attendance', 404);

        return DB::transaction(function () use ($feature, $state, $actor, $ip): FeatureSetting {
            $feature = FeatureSetting::query()->lockForUpdate()->findOrFail($feature->key);
            if ($feature->state !== $state) {
                $before = $feature->state;
                $feature->update(['state' => $state, 'changed_by' => $actor->id]);
                $this->audit($actor, 'feature.state_changed', 'feature_settings', MinistrySetting::SINGLETON_ID, [
                    'key' => $feature->key, 'before' => $before, 'after' => $state,
                ], $ip);
            }

            return $feature;
        });
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function changeFeatures(array $keys, string $state, User $actor, ?string $ip): int
    {
        abort_if(in_array('attendance', $keys, true), 404);

        return DB::transaction(function () use ($keys, $state, $actor, $ip): int {
            $this->lockInstallation();
            $features = FeatureSetting::query()->whereIn('key', $keys)->lockForUpdate()->get();
            $changed = 0;

            foreach ($features as $feature) {
                if ($feature->state === $state) {
                    continue;
                }

                $before = $feature->state;
                $feature->update(['state' => $state, 'changed_by' => $actor->id]);
                $this->audit($actor, 'feature.state_changed', 'feature_settings', MinistrySetting::SINGLETON_ID, [
                    'key' => $feature->key, 'before' => $before, 'after' => $state, 'bulk' => true,
                ], $ip);
                $changed++;
            }

            return $changed;
        });
    }

    private function lockInstallation(): void
    {
        MinistrySetting::query()->lockForUpdate()->findOrFail(MinistrySetting::SINGLETON_ID);
    }

    private function audit(User $actor, string $action, string $subjectType, string $subjectId, array $changes, ?string $ip): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id,
            'action' => $action, 'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR),
            'ip_address' => $ip, 'created_at' => now(),
        ]);
    }
}
