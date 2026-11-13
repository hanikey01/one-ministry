<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class PublicSchoolController extends Controller
{
    public function index(): View
    {
        $this->enabled();
        $courses = DB::table('school_courses')->whereNull('deleted_at')->where('status', 'published')
            ->orderByRaw('case when starts_at is null then 1 else 0 end')->orderBy('starts_at')->get();

        return $this->view('public.school.index', compact('courses'));
    }

    public function show(string $course): View
    {
        $this->enabled();
        $courseRow = DB::table('school_courses')->whereNull('deleted_at')->where(['id' => $course, 'status' => 'published'])->first();
        abort_unless($courseRow, 404);
        $previewLessons = DB::table('school_lessons')->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')
            ->where('school_course_modules.course_id', $course)->where('school_course_modules.published', true)
            ->where('school_lessons.published', true)->where('school_lessons.preview', true)
            ->orderBy('school_course_modules.position')->orderBy('school_lessons.position')
            ->get(['school_lessons.*', 'school_course_modules.title as module_title']);

        return $this->view('public.school.show', ['course' => $courseRow, 'previewLessons' => $previewLessons]);
    }

    private function enabled(): void
    {
        abort_unless(FeatureSetting::query()->where(['key' => 'digital_school', 'state' => 'enabled'])->exists(), 404);
    }

    private function view(string $view, array $data): View
    {
        return view($view, [...$data, 'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'features' => FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all()]);
    }
}
