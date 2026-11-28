<?php

namespace App\Actions;

use App\Http\Controllers\PageWorkspaceController;
use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Models\User;
use App\Services\DefaultMediaLibrary;
use App\Services\PageDocumentService;
use App\Support\AddressCatalog;
use App\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstallMinistry
{
    public function __construct(private readonly AddressCatalog $addresses) {}

    /**
     * @param  array{name: string, domain: string, timezone: string, location: string, admin_name: string, admin_email: string, password: string, tagline?: ?string, contact_email?: ?string, theme_preset?: string, chrome_mode?: string, chrome_color?: ?string, location_address_line_1?: ?string, location_address_line_2?: ?string, location_city?: ?string, location_region_code?: ?string, location_postal_code?: ?string, location_country_code?: ?string}  $input
     */
    public function execute(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:160'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i'],
            'timezone' => ['required', 'timezone'],
            'location' => ['required', 'string', 'max:160'],
            'location_address_line_1' => ['nullable', 'string', 'max:255'],
            'location_address_line_2' => ['nullable', 'string', 'max:255'],
            'location_city' => ['nullable', 'string', 'max:120'],
            'location_region_code' => ['nullable', 'string', 'max:16'],
            'location_postal_code' => ['nullable', 'string', 'max:32'],
            'location_country_code' => ['nullable', Rule::in(array_keys($this->addresses->countries()))],
            'admin_name' => ['required', 'string', 'max:160'],
            'admin_email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:12'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'theme_preset' => ['nullable', 'in:tech_blue,neutral,warm'],
            'chrome_mode' => ['nullable', Rule::in(['preset', 'custom'])],
            'chrome_color' => ['nullable', 'required_if:chrome_mode,custom', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ])->validate();

        if (MinistrySetting::query()->exists()) {
            throw ValidationException::withMessages(['installation' => 'This installation is already configured.']);
        }

        $countryCode = filled($input['location_country_code'] ?? null) ? strtoupper($input['location_country_code']) : null;
        $regionCode = filled($input['location_region_code'] ?? null) ? (string) $input['location_region_code'] : null;
        $countryName = $this->addresses->countryName($countryCode);
        $regionName = $this->addresses->subdivisionName($countryCode, $regionCode);
        if ($regionCode && ! $regionName) {
            throw ValidationException::withMessages(['location_region_code' => 'Choose a valid state, province, or region for the selected country.']);
        }
        $structuredAddress = [
            'address_line_1' => $input['location_address_line_1'] ?? null,
            'address_line_2' => $input['location_address_line_2'] ?? null,
            'locality' => $input['location_city'] ?? null,
            'region_code' => $regionCode,
            'region_name' => $regionName,
            'postal_code' => $input['location_postal_code'] ?? null,
            'country_code' => $countryCode,
            'country_name' => $countryName,
        ];
        $locationAddress = $this->addresses->format($structuredAddress);

        return DB::transaction(function () use ($input, $structuredAddress, $locationAddress): User {

            MinistrySetting::create([
                'id' => MinistrySetting::SINGLETON_ID,
                'name' => $input['name'],
                'tagline' => $input['tagline'] ?? null,
                'public_domain' => strtolower($input['domain']),
                'contact_email' => $input['contact_email'] ?? null,
                'timezone' => $input['timezone'],
                'theme_preset' => $input['theme_preset'] ?? 'tech_blue',
                'chrome_mode' => $input['chrome_mode'] ?? 'preset',
                'chrome_color' => ($input['chrome_mode'] ?? 'preset') === 'custom' ? strtoupper((string) $input['chrome_color']) : null,
                'public_address' => $locationAddress,
            ]);

            Location::create([
                'name' => $input['location'],
                'slug' => Str::slug($input['location']),
                'timezone' => $input['timezone'],
                'address' => $locationAddress ?: null,
                ...$structuredAddress,
                'is_primary' => true,
                'is_public' => true,
            ]);

            $admin = User::create([
                'name' => $input['admin_name'],
                'email' => strtolower($input['admin_email']),
                'password' => $input['password'],
            ]);

            foreach (RoleCatalog::INSTALLATION_ROLES as $key => $name) {
                DB::table('roles')->insertOrIgnore([
                    'key' => $key, 'name' => $name, 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

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
                'ai.manage' => 'Configure AI providers and page drafting models',
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

            foreach (DB::table('permissions')->pluck('key') as $permission) {
                if (! in_array($permission, ['audit.read', 'care.manage', 'care.notes.write', 'finance.view', 'finance.reconcile', 'roles.manage'], true)) {
                    DB::table('role_permissions')->insertOrIgnore(['role_key' => 'ministry_administrator', 'permission_key' => $permission]);
                }
            }

            foreach (['roles.manage', 'audit.read'] as $permission) {
                DB::table('role_permissions')->insert(['role_key' => 'security_administrator', 'permission_key' => $permission]);
            }

            foreach (DB::table('permissions')->pluck('key') as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_key' => 'super_administrator', 'permission_key' => $permission]);
            }

            foreach (RoleCatalog::PERMISSIONS as $role => $permissions) {
                foreach ($permissions as $permission) {
                    if (DB::table('permissions')->where('key', $permission)->exists()) {
                        DB::table('role_permissions')->insertOrIgnore(['role_key' => $role, 'permission_key' => $permission]);
                    }
                }
            }

            foreach (['digital_church', 'interactive_meetings', 'people', 'giving', 'testimonies', 'books_library', 'audio_sermons', 'video_sermons', 'sermon_series'] as $feature) {
                FeatureSetting::firstOrCreate(['key' => $feature], ['state' => 'enabled']);
            }
            FeatureSetting::firstOrCreate(['key' => 'attendance'], ['state' => 'disabled']);

            FeatureSetting::query()->where('key', '!=', 'attendance')->update(['state' => 'enabled', 'changed_by' => $admin->id]);

            // Turn the packaged starter artwork into installation-owned Media
            // Library assets before any default page document references it.
            app(DefaultMediaLibrary::class)->install();

            // Every fresh installation starts with a real, editable homepage page.
            // This prevents the public route from depending on a missing fallback view.
            $homepageId = (string) Str::uuid();
            $homepageDocument = app(PageWorkspaceController::class)->starterDocument('homepage');
            DB::table('pages')->insert([
                'id' => $homepageId,
                'title' => 'Home',
                'slug' => 'home',
                'body' => app(PageDocumentService::class)->plainText($homepageDocument, FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all()),
                'blocks' => json_encode($homepageDocument, JSON_THROW_ON_ERROR),
                'page_type' => 'homepage',
                'source_key' => 'homepage',
                'seo_title' => $input['name'],
                'seo_description' => $input['tagline'] ?? 'Welcome to our ministry.',
                'status' => 'published',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            MinistrySetting::query()->whereKey(MinistrySetting::SINGLETON_ID)->update(['homepage_page_id' => $homepageId]);

            // The public site begins with the matching About Us design too. Both
            // defaults remain ordinary editable pages after installation.
            $aboutDocument = app(PageWorkspaceController::class)->starterDocument('about');
            DB::table('pages')->insert([
                'id' => (string) Str::uuid(),
                'title' => 'About Us',
                'slug' => 'about-us',
                'body' => app(PageDocumentService::class)->plainText($aboutDocument, FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all()),
                'blocks' => json_encode($aboutDocument, JSON_THROW_ON_ERROR),
                'page_type' => 'standard',
                'source_key' => 'about',
                'seo_title' => 'About '.$input['name'],
                'seo_description' => 'Learn about our ministry, its story, values, people, and public life.',
                'status' => 'published',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Keep the archive private until staff have added the genuine crusade
            // media and checked its historical details.
            $crusadeDocument = app(\App\Services\CrusadeArchiveStarterService::class)->document(FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all());
            DB::table('pages')->insert([
                'id' => (string) Str::uuid(),
                'title' => 'Ministry Crusade',
                'slug' => 'crusade-archive',
                'body' => app(PageDocumentService::class)->plainText($crusadeDocument, FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all()),
                'blocks' => json_encode($crusadeDocument, JSON_THROW_ON_ERROR),
                'page_type' => 'standard',
                'source_key' => 'crusade_archive',
                'seo_title' => 'Ministry Crusade Archive',
                'seo_description' => 'A private, editable archive for verified crusade photos and recordings.',
                'status' => 'draft',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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
