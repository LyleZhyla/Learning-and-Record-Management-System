<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRegistrationRequest extends FormRequest
{
    private const RELATIONSHIPS = [
        'Mother', 'Father', 'Sibling', 'Aunt', 'Uncle', 'Cousin',
        'Nephew', 'Niece', 'Grandmother', 'Grandfather', 'Guardian',
    ];

    private const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'extension_name' => $this->boolean('extension_name_na') ? null : $this->input('extension_name'),
            'middle_name' => $this->boolean('middle_name_na') ? null : $this->input('middle_name'),
            'emergency_address' => $this->boolean('emergency_same_address') ? null : $this->input('emergency_address'),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'student_number' => trim((string) $this->input('student_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'cor' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'formal_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'extension_name_na' => ['nullable', 'boolean'],
            'extension_name' => ['nullable', 'required_unless:extension_name_na,1', 'string', 'max:30'],
            'middle_name_na' => ['nullable', 'boolean'],
            'middle_name' => ['nullable', 'required_unless:middle_name_na,1', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:120'],
            'province_code' => ['nullable', 'string', 'max:12'],
            'city_municipality' => ['required', 'string', 'max:120'],
            'city_municipality_code' => ['nullable', 'string', 'max:12'],
            'barangay' => ['required', 'string', 'max:120'],
            'barangay_code' => ['nullable', 'string', 'max:12'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'birth_province' => ['required', 'string', 'max:120'],
            'birth_province_code' => ['nullable', 'string', 'max:12'],
            'birth_city_municipality' => ['required', 'string', 'max:120'],
            'birth_city_municipality_code' => ['nullable', 'string', 'max:12'],
            'religion_selection' => ['required', 'string', 'max:120'],
            'religion_other' => ['nullable', 'required_if:religion_selection,Others', 'string', 'max:120'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'blood_type' => ['required', Rule::in(self::BLOOD_TYPES)],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique(StudentRegistration::class, 'email'),
                Rule::unique(User::class, 'email'),
            ],
            'emergency_contact_name' => ['required', 'string', 'max:150'],
            'emergency_relationship' => ['required', Rule::in(self::RELATIONSHIPS)],
            'emergency_contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'emergency_same_address' => ['nullable', 'boolean'],
            'emergency_address' => ['nullable', 'required_unless:emergency_same_address,1', 'string', 'max:500'],
            'student_number' => [
                'required', 'regex:/^20\d{8}$/',
                Rule::unique(StudentRegistration::class),
                Rule::unique(StudentProfile::class),
            ],
            'college' => ['required', 'string', Rule::in(array_keys(config('academics.colleges', [])))],
            'course' => ['required', 'string', 'max:150'],
            'major' => ['required', 'string', 'max:150'],
            'year_section_selection' => ['required', Rule::in(['1A', '1B', '1C', '1D', '1E', '1F', 'Others'])],
            'year_section_other' => ['nullable', 'required_if:year_section_selection,Others', 'string', 'max:80'],
            'privacy_consent' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $programs = config('academics.colleges', [])[$this->input('college')] ?? null;

                if (! is_array($programs)) {
                    return;
                }

                $majors = $programs[$this->input('course')] ?? null;

                if (! is_array($majors)) {
                    $validator->errors()->add('course', 'Select a course offered by the chosen college.');

                    return;
                }

                if (! in_array($this->input('major'), $majors, true)) {
                    $validator->errors()->add('major', 'Select a major available for the chosen course.');
                }
            },
            function (Validator $validator): void {
                $email = Str::lower(trim((string) $this->input('email')));
                if (filled($email) && ! $validator->errors()->has('email') && (
                    StudentRegistration::whereRaw('LOWER(email) = ?', [$email])->exists()
                    || User::whereRaw('LOWER(email) = ?', [$email])->exists()
                )) {
                    $validator->errors()->add('email', 'An account or registration already uses this email address.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cor.required' => 'Upload your Certificate of Registration before continuing.',
            'cor.mimes' => 'The COR must be a PDF, JPG, JPEG, or PNG file.',
            'contact_number.regex' => 'Enter an 11-digit mobile number beginning with 09.',
            'emergency_contact_number.regex' => 'Enter an 11-digit mobile number beginning with 09.',
            'student_number.regex' => 'The student number must contain 10 digits and begin with 20.',
            'student_number.unique' => 'A student is already registered with this student number.',
            'email.unique' => 'An account or registration already uses this email address.',
            'formal_photo.required' => 'Upload a formal picture with a white background.',
            'privacy_consent.accepted' => 'You must confirm that the information is correct.',
        ];
    }
}
