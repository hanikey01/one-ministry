<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class CertificateVerificationController extends Controller
{
    public function show(string $code): View
    {
        $certificate = DB::table('school_certificates')
            ->join('school_enrollments', 'school_enrollments.id', '=', 'school_certificates.enrollment_id')
            ->join('school_courses', 'school_courses.id', '=', 'school_enrollments.course_id')
            ->join('people', 'people.id', '=', 'school_enrollments.person_id')
            ->whereRaw('upper(school_certificates.verification_code) = ?', [strtoupper($code)])
            ->first([
                'school_certificates.*',
                'school_courses.title as current_course_title',
                'people.first_name', 'people.last_name',
            ]);

        return view('public.certificate-verification', [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'certificate' => $certificate,
        ]);
    }
}
