<?php

namespace App\Actions;

use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallMinistry
{
    /**
     * @param  array{name: string, domain: string, timezone: string, location: string, admin_name: string, admin_email: string, password: string, tagline?: ?string, contact_email?: ?string, primary_color?: string, accent_color?: string}  $input
     */
    public function execute(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:160'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i'],
            'timezone' => ['required', 'timezone'],
            'location' => ['required', 'string', 'max:160'],
            'admin_name' => ['required', 'string', 'max:160'],
            'admin_email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:12'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ])->validate();

        if (MinistrySetting::query()->exists()) {
            throw ValidationException::withMessages(['installation' => 'This installation is already configured.']);
        }

        return DB::transaction(function () use ($input): User {
            MinistrySetting::create([
                'id' => MinistrySetting::SINGLETON_ID,
                'name' => $input['name'],
                'tagline' => $input['tagline'] ?? null,
                'public_domain' => strtolower($input['domain']),
                'contact_email' => $input['contact_email'] ?? null,
                'timezone' => $input['timezone'],
                'primary_color' => $input['primary_color'] ?? '#17324D',
                'accent_color' => $input['accent_color'] ?? '#D9A441',
            ]);

            Location::create([
                'name' => $input['location'],
                'slug' => Str::slug($input['location']),
                'timezone' => $input['timezone'],
                'is_primary' => true,
                'is_public' => true,
            ]);

            $admin = User::create([
                'name' => $input['admin_name'],
                'email' => strtolower($input['admin_email']),
                'password' => $input['password'],
            ]);

            DB::table('roles')->insertOrIgnore([
                ['key' => 'ministry_administrator', 'name' => 'Ministry Administrator', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'super_administrator', 'name' => 'Super Administrator', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'security_administrator', 'name' => 'Security Administrator', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'site_editor', 'name' => 'Site Editor', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);

            foreach (['super_administrator', 'ministry_administrator', 'security_administrator'] as $role) {
                DB::table('user_role_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $admin->id,
                    'role_key' => $role,
                    'scope_type' => 'installation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ([
                'settings.read' => 'View public ministry settings',
                'settings.update' => 'Update public ministry settings',
                'roles.manage' => 'Manage staff role assignments',
                'audit.read' => 'Review security audit events',
                'locations.read' => 'View ministry locations',
                'locations.manage' => 'Create and update ministry locations',
                'features.read' => 'View module states',
                'features.manage' => 'Change module states',
                'services.manage' => 'Create and operate service occurrences',
                'departments.manage' => 'Create departments and appoint department leaders',
            ] as $key => $description) {
                DB::table('permissions')->insertOrIgnore([
                    'key' => $key,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['settings.read', 'settings.update', 'locations.read', 'locations.manage', 'features.read', 'features.manage', 'services.manage', 'departments.manage', 'content.manage', 'content.publish'] as $permission) {
                if (DB::table('permissions')->where('key', $permission)->exists()) {
                    DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => $permission]);
                }
            }

            foreach (['roles.manage', 'audit.read'] as $permission) {
                DB::table('role_permissions')->insert(['role_key' => 'security_administrator', 'permission_key' => $permission]);
            }

            foreach (DB::table('permissions')->pluck('key') as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_key' => 'super_administrator', 'permission_key' => $permission]);
            }

            foreach (['settings.read', 'settings.update', 'locations.read', 'locations.manage'] as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_key' => 'site_editor', 'permission_key' => $permission]);
            }

            foreach (['digital_church', 'people', 'groups', 'giving', 'books_library', 'audio_sermons', 'video_sermons', 'sermon_series'] as $feature) {
                FeatureSetting::firstOrCreate(['key' => $feature], ['state' => 'disabled']);
            }

            foreach ([
                ['Media & Production', 'media-production', 'Visual, audio, livestream, projection, and service production.'],
                ['Prayer', 'prayer', 'Prayer gatherings and prayer-team coordination.'],
                ['Worship', 'worship', 'Music, worship leaders, and service musicians.'],
                ['Hospitality & Ushering', 'hospitality-ushering', 'Welcome, seating, and guest hospitality.'],
                ['Follow-up & Connections', 'follow-up-connections', 'Visitor welcome and connection follow-up.'],
                ['Outreach & Missions', 'outreach-missions', 'Community outreach and mission activities.'],
                ['Technical & Livestream', 'technical-livestream', 'Technical systems, streaming, and digital service support.'],
                ['Protocol', 'protocol', 'Guest, speaker, and service protocol.'],
                ['Communications', 'communications', 'Notices, social channels, photography, and communication support.'],
                ['Care', 'care', 'Care-team coordination. Restricted care records need separate access.'],
                ['Finance', 'finance', 'Finance-team coordination. Giving records need separate access.'],
            ] as [$name, $slug, $description]) {
                DB::table('departments')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'name' => $name, 'slug' => $slug, 'description' => $description,
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $admin;
        });
    }
}
