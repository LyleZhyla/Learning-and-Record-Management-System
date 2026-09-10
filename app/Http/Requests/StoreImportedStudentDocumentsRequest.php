<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportedStudentDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true
            && $this->user()->must_upload_student_documents;
    }

    public function rules(): array
    {
        return [
            'cor' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'formal_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'cor.required' => 'Upload your Certificate of Registration before continuing.',
            'cor.mimes' => 'The COR must be a PDF, JPG, JPEG, or PNG file.',
            'formal_photo.required' => 'Upload a formal picture with a white background before continuing.',
            'formal_photo.image' => 'The formal photo must be a valid image.',
        ];
    }
}
