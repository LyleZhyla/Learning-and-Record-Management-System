<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileUploadUiTest extends TestCase
{
    public function test_every_file_input_uses_the_shared_picker_or_an_existing_custom_picker(): void
    {
        $approvedPickerMarkers = [
            'file-input',
            'upload-native-input',
            'data-profile-photo-input',
            'data-omr-upload',
            'data-required-upload',
            'data-assessment-file',
            'data-attachment',
            'data-student-import-file',
            'id="cor"',
            'id="formal-photo"',
        ];
        $fileInputCount = 0;

        foreach (File::allFiles(resource_path('views')) as $view) {
            if ($view->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/<input\b[^>]*\btype\s*=\s*["\']file["\'][^>]*>/is', File::get($view->getPathname()), $matches);
            foreach ($matches[0] as $input) {
                $fileInputCount++;
                $this->assertTrue(
                    collect($approvedPickerMarkers)->contains(fn (string $marker): bool => str_contains($input, $marker)),
                    $view->getRelativePathname().' contains an unstyled file input: '.$input
                );
            }
        }

        $this->assertGreaterThanOrEqual(20, $fileInputCount);
        $stylesheet = File::get(public_path('css/app.css'));
        $this->assertStringContainsString('.file-input::file-selector-button', $stylesheet);
        $this->assertStringContainsString('html[data-theme=dark] .file-input', $stylesheet);
    }
}
