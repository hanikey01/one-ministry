<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\DepartmentService;
use App\Services\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffDepartmentController extends Controller
{
    public function index(Request $request, DepartmentService $departments, PermissionChecker $permissions): View
    {
        abort_unless($departments->canSeeAny($request->user()), 403);
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $countQuery = DB::table('department_memberships')->where('status', 'active')->selectRaw('department_id, count(*) as member_count')->groupBy('department_id');
        $items = DB::table('departments')->leftJoinSub($countQuery, 'membership_counts', fn ($join) => $join->on('membership_counts.department_id', '=', 'departments.id'))
            ->where('departments.status', 'active')
            ->select('departments.*', DB::raw('coalesce(membership_counts.member_count, 0) as member_count'))
            ->orderBy('departments.name')->get();
        if (! $permissions->allows($request->user(), 'departments.manage')) {
            $ids = DB::table('department_memberships')->join('people', 'people.id', '=', 'department_memberships.person_id')
                ->where('people.user_id', $request->user()->id)->where('department_memberships.status', 'active')
                ->whereIn('department_memberships.role', [DepartmentService::COORDINATOR, DepartmentService::EXECUTIVE])->pluck('department_memberships.department_id');
            $items = $items->whereIn('id', $ids);
        }

        return view('staff.departments', ['settings' => $settings, 'departments' => $items, 'user' => $request->user(), 'canManageDepartments' => $permissions->allows($request->user(), 'departments.manage')]);
    }

    public function store(Request $request, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160', 'unique:departments,name'], 'description' => ['nullable', 'string', 'max:2000']]);
        $departments->createDepartment($data, $request->user(), $request->ip());

        return to_route('staff.departments')->with('status', 'Department created.');
    }

    public function show(Request $request, string $department, DepartmentService $departments, PermissionChecker $permissions): View
    {
        abort_unless($departments->canView($request->user(), $department), 403);
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $item = DB::table('departments')->where('id', $department)->where('status', 'active')->first();
        abort_unless($item, 404);
        $members = DB::table('department_memberships')->join('people', 'people.id', '=', 'department_memberships.person_id')
            ->leftJoin('users', 'users.id', '=', 'people.user_id')
            ->where('department_memberships.department_id', $department)->where('department_memberships.status', 'active')
            ->orderByRaw("case department_memberships.role when 'executive' then 1 when 'coordinator' then 2 else 3 end")
            ->orderBy('people.first_name')->select('department_memberships.*', 'people.first_name', 'people.last_name', 'people.email', 'users.id as user_id')->get();
        $people = DB::table('people')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email', 'user_id']);

        return view('staff.department', ['settings' => $settings, 'department' => $item, 'members' => $members, 'people' => $people, 'user' => $request->user(), 'isAdministrator' => $permissions->allows($request->user(), 'departments.manage')]);
    }

    public function addMember(Request $request, string $department, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate(['person_id' => ['required', 'uuid', 'exists:people,id'], 'role' => ['required', Rule::in([DepartmentService::MEMBER, DepartmentService::COORDINATOR, DepartmentService::EXECUTIVE])]]);
        $departments->addMember($department, $data['person_id'], $data['role'], $request->user(), $request->ip());

        return to_route('staff.departments.show', $department)->with('status', 'Team membership saved.');
    }

    public function removeMember(Request $request, string $department, string $membership, DepartmentService $departments): RedirectResponse
    {
        $departments->removeMember($department, $membership, $request->user(), $request->ip());

        return to_route('staff.departments.show', $department)->with('status', 'Team member removed.');
    }
}

