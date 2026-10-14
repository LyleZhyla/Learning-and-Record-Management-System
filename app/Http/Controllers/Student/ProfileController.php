<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\NstpEnrollment;
use App\Models\StudentRegistration;
use App\Services\ProfilePhotoService;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, QrCodeService $qrCode): View
    {
        $user = $request->user()->load('studentProfile');
        if (blank($user->student_qr_token)) {
            $user->save();
        }
        $registration = $user->studentProfile
            ? null
            : StudentRegistration::where('email', $user->email)->latest()->first();
        $details = $user->studentProfile ?? $registration;
        $enrollment = NstpEnrollment::with('component')
            ->where('student_id', $user->id)
            ->where('status', 'enrolled')
            ->latest('id')
            ->first();
        $middleInitial = filled($details?->middle_name)
            ? Str::upper(Str::substr(trim($details->middle_name), 0, 1)).'.'
            : null;
        $idDisplayName = collect([
            $details?->first_name,
            $middleInitial,
            $details?->last_name,
            $details?->extension_name,
        ])->filter()->implode(' ') ?: $user->name;

        return view('student.profile', [
            'user' => $user,
            'details' => $details,
            'idCard' => [
                'student' => $user,
                'details' => $details,
                'enrollment' => $enrollment,
                'displayName' => $idDisplayName,
                'qrSvg' => $qrCode->generateSvg($user->studentQrPayload()),
            ],
            'locationEndpoints' => [
                'cities' => route('locations.cities', ['provinceCode' => '__CODE__'], false),
                'barangays' => route('locations.barangays', ['cityCode' => '__CODE__'], false),
            ],
        ]);
    }

    public function update(UpdateStudentProfileRequest $request, ProfilePhotoService $photos): RedirectResponse
    {
        $user = $request->user()->load('studentProfile');
        $validated = $request->validated();
        $registration = $user->studentProfile?->registration
            ?? StudentRegistration::where('email', $user->email)->latest()->first();

        $validated['religion'] = $validated['religion_selection'] === 'Others'
            ? $validated['religion_other']
            : $validated['religion_selection'];
        $validated['year_section'] = $validated['year_section_selection'] === 'Others'
            ? $validated['year_section_other']
            : $validated['year_section_selection'];

        $profileData = Arr::except($validated, [
            'profile_photo', 'email', 'extension_name_na', 'middle_name_na',
            'religion_selection', 'religion_other', 'year_section_selection', 'year_section_other',
        ]);

        DB::transaction(function () use ($user, $registration, $validated, $profileData): void {
            $name = collect([
                $validated['first_name'],
                $validated['middle_name'],
                $validated['last_name'],
                $validated['extension_name'],
            ])->filter(fn (?string $part): bool => filled($part))->implode(' ');

            $user->fill(['name' => $name, 'email' => $validated['email']]);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();

            $user->studentProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [...$profileData, 'student_registration_id' => $registration?->id]
            );
        });

        if ($request->hasFile('profile_photo')) {
            $photos->replace($user, $request->file('profile_photo'));
        }

        return redirect()->route('student.profile.edit')->with([
            'status' => 'Your student information was updated successfully.',
            'profile_saved' => true,
        ]);
    }
}
