<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\CertificateQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffSchoolController extends Controller
{
    public function index(Request $request): View
    {
        $this->enabled();
        $canManage = $this->canManage($request);
        abort_unless($canManage || $this->canInstruct($request), 403);
        $courses = DB::table('school_courses')->whereNull('deleted_at')
            ->when(! $canManage, fn ($query) => $query->whereIn('id', DB::table('school_course_instructors')->where('user_id', $request->user()->id)->pluck('course_id')))
            ->latest()->get();
        $stats = [
            'courses' => $courses->count(),
            'published' => $courses->where('status', 'published')->count(),
            'learners' => DB::table('school_enrollments')->whereIn('status', ['active', 'completed'])->distinct()->count('person_id'),
            'classes' => DB::table('school_live_classes')->where('starts_at', '>=', now())->count(),
        ];

        return view('staff.school.index', $this->base($request) + compact('courses', 'stats', 'canManage'));
    }

    public function show(Request $request, string $course): View
    {
        $this->enabled();
        $canManage = $this->accessCourse($request, $course);
        $courseRow = DB::table('school_courses')->whereNull('deleted_at')->where('id', $course)->first();
        abort_unless($courseRow, 404);
        $modules = DB::table('school_course_modules')->where('course_id', $course)->orderBy('position')->get();
        $lessons = DB::table('school_lessons')->whereIn('module_id', $modules->pluck('id'))->orderBy('position')->get()->groupBy('module_id');
        $assessments = DB::table('school_assessments')->where('course_id', $course)->get();
        $questions = DB::table('school_assessment_questions')->whereIn('assessment_id', $assessments->pluck('id'))->orderBy('position')->get()->groupBy('assessment_id');
        $classes = DB::table('school_live_classes')->where('course_id', $course)->orderBy('starts_at')->get();
        $enrollments = DB::table('school_enrollments')->join('people', 'people.id', '=', 'school_enrollments.person_id')
            ->where('course_id', $course)->orderByDesc('enrolled_at')->get(['school_enrollments.*', 'people.first_name', 'people.last_name', 'people.email']);
          $people = DB::table('people')->whereNotIn('id', $enrollments->pluck('person_id'))->where('status', 'member')
            ->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']);
        $certificates = DB::table('school_certificates')
            ->join('school_enrollments', 'school_enrollments.id', '=', 'school_certificates.enrollment_id')
            ->join('people', 'people.id', '=', 'school_enrollments.person_id')
            ->where('school_enrollments.course_id', $course)
            ->latest('school_certificates.issued_at')
            ->get(['school_certificates.*', 'people.first_name', 'people.last_name', 'people.email']);
        $instructors = DB::table('school_course_instructors')
            ->join('users', 'users.id', '=', 'school_course_instructors.user_id')
            ->where('school_course_instructors.course_id', $course)
            ->orderBy('users.name')
            ->get(['school_course_instructors.*', 'users.name', 'users.email']);
        $instructorCandidates = $canManage ? DB::table('users')
            ->join('user_role_assignments', 'user_role_assignments.user_id', '=', 'users.id')
            ->join('role_permissions', 'role_permissions.role_key', '=', 'user_role_assignments.role_key')
            ->where('role_permissions.permission_key', 'school.instruct')
            ->whereNotIn('users.id', $instructors->pluck('user_id'))
            ->select('users.id', 'users.name', 'users.email')->distinct()->orderBy('users.name')->get() : collect();
        $analytics = [
            'active' => $enrollments->where('status', 'active')->count(),
            'completed' => $enrollments->where('status', 'completed')->count(),
            'pending' => $enrollments->where('status', 'pending')->count(),
            'completion_rate' => $enrollments->whereIn('status', ['active', 'completed'])->count() > 0
                ? (int) round(($enrollments->where('status', 'completed')->count() / $enrollments->whereIn('status', ['active', 'completed'])->count()) * 100) : 0,
        ];

        return view('staff.school.show', $this->base($request) + ['course' => $courseRow] + compact('modules', 'lessons', 'assessments', 'questions', 'classes', 'enrollments', 'people', 'certificates', 'canManage', 'instructors', 'instructorCandidates', 'analytics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'slug' => ['nullable', 'string', 'max:220', 'unique:school_courses,slug'],
            'category' => ['nullable', 'string', 'max:100'], 'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'all_levels'])],
            'instructor_name' => ['nullable', 'string', 'max:160'], 'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:20000'], 'enrollment_policy' => ['required', Rule::in(['open', 'approval', 'invite_only'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'certificate_template_key' => ['nullable', Rule::in(['classic', 'modern', 'minimal'])],
        ]);
        $data['certificate_template_key'] ??= 'classic';
        $id = (string) Str::uuid();
        $slug = Str::slug(($data['slug'] ?? null) ?: $data['title']);
        if (DB::table('school_courses')->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }
        DB::table('school_courses')->insert([...$data, 'id' => $id, 'slug' => $slug, 'status' => 'draft', 'certificate_enabled' => $request->boolean('certificate_enabled'), 'passing_score' => 70, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'school.course_created', 'school_courses', $id, ['title' => $data['title']]);

        return to_route('staff.school.show', $id)->with('status', 'Course created. Build its learning path below.');
    }

    public function update(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'category' => ['nullable', 'string', 'max:100'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'all_levels'])], 'instructor_name' => ['nullable', 'string', 'max:160'],
            'cover_image_url' => ['nullable', 'url', 'max:500'], 'summary' => ['nullable', 'string', 'max:1000'], 'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'enrollment_policy' => ['required', Rule::in(['open', 'approval', 'invite_only'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'], 'passing_score' => ['required', 'integer', 'between:1,100'],
            'certificate_template_key' => ['nullable', Rule::in(['classic', 'modern', 'minimal'])],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $data['certificate_template_key'] ??= 'classic';
        if ($data['status'] === 'published' && ! DB::table('school_lessons')->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')->where('school_course_modules.course_id', $course)->where('school_course_modules.published', true)->where('school_lessons.published', true)->exists()) {
            throw ValidationException::withMessages(['status' => 'Add at least one published lesson before publishing this course.']);
        }
        DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->update([...$data, 'certificate_enabled' => $request->boolean('certificate_enabled'), 'updated_at' => now()]);
        $this->audit($request, 'school.course_updated', 'school_courses', $course, ['status' => $data['status']]);

        return back()->with('status', 'Course settings saved.');
    }

    public function updateCertificateTemplate(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate(['certificate_template_key' => ['required', Rule::in(['classic', 'modern', 'minimal'])]]);
        abort_unless(DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->exists(), 404);
        DB::table('school_courses')->where('id', $course)->update([...$data, 'updated_at' => now()]);
        $this->audit($request, 'school.certificate_template_updated', 'school_courses', $course, $data);

        return back()->with('status', 'Certificate design saved. Future certificates will use this design.');
    }

    public function certificatePreview(Request $request, string $course, CertificateQrCode $qrCode): View
    {
        $this->enabled();
        $this->accessCourse($request, $course);
        $courseRow = DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->first();
        abort_unless($courseRow, 404);

        $names = preg_split('/\s+/', trim($request->user()->name), 2);
        $person = (object) ['first_name' => $names[0] ?: 'Sample', 'last_name' => $names[1] ?? 'Learner'];
        $certificate = (object) [
            'course_title' => $courseRow->title,
            'current_course_title' => $courseRow->title,
            'certificate_number' => 'PREVIEW-'.strtoupper(Str::random(8)),
            'issued_at' => now(),
            'template_key' => $courseRow->certificate_template_key ?: 'classic',
            'verification_code' => null,
        ];

        $verificationUrl = url('/certificates/PREVIEW-'.strtoupper(Str::random(12)));

        return view('member.school.certificate', [
            ...$this->base($request),
            'person' => $person,
            'certificate' => $certificate,
            'preview' => true,
            'verificationUrl' => $verificationUrl,
            'certificateQrSvg' => $qrCode->svg($verificationUrl),
            'features' => DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all(),
        ]);
    }

    public function module(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->exists(), 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:4000']]);
        $position = (int) DB::table('school_course_modules')->where('course_id', $course)->max('position') + 1;
        DB::table('school_course_modules')->insert([...$data, 'id' => (string) Str::uuid(), 'course_id' => $course, 'position' => $position, 'published' => $request->boolean('published'), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Course module added.');
    }

    public function lesson(Request $request, string $course, string $module): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_course_modules')->where(['id' => $module, 'course_id' => $course])->exists(), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'type' => ['required', Rule::in(['text', 'video', 'audio', 'download', 'live_replay'])],
            'content' => ['nullable', 'string', 'max:50000'], 'media_url' => ['nullable', 'url', 'max:1000'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
        $position = (int) DB::table('school_lessons')->where('module_id', $module)->max('position') + 1;
        DB::table('school_lessons')->insert([...$data, 'id' => (string) Str::uuid(), 'module_id' => $module, 'position' => $position, 'preview' => $request->boolean('preview'), 'published' => $request->boolean('published'), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Lesson added.');
    }

    public function updateModule(Request $request, string $course, string $module): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_course_modules')->where(['id' => $module, 'course_id' => $course])->exists(), 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:4000']]);
        DB::table('school_course_modules')->where('id', $module)->update([...$data, 'published' => $request->boolean('published'), 'updated_at' => now()]);

        return back()->with('status', 'Module updated.');
    }

    public function deleteModule(string $course, string $module): RedirectResponse
    {
        $this->enabled();
        $this->requireManage(request(), $course);
        abort_unless(DB::table('school_course_modules')->where(['id' => $module, 'course_id' => $course])->exists(), 404);
        DB::table('school_course_modules')->where('id', $module)->delete();

        return back()->with('status', 'Module and its lessons were removed.');
    }

    public function updateLesson(Request $request, string $course, string $lesson): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $belongs = DB::table('school_lessons')->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')->where('school_lessons.id', $lesson)->where('school_course_modules.course_id', $course)->exists();
        abort_unless($belongs, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'type' => ['required', Rule::in(['text', 'video', 'audio', 'download', 'live_replay'])], 'content' => ['nullable', 'string', 'max:50000'], 'media_url' => ['nullable', 'url', 'max:1000'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440']]);
        DB::table('school_lessons')->where('id', $lesson)->update([...$data, 'preview' => $request->boolean('preview'), 'published' => $request->boolean('published'), 'updated_at' => now()]);

        return back()->with('status', 'Lesson updated.');
    }

    public function deleteLesson(string $course, string $lesson): RedirectResponse
    {
        $this->enabled();
        $this->requireManage(request(), $course);
        $belongs = DB::table('school_lessons')->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')->where('school_lessons.id', $lesson)->where('school_course_modules.course_id', $course)->exists();
        abort_unless($belongs, 404);
        DB::table('school_lessons')->where('id', $lesson)->delete();

        return back()->with('status', 'Lesson removed.');
    }

    public function assessment(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate([
            'module_id' => ['nullable', Rule::exists('school_course_modules', 'id')->where('course_id', $course)], 'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:4000'], 'passing_score' => ['required', 'integer', 'between:1,100'], 'max_attempts' => ['required', 'integer', 'between:1,10'],
            'question' => ['required', 'string', 'max:2000'], 'options' => ['required', 'string', 'max:4000'], 'correct_answer' => ['required', 'string', 'max:1000'],
        ]);
        $options = collect(preg_split('/\r\n|\r|\n/', $data['options']))->map(fn ($value) => trim($value))->filter()->values();
        if ($options->count() < 2 || ! $options->contains($data['correct_answer'])) {
            return back()->withErrors(['options' => 'Provide at least two options and make sure the correct answer exactly matches one option.'])->withInput();
        }
        $assessmentId = (string) Str::uuid();
        DB::transaction(function () use ($data, $course, $assessmentId, $options, $request): void {
            DB::table('school_assessments')->insert(['id' => $assessmentId, 'course_id' => $course, 'module_id' => $data['module_id'] ?: null, 'title' => $data['title'], 'instructions' => $data['instructions'], 'passing_score' => $data['passing_score'], 'max_attempts' => $data['max_attempts'], 'published' => $request->boolean('published'), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('school_assessment_questions')->insert(['id' => (string) Str::uuid(), 'assessment_id' => $assessmentId, 'prompt' => $data['question'], 'type' => 'single_choice', 'options_json' => json_encode($options), 'correct_answer_json' => json_encode([$data['correct_answer']]), 'points' => 1, 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Assessment added.');
    }

    public function liveClass(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate([
            'module_id' => ['nullable', Rule::exists('school_course_modules', 'id')->where('course_id', $course)], 'meeting_id' => ['nullable', 'exists:meetings,id'],
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:4000'], 'external_url' => ['nullable', 'url', 'max:1000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'timezone' => ['required', 'timezone'],
        ]);
        if (empty($data['meeting_id']) && empty($data['external_url'])) {
            return back()->withErrors(['external_url' => 'Choose a platform meeting or provide an approved live-class link.'])->withInput();
        }
        DB::table('school_live_classes')->insert([...$data, 'id' => (string) Str::uuid(), 'course_id' => $course, 'module_id' => $data['module_id'] ?: null, 'meeting_id' => $data['meeting_id'] ?: null, 'external_url' => $data['external_url'] ?: null, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Live class scheduled.');
    }

    public function addQuestion(Request $request, string $course, string $assessment): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_assessments')->where(['id' => $assessment, 'course_id' => $course])->exists(), 404);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'], 'type' => ['nullable', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'short_text'])],
            'options' => ['nullable', 'string', 'max:4000'], 'correct_answer' => ['required', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:4000'], 'points' => ['required', 'integer', 'between:1,100'],
        ]);
        $type = $data['type'] ?? 'single_choice';
        $options = collect(preg_split('/\r\n|\r|\n/', (string) $data['options']))->map(fn ($value) => trim($value))->filter()->values();
        if ($type === 'true_false') { $options = collect(['True', 'False']); }
        if (in_array($type, ['single_choice', 'multiple_choice'], true) && $options->count() < 2) {
            return back()->withErrors(['options' => 'Provide at least two answer options.'])->withInput();
        }
        if ($type === 'short_text') { $options = collect(); }
        $answers = $type === 'multiple_choice'
            ? collect(preg_split('/\r\n|\r|\n/', $data['correct_answer']))->map(fn ($value) => trim($value))->filter()->values()
            : collect([$data['correct_answer']]);
        if (! $answers->every(fn ($answer) => $type === 'short_text' || $options->contains($answer))) {
            return back()->withErrors(['correct_answer' => 'Every correct answer must match an available option.'])->withInput();
        }
        $position = (int) DB::table('school_assessment_questions')->where('assessment_id', $assessment)->max('position') + 1;
        DB::table('school_assessment_questions')->insert(['id' => (string) Str::uuid(), 'assessment_id' => $assessment, 'prompt' => $data['question'], 'type' => $type, 'options_json' => json_encode($options->all()), 'correct_answer_json' => json_encode($answers->all()), 'explanation' => $data['explanation'], 'points' => $data['points'], 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Assessment question added.');
    }

    public function enrollment(Request $request, string $course, string $enrollment): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'completed', 'declined', 'withdrawn'])]]);
        abort_unless(DB::table('school_enrollments')->where(['id' => $enrollment, 'course_id' => $course])->exists(), 404);
        $timestamps = match ($data['status']) {
            'active' => ['started_at' => now(), 'completed_at' => null],
            'completed' => ['completed_at' => now()],
            default => [],
        };
        DB::table('school_enrollments')->where('id', $enrollment)->update(['status' => $data['status'], ...$timestamps, 'updated_at' => now()]);
        $this->audit($request, 'school.enrollment_updated', 'school_enrollments', $enrollment, ['status' => $data['status']]);

        return back()->with('status', 'Learner enrolment updated.');
    }

    public function enrollPerson(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->exists(), 404);
        $data = $request->validate(['person_id' => ['required', 'exists:people,id'], 'status' => ['required', Rule::in(['active', 'pending'])]]);
        $existing = DB::table('school_enrollments')->where(['course_id' => $course, 'person_id' => $data['person_id']])->first();
        $values = ['status' => $data['status'], 'progress_percent' => 0, 'enrolled_at' => now(), 'started_at' => $data['status'] === 'active' ? now() : null, 'completed_at' => null, 'updated_at' => now()];
        if ($existing) {
            DB::table('school_enrollments')->where('id', $existing->id)->update($values);
        } else {
            DB::table('school_enrollments')->insert($values + ['id' => (string) Str::uuid(), 'course_id' => $course, 'person_id' => $data['person_id'], 'created_at' => now()]);
        }

        return back()->with('status', 'Learner added to the course.');
    }

    public function updateLiveClass(Request $request, string $course, string $class): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        abort_unless(DB::table('school_live_classes')->where(['id' => $class, 'course_id' => $course])->exists(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['scheduled', 'live', 'completed', 'cancelled'])]]);
        DB::table('school_live_classes')->where('id', $class)->update(['status' => $data['status'], 'updated_at' => now()]);

        return back()->with('status', 'Live class status updated.');
    }

    public function deleteLiveClass(string $course, string $class): RedirectResponse
    {
        $this->enabled();
        $this->requireManage(request(), $course);
        DB::table('school_live_classes')->where(['id' => $class, 'course_id' => $course])->delete();

        return back()->with('status', 'Live class removed.');
    }

    public function revokeCertificate(Request $request, string $course, string $certificate): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $record = DB::table('school_certificates')
            ->join('school_enrollments', 'school_enrollments.id', '=', 'school_certificates.enrollment_id')
            ->where('school_certificates.id', $certificate)
            ->where('school_enrollments.course_id', $course)
            ->first(['school_certificates.id']);
        abort_unless($record, 404);

        DB::table('school_certificates')->where('id', $certificate)->whereNull('revoked_at')->update([
            'revoked_at' => now(), 'revoked_by_user_id' => $request->user()->id, 'revoked_reason' => $data['reason'], 'updated_at' => now(),
        ]);
        $this->audit($request, 'school.certificate_revoked', 'school_certificates', $certificate, ['reason' => $data['reason']]);

        return back()->with('status', 'Certificate revoked. Its public verification link now reports that the credential is no longer valid.');
    }

    public function reissueCertificate(Request $request, string $course, string $certificate): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $record = DB::table('school_certificates')->join('school_enrollments', 'school_enrollments.id', '=', 'school_certificates.enrollment_id')
            ->where('school_certificates.id', $certificate)->where('school_enrollments.course_id', $course)->first(['school_certificates.*']);
        abort_unless($record && $record->revoked_at, 422);

        $courseRow = DB::table('school_courses')->where('id', $course)->first(['title', 'certificate_template_key']);
        DB::table('school_certificates')->insert([
            'id' => (string) Str::uuid(), 'enrollment_id' => $record->enrollment_id, 'supersedes_certificate_id' => $record->id,
            'certificate_number' => 'OMS-'.now()->format('Ym').'-'.strtoupper(Str::random(10)), 'verification_code' => strtoupper(Str::random(16)),
            'recipient_name' => $record->recipient_name, 'course_title' => $courseRow?->title ?: $record->course_title,
            'issuer_name' => $record->issuer_name, 'template_key' => $courseRow?->certificate_template_key ?: $record->template_key ?: 'classic',
            'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($request, 'school.certificate_reissued', 'school_certificates', $certificate, ['superseded_certificate_id' => $certificate]);

        return back()->with('status', 'Replacement certificate issued. The prior verification link remains revoked.');
    }

    public function assignInstructor(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $eligible = DB::table('user_role_assignments')->join('role_permissions', 'role_permissions.role_key', '=', 'user_role_assignments.role_key')
            ->where('user_role_assignments.user_id', $data['user_id'])->where('role_permissions.permission_key', 'school.instruct')->exists();
        abort_unless($eligible, 422);
        DB::table('school_course_instructors')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'course_id' => $course, 'user_id' => $data['user_id'], 'assigned_by_user_id' => $request->user()->id,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit($request, 'school.instructor_assigned', 'school_courses', $course, ['user_id' => $data['user_id']]);
        return back()->with('status', 'Instructor assigned to this course.');
    }

    public function removeInstructor(Request $request, string $course, string $assignment): RedirectResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $record = DB::table('school_course_instructors')->where(['id' => $assignment, 'course_id' => $course])->first();
        abort_unless($record, 404);
        DB::table('school_course_instructors')->where('id', $assignment)->delete();
        $this->audit($request, 'school.instructor_removed', 'school_courses', $course, ['user_id' => $record->user_id]);
        return back()->with('status', 'Instructor removed from this course.');
    }

    public function exportEnrollments(Request $request, string $course): StreamedResponse
    {
        $this->enabled();
        $this->requireManage($request, $course);
        $this->audit($request, 'school.enrollments_exported', 'school_courses', $course, []);
        $rows = DB::table('school_enrollments')->join('people', 'people.id', '=', 'school_enrollments.person_id')->where('school_enrollments.course_id', $course)
            ->orderBy('people.last_name')->orderBy('people.first_name')->get(['people.first_name', 'people.last_name', 'people.email', 'school_enrollments.status', 'school_enrollments.progress_percent', 'school_enrollments.enrolled_at', 'school_enrollments.completed_at']);
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w'); fputcsv($out, ['Learner', 'Email', 'Status', 'Progress %', 'Enrolled', 'Completed']);
            foreach ($rows as $row) { fputcsv($out, [trim($row->first_name.' '.$row->last_name), $row->email, $row->status, $row->progress_percent, $row->enrolled_at, $row->completed_at]); }
            fclose($out);
        }, 'course-enrolments-'.$course.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function enabled(): void
    {
        abort_unless(app(\App\Services\ModuleAvailability::class)->visibleToStaff('digital_school', auth()->user()), 404);
    }

    private function canManage(Request $request): bool
    {
        return in_array('school.manage', app(\App\Services\PermissionChecker::class)->installationPermissions($request->user()), true);
    }

    private function canInstruct(Request $request): bool
    {
        return in_array('school.instruct', app(\App\Services\PermissionChecker::class)->installationPermissions($request->user()), true);
    }

    private function accessCourse(Request $request, string $course): bool
    {
        if ($this->canManage($request)) { return true; }
        abort_unless($this->canInstruct($request) && DB::table('school_course_instructors')->where(['course_id' => $course, 'user_id' => $request->user()->id])->exists(), 403);
        return false;
    }

    private function requireManage(Request $request, ?string $course = null): void
    {
        abort_unless($this->canManage($request), 403);
        if ($course) { abort_unless(DB::table('school_courses')->where('id', $course)->whereNull('deleted_at')->exists(), 404); }
    }

    /** @return array<string, mixed> */
    private function base(Request $request): array
    {
        return ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'user' => $request->user(), 'title' => 'Digital school'];
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, string $action, string $subjectType, string $subjectId, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
