<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\DigitalSchoolService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberSchoolController extends Controller
{
    public function index(Request $request): View
    {
        $this->enabled();
        $person = $this->person($request);
        $courses = DB::table('school_courses')->whereNull('deleted_at')->where('status', 'published')
            ->orderByRaw('case when starts_at is null then 1 else 0 end')->orderBy('starts_at')->get();
        $enrollments = DB::table('school_enrollments')->where('person_id', $person->id)->get()->keyBy('course_id');

        return view('member.school.index', $this->base($request, $person) + compact('courses', 'enrollments'));
    }

    public function show(Request $request, string $course): View
    {
        $this->enabled();
        $person = $this->person($request);
        $courseRow = DB::table('school_courses')->whereNull('deleted_at')->where(['id' => $course, 'status' => 'published'])->first();
        abort_unless($courseRow, 404);
        $enrollment = DB::table('school_enrollments')->where(['course_id' => $course, 'person_id' => $person->id])->first();
        $modules = DB::table('school_course_modules')->where(['course_id' => $course, 'published' => true])->orderBy('position')->get();
        $lessons = DB::table('school_lessons')->whereIn('module_id', $modules->pluck('id'))->where('published', true)->orderBy('position')->get()->groupBy('module_id');
        $progress = $enrollment ? DB::table('school_lesson_progress')->where('enrollment_id', $enrollment->id)->get()->keyBy('lesson_id') : collect();
        $assessments = DB::table('school_assessments')->where(['course_id' => $course, 'published' => true])->get();
        $questions = DB::table('school_assessment_questions')->whereIn('assessment_id', $assessments->pluck('id'))->orderBy('position')->get()->groupBy('assessment_id');
        $attempts = $enrollment ? DB::table('school_assessment_attempts')->where('enrollment_id', $enrollment->id)->latest('submitted_at')->get()->groupBy('assessment_id') : collect();
        $classes = DB::table('school_live_classes')->where('course_id', $course)->whereIn('status', ['scheduled', 'live'])->orderBy('starts_at')->get();
        $certificate = $enrollment ? DB::table('school_certificates')->where('enrollment_id', $enrollment->id)->whereNull('revoked_at')->first() : null;

        return view('member.school.show', $this->base($request, $person) + ['course' => $courseRow] + compact('enrollment', 'modules', 'lessons', 'progress', 'assessments', 'questions', 'attempts', 'classes', 'certificate'));
    }

    public function enroll(Request $request, string $course): RedirectResponse
    {
        $this->enabled();
        $person = $this->person($request);
        $courseRow = DB::table('school_courses')->whereNull('deleted_at')->where(['id' => $course, 'status' => 'published'])->first();
        abort_unless($courseRow, 404);
        if ($courseRow->enrollment_policy === 'invite_only') {
            throw ValidationException::withMessages(['course' => 'This course is available by invitation only.']);
        }
        if ($courseRow->capacity && DB::table('school_enrollments')->where('course_id', $course)->whereIn('status', ['active', 'completed'])->count() >= $courseRow->capacity) {
            throw ValidationException::withMessages(['course' => 'This course has reached its enrolment capacity.']);
        }
        $status = $courseRow->enrollment_policy === 'approval' ? 'pending' : 'active';
        $existing = DB::table('school_enrollments')->where(['course_id' => $course, 'person_id' => $person->id])->first();
        if ($existing && in_array($existing->status, ['withdrawn', 'declined'], true)) {
            DB::table('school_enrollments')->where('id', $existing->id)->update(['status' => $status, 'progress_percent' => 0, 'enrolled_at' => now(), 'started_at' => $status === 'active' ? now() : null, 'completed_at' => null, 'updated_at' => now()]);
        } elseif (! $existing) {
            DB::table('school_enrollments')->insert(['id' => (string) Str::uuid(), 'course_id' => $course, 'person_id' => $person->id, 'status' => $status, 'progress_percent' => 0, 'enrolled_at' => now(), 'started_at' => $status === 'active' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('status', $courseRow->enrollment_policy === 'approval' ? 'Your enrolment request was sent for approval.' : 'You are enrolled. Your learning path is ready.');
    }

    public function completeLesson(Request $request, string $course, string $lesson, DigitalSchoolService $school): RedirectResponse
    {
        $this->enabled();
        $person = $this->person($request);
        $enrollment = DB::table('school_enrollments')->where(['course_id' => $course, 'person_id' => $person->id, 'status' => 'active'])->first();
        abort_unless($enrollment, 403);
        abort_unless(DB::table('school_lessons')->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')->where('school_lessons.id', $lesson)->where('school_course_modules.course_id', $course)->where('school_lessons.published', true)->exists(), 404);
        $existing = DB::table('school_lesson_progress')->where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson])->exists();
        if ($existing) {
            DB::table('school_lesson_progress')->where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson])->update(['status' => 'completed', 'last_viewed_at' => now(), 'completed_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('school_lesson_progress')->insert(['id' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson, 'status' => 'completed', 'progress_seconds' => 0, 'last_viewed_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $school->recalculateProgress($enrollment->id);

        return back()->with('status', 'Lesson completed. Your progress has been updated.');
    }

    public function submitAssessment(Request $request, string $course, string $assessment, DigitalSchoolService $school): RedirectResponse
    {
        $this->enabled();
        $person = $this->person($request);
        $enrollment = DB::table('school_enrollments')->where(['course_id' => $course, 'person_id' => $person->id, 'status' => 'active'])->first();
        abort_unless($enrollment, 403);
        $assessmentRow = DB::table('school_assessments')->where(['id' => $assessment, 'course_id' => $course, 'published' => true])->first();
        abort_unless($assessmentRow, 404);
        $questionIds = DB::table('school_assessment_questions')->where('assessment_id', $assessment)->pluck('id')->all();
        $answers = collect($request->input('answers', []))->only($questionIds)->all();
        if (count($answers) !== count($questionIds)) {
            throw ValidationException::withMessages(['answers' => 'Answer every question before submitting.']);
        }
        $attempt = $school->gradeAssessment($assessmentRow, $enrollment, $answers);

        return back()->with('status', $attempt->passed ? "Assessment passed with {$attempt->score_percent}%." : "You scored {$attempt->score_percent}%. Review the lessons and try again.");
    }

    public function certificate(Request $request, string $course): View
    {
        $this->enabled();
        $person = $this->person($request);
        $record = DB::table('school_certificates')->join('school_enrollments', 'school_enrollments.id', '=', 'school_certificates.enrollment_id')->join('school_courses', 'school_courses.id', '=', 'school_enrollments.course_id')->where('school_courses.id', $course)->where('school_enrollments.person_id', $person->id)->whereNull('school_certificates.revoked_at')->first(['school_certificates.*', 'school_courses.title as course_title']);
        abort_unless($record, 404);

        return view('member.school.certificate', $this->base($request, $person) + ['certificate' => $record]);
    }

    private function enabled(): void
    {
        abort_unless(DB::table('feature_settings')->where(['key' => 'digital_school', 'state' => 'enabled'])->exists(), 404);
    }

    private function person(Request $request): object
    {
        $person = DB::table('people')->where('user_id', $request->user()->id)->first();
        if ($person) {
            return $person;
        }
        $id = (string) Str::uuid();
        $names = preg_split('/\s+/', trim($request->user()->name), 2);
        DB::table('people')->insert(['id' => $id, 'user_id' => $request->user()->id, 'first_name' => $names[0] ?: 'Member', 'last_name' => $names[1] ?? null, 'email' => $request->user()->email, 'status' => 'member', 'consent_to_contact' => false, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('people')->where('id', $id)->first();
    }

    /** @return array<string, mixed> */
    private function base(Request $request, object $person): array
    {
        return [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'user' => $request->user(), 'person' => $person,
            'features' => DB::table('feature_settings')->where('state', 'enabled')->pluck('key')->all(), 'title' => 'Digital school',
        ];
    }
}
