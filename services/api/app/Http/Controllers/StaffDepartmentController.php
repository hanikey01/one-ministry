<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\DepartmentService;
use App\Services\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        return view('staff.departments', ['settings' => $settings, 'departments' => $items, 'imageAssets' => $this->departmentImageAssets(), 'user' => $request->user(), 'canManageDepartments' => $permissions->allows($request->user(), 'departments.manage')]);
    }

    public function store(Request $request, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:departments,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url'=>['nullable','url:http,https','max:1000'], 'image_alt'=>['nullable','string','max:255'],
            'media_asset_id'=>['nullable','uuid'], 'image_upload'=>['nullable','image','mimes:jpg,jpeg,png,webp,gif','max:10240'],
            'category'=>['nullable','string','max:80'], 'contact_email'=>['nullable','email','max:191'], 'featured'=>['nullable','boolean'],
            'is_public' => ['nullable', 'boolean'], 'accepting_requests' => ['nullable', 'boolean'], 'discussion_enabled' => ['nullable', 'boolean'],
        ]);
        $data = $this->resolveDepartmentImage($request, $data);
        $data['is_public'] = $request->has('is_public') ? $request->boolean('is_public') : true;
        $data['accepting_requests'] = $request->boolean('accepting_requests');
        $data['discussion_enabled'] = $request->boolean('discussion_enabled');
        $data['featured'] = $request->boolean('featured');
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
        $joinRequests = DB::table('department_join_requests')->join('people', 'people.id', '=', 'department_join_requests.person_id')
            ->where('department_join_requests.department_id', $department)->where('department_join_requests.status', 'pending')
            ->orderBy('department_join_requests.created_at')->get(['department_join_requests.*', 'people.first_name', 'people.last_name', 'people.email']);
        $messages = DB::table('department_messages')->join('people', 'people.id', '=', 'department_messages.person_id')
            ->where('department_messages.department_id', $department)->whereNull('department_messages.moderated_at')
            ->latest('department_messages.created_at')->limit(50)->get(['department_messages.*', 'people.first_name', 'people.last_name']);

        return view('staff.department', compact('settings', 'members', 'people', 'joinRequests', 'messages') + ['department' => $item, 'imageAssets' => $this->departmentImageAssets(), 'user' => $request->user(), 'isAdministrator' => $permissions->allows($request->user(), 'departments.manage')]);
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

    public function update(Request $request, string $department, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate([
            'name'=>['sometimes','required','string','max:160','unique:departments,name,'.$department],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url'=>['nullable','url:http,https','max:1000'], 'image_alt'=>['nullable','string','max:255'],
            'media_asset_id'=>['nullable','uuid'], 'image_upload'=>['nullable','image','mimes:jpg,jpeg,png,webp,gif','max:10240'],
            'category'=>['nullable','string','max:80'], 'contact_email'=>['nullable','email','max:191'], 'featured'=>['nullable','boolean'],
            'is_public' => ['nullable', 'boolean'], 'accepting_requests' => ['nullable', 'boolean'], 'discussion_enabled' => ['nullable', 'boolean'],
        ]);
        $data = $this->resolveDepartmentImage($request, $data);
        $departments->updateDepartment($department, [...$data, 'featured'=>$request->boolean('featured'), 'is_public' => $request->boolean('is_public'), 'accepting_requests' => $request->boolean('accepting_requests'), 'discussion_enabled' => $request->boolean('discussion_enabled')], $request->user(), $request->ip());
        return to_route('staff.departments.show', $department)->with('status', 'Department settings saved.');
    }

    public function reviewJoinRequest(Request $request, string $department, string $joinRequest, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'declined'])]]);
        $departments->reviewRequest($department, $joinRequest, $data['status'], $request->user(), $request->ip());
        return to_route('staff.departments.show', $department)->with('status', 'Join request reviewed.');
    }

    public function removeMessage(Request $request, string $department, string $message, DepartmentService $departments): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);
        $departments->removeMessage($department, $message, $request->user(), $data['reason'], $request->ip());
        return to_route('staff.departments.show', $department)->with('status', 'Discussion message removed.');
    }

    /** @return Collection<int, object> */
    private function departmentImageAssets(): Collection
    {
        return DB::table('media_assets')->where('library', 'media_library')->where('status', 'published')
            ->where('file_mime', 'like', 'image/%')->orderByDesc('published_at')->orderByDesc('created_at')
            ->get(['id', 'title', 'alt_text'])->map(function (object $asset): object {
                $asset->url = route('public.media', $asset->id);
                return $asset;
            });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveDepartmentImage(Request $request, array $data): array
    {
        $assetId = $data['media_asset_id'] ?? null;
        unset($data['media_asset_id'], $data['image_upload']);
        if ($request->hasFile('image_upload')) {
            $file = $request->file('image_upload');
            $assetId = (string) Str::uuid();
            $path = $file->store('media-library', 'local');
            $title = str($file->getClientOriginalName())->beforeLast('.')->replace(['-', '_'], ' ')->headline()->toString();
            DB::table('media_assets')->insert([
                'id'=>$assetId, 'title'=>$title, 'description'=>null, 'provider'=>'local', 'provider_key'=>$path,
                'library'=>'media_library', 'kind'=>'image', 'status'=>'published', 'published_at'=>now(),
                'file_disk'=>'local', 'file_path'=>$path, 'file_name'=>$file->getClientOriginalName(),
                'file_mime'=>$file->getMimeType(), 'file_size_bytes'=>$file->getSize(),
                'alt_text'=>$data['image_alt'] ?: null, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
        if ($assetId) {
            $asset = DB::table('media_assets')->where(['id'=>$assetId, 'library'=>'media_library', 'status'=>'published'])
                ->where('file_mime', 'like', 'image/%')->first();
            abort_unless($asset, 422, 'Choose a published image from the ministry Media Library.');
            $data['image_url'] = route('public.media', $asset->id);
            if (blank($data['image_alt']) && filled($asset->alt_text)) $data['image_alt'] = $asset->alt_text;
        }
        return $data;
    }
}

