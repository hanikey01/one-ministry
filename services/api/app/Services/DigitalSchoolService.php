<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalSchoolService
{
    public function recalculateProgress(string $enrollmentId): object
    {
        $enrollment = DB::table('school_enrollments')->where('id', $enrollmentId)->first();
        abort_unless($enrollment, 404);

        $lessonCount = DB::table('school_lessons')
            ->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')
            ->where('school_course_modules.course_id', $enrollment->course_id)
            ->where('school_course_modules.published', true)
            ->where('school_lessons.published', true)
            ->count();
        $completed = DB::table('school_lesson_progress')
            ->join('school_lessons', 'school_lessons.id', '=', 'school_lesson_progress.lesson_id')
            ->join('school_course_modules', 'school_course_modules.id', '=', 'school_lessons.module_id')
            ->where('school_lesson_progress.enrollment_id', $enrollmentId)
            ->where('school_course_modules.course_id', $enrollment->course_id)
            ->where('school_lesson_progress.status', 'completed')
            ->count();
        $percent = $lessonCount === 0 ? 0 : (int) floor(($completed / $lessonCount) * 100);

        $requiredAssessments = DB::table('school_assessments')->where('course_id', $enrollment->course_id)->where('published', true)->pluck('id');
        $passedAssessments = DB::table('school_assessment_attempts')
            ->where('enrollment_id', $enrollmentId)
            ->whereIn('assessment_id', $requiredAssessments)
            ->where('passed', true)
            ->distinct()->count('assessment_id');
        $complete = $lessonCount > 0 && $completed === $lessonCount && $passedAssessments === $requiredAssessments->count();

        DB::table('school_enrollments')->where('id', $enrollmentId)->update([
            'progress_percent' => $complete ? 100 : $percent,
            'status' => $complete ? 'completed' : 'active',
            'started_at' => $enrollment->started_at ?: now(),
            'completed_at' => $complete ? ($enrollment->completed_at ?: now()) : null,
            'updated_at' => now(),
        ]);

        if ($complete && DB::table('school_courses')->where('id', $enrollment->course_id)->value('certificate_enabled')) {
            DB::table('school_certificates')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'enrollment_id' => $enrollmentId,
                'certificate_number' => 'OMS-'.now()->format('Ym').'-'.strtoupper(Str::random(10)),
                'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return DB::table('school_enrollments')->where('id', $enrollmentId)->first();
    }

    /** @param array<string, mixed> $answers */
    public function gradeAssessment(object $assessment, object $enrollment, array $answers): object
    {
        $attemptNumber = DB::table('school_assessment_attempts')
            ->where('assessment_id', $assessment->id)->where('enrollment_id', $enrollment->id)->count() + 1;
        if ($attemptNumber > $assessment->max_attempts) {
            throw ValidationException::withMessages(['assessment' => 'You have used every allowed attempt for this assessment.']);
        }

        $earned = 0;
        $possible = 0;
        $questions = DB::table('school_assessment_questions')->where('assessment_id', $assessment->id)->orderBy('position')->get();
        foreach ($questions as $question) {
            $possible += $question->points;
            $expected = json_decode($question->correct_answer_json, true);
            $actual = $answers[$question->id] ?? null;
            $normalize = fn ($value) => collect(is_array($value) ? $value : [$value])->map(fn ($item) => mb_strtolower(trim((string) $item)))->sort()->values()->all();
            if ($normalize($actual) === $normalize($expected)) {
                $earned += $question->points;
            }
        }
        $score = $possible > 0 ? (int) round(($earned / $possible) * 100) : 0;
        $passed = $score >= $assessment->passing_score;
        $id = (string) Str::uuid();
        DB::table('school_assessment_attempts')->insert([
            'id' => $id, 'assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id,
            'attempt_number' => $attemptNumber, 'answers_json' => json_encode($answers),
            'earned_points' => $earned, 'possible_points' => $possible, 'score_percent' => $score,
            'passed' => $passed, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->recalculateProgress($enrollment->id);

        return DB::table('school_assessment_attempts')->where('id', $id)->first();
    }
}
