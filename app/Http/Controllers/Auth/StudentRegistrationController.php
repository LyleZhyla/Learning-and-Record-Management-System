<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRegistrationRequest;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Models\NstpSection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StudentRegistrationController extends Controller
{
    public function create(): View
    {
        $registrationSemester = SystemSetting::studentRegistrationSemester();
        $registrationNstpLevel = StudentRegistration::nstpLevelForSemester($registrationSemester);

        return view('auth.register', [
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => $registrationSemester,
            'registrationSemesterLabel' => NstpSection::SEMESTERS[$registrationSemester] ?? str($registrationSemester)->headline(),
            'registrationNstpLevel' => $registrationNstpLevel,
            'registrationNstpLevelLabel' => StudentRegistration::NSTP_LEVELS[$registrationNstpLevel],
            'locationEndpoints' => [
                'cities' => route('locations.cities', ['provinceCode' => '__CODE__'], false),
                'barangays' => route('locations.barangays', ['cityCode' => '__CODE__'], false),
            ],
        ]);
    }

    public function store(StoreStudentRegistrationRequest $request): RedirectResponse
    {
        if (! SystemSetting::studentRegistrationIsOpen()) {
            throw ValidationException::withMessages([
                'registration' => 'Student registration is currently closed. Please wait for the NSTP Office to open the next registration period.',
            ]);
        }

        $validated = $request->validated();
        $validated['academic_year'] = SystemSetting::studentRegistrationAcademicYear();
        $validated['semester'] = SystemSetting::studentRegistrationSemester();
        $validated['nstp_level'] = StudentRegistration::nstpLevelForSemester($validated['semester']);
        $corPath = null;
        $photoPath = null;

        try {
            $corPath = $request->file('cor')->store('student-registrations/cor', 'local');
            $photoPath = $request->file('formal_photo')->store('student-registrations/formal-photos', 'local');
            $corOriginalName = $request->file('cor')->getClientOriginalName();
            $photoOriginalName = $request->file('formal_photo')->getClientOriginalName();

            $registration = DB::transaction(function () use ($validated, $corPath, $photoPath, $corOriginalName, $photoOriginalName): StudentRegistration {
                $validated['religion'] = $validated['religion_selection'] === 'Others'
                    ? $validated['religion_other']
                    : $validated['religion_selection'];
                $validated['year_section'] = $validated['year_section_selection'] === 'Others'
                    ? $validated['year_section_other']
                    : $validated['year_section_selection'];
                unset(
                    $validated['cor'], $validated['formal_photo'], $validated['privacy_consent'],
                    $validated['extension_name_na'], $validated['middle_name_na'],
                    $validated['religion_selection'], $validated['religion_other'],
                    $validated['year_section_selection'], $validated['year_section_other']
                );

                return StudentRegistration::create([
                    ...$validated,
                    'reference_code' => $this->referenceCode(),
                    'status' => 'pending',
                    'cor_path' => $corPath,
                    'cor_original_name' => $corOriginalName,
                    'formal_photo_path' => $photoPath,
                    'formal_photo_original_name' => $photoOriginalName,
                ]);
            });
        } catch (Throwable $exception) {
            if ($corPath) {
                Storage::disk('local')->delete($corPath);
            }
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            if ($exception instanceof QueryException && in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)) {
                $message = Str::lower($exception->getMessage());
                $field = Str::contains($message, 'student_number')
                    ? 'student_number'
                    : (Str::contains($message, 'email') ? 'email' : null);

                if ($field) {
                    throw ValidationException::withMessages([
                        $field => $field === 'student_number'
                            ? 'A student is already registered with this student number.'
                            : 'An account or registration already uses this email address.',
                    ]);
                }
            }

            throw $exception;
        }

        return redirect()->route('register')->with([
            'status' => 'Your student registration was submitted successfully.',
            'reference_code' => $registration->reference_code,
        ]);
    }

    private function referenceCode(): string
    {
        do {
            $reference = 'NSTP-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (StudentRegistration::where('reference_code', $reference)->exists());

        return $reference;
    }
}
