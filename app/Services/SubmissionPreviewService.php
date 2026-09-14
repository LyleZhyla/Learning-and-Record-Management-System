<?php

namespace App\Services;

use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Storage;

class SubmissionPreviewService
{
    public const MAX_PREVIEW_BYTES = 5 * 1024 * 1024;

    public function inspect(AssessmentSubmission $submission): array
    {
        if (blank($submission->file_path) || ! Storage::exists($submission->file_path)) {
            return ['exists' => false, 'size' => 0, 'size_label' => null, 'too_large' => false, 'preview_type' => null, 'mime' => null];
        }

        $size = Storage::size($submission->file_path);
        $mime = Storage::mimeType($submission->file_path) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($submission->original_filename ?: $submission->file_path, PATHINFO_EXTENSION));
        $previewType = match (true) {
            str_starts_with($mime, 'image/') && in_array($extension, ['jpg', 'jpeg', 'png'], true) => 'image',
            $mime === 'application/pdf' || $extension === 'pdf' => 'pdf',
            str_starts_with($mime, 'text/') || $extension === 'txt' => 'text',
            default => 'download',
        };

        return [
            'exists' => true,
            'size' => $size,
            'size_label' => $this->sizeLabel($size),
            'too_large' => $size > self::MAX_PREVIEW_BYTES,
            'preview_type' => $previewType,
            'mime' => $mime,
        ];
    }

    private function sizeLabel(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 2).' MB'
            : number_format(max(1, $bytes / 1024), 1).' KB';
    }
}
