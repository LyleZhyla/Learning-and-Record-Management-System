<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'extension_name' => $this->boolean('extension_name_na') ? null : $this->input('extension_name'),
            'middle_name' => $this->boolean('middle_name_na') ? null : $this->input('middle_name'),
            'emergency_address' => $this->boolean('emergency_same_address') ? null : $this->input('emergency_address'),
        ]);
    }

    public function rules(): array
    {
        $profile = $this->user()->studentProfile;
        $registrationId = $profile?->student_registration_id
            ?? StudentRegistration::where('email', $this->user()->email)->value('id');

        return [
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'extension_name_na' => ['nullable', 'boolean'],
            'extension_name' => ['nullable', 'required_unless:extension_name_na,1', 'string', 'max:30'],
            'middle_name_na' => ['nullable', 'boolean'],
            'middle_name' => ['nullable', 'required_unless:middle_name_na,1', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:120'],
            'province_code' => ['required', 'string', Rule::in(array_keys(config('philippine_locations.provinces', [])))],
            'city_municipality' => ['required', 'string', 'max:120'],
            'city_municipality_code' => ['required', 'string', 'max:12'],
            'barangay' => ['required', 'string', 'max:120'],
            'barangay_code' => ['required', 'string', 'max:12'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'birth_province' => ['required', 'string', 'max:120'],
            'birth_province_code' => ['required', 'string', Rule::in(array_keys(config('philippine_locations.provinces', [])))],
            'birth_city_municipality' => ['required', 'string', 'max:120'],
            'birth_city_municipality_code' => ['required', 'string', 'max:12'],
            'religion_selection' => ['required', Rule::in(config('student_details.religions', []))],
            'religion_other' => ['nullable', 'required_if:religion_selection,Others', 'string', 'max:120'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'blood_type' => ['required', Rule::in(config('student_details.blood_types', []))],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                Rule::unique(StudentRegistration::class, 'email')->ignore($registrationId),
            ],
            'emergency_contact_name' => ['required', 'string', 'max:150'],
            'emergency_relationship' => ['required', Rule::in(config('student_details.relationships', []))],
            'emergency_contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'emergency_same_address' => ['nullable', 'boolean'],
            'emergency_address' => ['nullable', 'required_unless:emergency_same_address,1', 'string', 'max:500'],
            'student_number' => [
                'required', 'regex:/^20\d{8}$/',
                Rule::unique(StudentProfile::class)->ignore($profile?->id),
                Rule::unique(StudentRegistration::class)->ignore($registrationId),
            ],
            'college' => ['required', 'string', Rule::in(array_keys(config('academics.colleges', [])))],
            'course' => ['required', 'string', 'max:150'],
            'major' => ['required', 'string', 'max:150'],
            'year_section_selection' => ['required', Rule::in(config('student_details.year_sections', []))],
            'year_section_other' => ['nullable', 'required_if:year_section_selection,Others', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $programs = config('academics.colleges', [])[$this->input('college')] ?? null;
                $majors = is_array($programs) ? ($programs[$this->input('course')] ?? null) : null;

                if (! is_array($majors)) {
                    $validator->errors()->add('course', 'Select a course offered by the chosen college.');
                } elseif (! in_array($this->input('major'), $majors, true)) {
                    $validator->errors()->add('major', 'Select a major available for the chosen course.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Enter an 11-digit mobile number beginning with 09.',
            'emergency_contact_number.regex' => 'Enter an 11-digit mobile number beginning with 09.',
            'student_number.regex' => 'The student number must contain 10 digits and begin with 20.',
        ];
    }
}
