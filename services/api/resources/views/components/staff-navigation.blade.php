@php
    $permissions = app(\App\Services\PermissionChecker::class);
    $departmentTeams = app(\App\Services\DepartmentService::class);
    $user = auth()->user();
    $enabledFeatures = \App\Models\FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();
    $groups = [
        'Workspace' => [
            ['Overview', 'staff.overview', 'settings.read', 'home'], ['People', 'staff.people', 'services.manage', 'people'], ['Services', 'staff.services', 'services.manage', 'services'],
            ['Follow-up', 'staff.follow-up', 'services.manage', 'followup'], ['Care', 'staff.care', 'care.manage', 'care'],
        ],
        'Ministry' => [
            ['Groups', 'staff.groups', 'community.manage', 'groups'], ['Events', 'staff.events', 'community.manage', 'calendar'], ['Volunteers', 'staff.volunteers', 'community.manage', 'volunteer'],
            ['Digital school', 'staff.school.index', 'school.manage', 'school'], ['Communications', 'staff.communications', 'communications.manage', 'message'], ['Giving', 'staff.giving', 'finance.view', 'giving'], ['Reports', 'staff.reports', 'reports.view', 'reports'],
        ],
        'Publishing' => [
            ['Content library', 'staff.content', 'content.manage', 'content'], ['Pages', 'staff.pages', 'content.manage', 'content'], ['Navigation', 'staff.navigation', 'content.manage', 'menu'], ['Homepage', 'staff.homepage', 'content.manage', 'homepage'],
        ],
        'Administration' => [
            ['Locations', 'staff.locations', 'locations.read', 'location'], ['Modules', 'staff.modules', 'features.read', 'modules'], ['Staff access', 'staff.access', 'roles.manage', 'access'],
            ['Security', 'staff.security', null, 'security'], ['Settings', 'staff.settings', 'settings.read', 'settings'],
        ],
    ];
@endphp
@if ($user)
    @foreach ($groups as $group => $items)
        @php($visible = collect($items)->contains(fn ($item) => $item[2] === null || $permissions->allows($user, $item[2])))
        @if($visible)
            <div class="workspace-nav-group"><p>{{ $group }}</p>
            @foreach ($items as [$label, $route, $permission, $icon])
                @if (($label !== 'Digital school' || in_array('digital_school', $enabledFeatures, true)) && ($permission === null || $permissions->allows($user, $permission)))
                    <a href="{{ route($route) }}" @if (request()->routeIs($route, $route.'.*')) aria-current="page" @endif><x-ui.icon :name="$icon"/><span>{{ $label }}</span></a>
                @endif
            @endforeach
            @if ($group === 'Ministry' && $departmentTeams->canSeeAny($user))
                <a href="{{ route('staff.departments') }}" @if (request()->routeIs('staff.departments*')) aria-current="page" @endif><x-ui.icon name="groups"/><span>Departments</span></a>
            @endif
            </div>
        @endif
    @endforeach
@endif
