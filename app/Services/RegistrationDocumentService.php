<?php

namespace App\Services;

use App\Models\ReviewCategory;
use App\Models\StudentRegistration;
use Illuminate\Support\Facades\Storage;

class RegistrationDocumentService
{
    /** @return array<string, array<string, mixed>> */
    public function checklist(StudentRegistration $registration): array
    {
        return [
            'cor' => $this->inspect(
                'Certificate of Registration (COR)',
                $registration->cor_path,
                $registration->cor_original_name,
                $registration->cor_review_status,
                ['pdf', 'jpg', 'jpeg', 'png'],
                5 * 1024 * 1024,
            ),
            'formal_photo' => $this->inspect(
                'Formal photo',
                $registration->formal_photo_path,
                $registration->formal_photo_original_name,
                $registration->formal_photo_review_status,
                ['jpg', 'jpeg', 'png'],
                3 * 1024 * 1024,
            ),
        ];
    }

    public function isComplete(StudentRegistration $registration): bool
    {
        return collect($this->checklist($registration))->every(fn (array $document): bool => $document['complete']);
    }

    /** @param array<int, string> $allowedExtensions */
    private function inspect(string $label, ?string $path, ?string $originalName, ?string $reviewStatus, array $allowedExtensions, int $maximumBytes): array
    {
        $disk = Storage::disk('local');
        $exists = filled($path) && $disk->exists($path);
        $name = $originalName ?: ($path ? basename($path) : null);
        $extension = strtolower(pathinfo((string) ($name ?: $path), PATHINFO_EXTENSION));
        $size = $exists ? $disk->size($path) : null;
        $validType = $exists && in_array($extension, $allowedExtensions, true);
        $validSize = $exists && $size !== null && $size > 0 && $size <= $maximumBytes;

        return [
            'label' => $label,
            'path' => $path,
            'name' => $name,
            'extension' => $extension,
            'exists' => $exists,
            'size' => $size,
            'size_label' => $size === null ? '—' : number_format($size / 1024, 1).' KB',
            'valid_type' => $validType,
            'valid_size' => $validSize,
            'complete' => $exists && $validType && $validSize,
            'review_status' => $reviewStatus ?: ReviewCategory::defaultSlug('registration_document', 'pending', 'pending'),
            'allowed_extensions' => $allowedExtensions,
            'maximum_size_label' => number_format($maximumBytes / 1024 / 1024, 0).' MB',
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png'], true),
        ];
    }
}
