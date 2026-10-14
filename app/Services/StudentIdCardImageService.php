<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentIdCardImageService
{
    public const WIDTH = 1011;

    public const HEIGHT = 638;

    /** @param array<string, mixed> $data */
    public function render(array $data, string $qrPng, string $format): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $design = SystemSetting::studentIdDesign();
        $navy = $this->allocateHexColor($image, $design['primary_color']);
        $blue = $this->allocateHexColor($image, $design['secondary_color']);
        $green = $this->allocateHexColor($image, $design['accent_color']);
        $ink = imagecolorallocate($image, 22, 43, 71);
        $muted = imagecolorallocate($image, 101, 118, 139);
        $line = imagecolorallocate($image, 225, 232, 240);
        $photoBlue = $blue;

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, 570, 12, $navy);
        imagefilledrectangle($image, 570, 0, 850, 12, $blue);
        imagefilledrectangle($image, 850, 0, self::WIDTH, 12, $green);
        imageline($image, 0, 133, self::WIDTH, 133, $line);

        $branding = SystemSetting::branding();
        $this->drawContainedImage($image, $this->brandingLogoPath($branding, 'organization'), 29, 28, 82, 82);
        $this->drawContainedImage($image, $this->brandingLogoPath($branding, 'program'), 90, 28, 82, 82);

        $this->drawFittedText($image, $branding['organization_name'], 24, 16, 182, 66, 620, $navy, true);
        $this->drawFittedText($image, $branding['program_name'], 13, 10, 182, 94, 620, $muted);
        imagefilledrectangle($image, 829, 48, 978, 91, $navy);
        $this->drawCenteredFittedText($image, $design['title'], 15, 10, 829, 978, 76, $white, true);

        $student = $data['student'];
        $details = $data['details'];
        $enrollment = $data['enrollment'];
        $displayName = $data['displayName'];

        $photoPath = filled($student->profile_photo_path) && Storage::disk('local')->exists($student->profile_photo_path)
            ? Storage::disk('local')->path($student->profile_photo_path)
            : null;
        if ($photoPath) {
            $this->drawCroppedImage($image, $photoPath, 34, 166, 190, 274);
        } else {
            imagefilledrectangle($image, 34, 166, 224, 440, $photoBlue);
            $this->drawCenteredText($image, mb_strtoupper(mb_substr($displayName, 0, 1)), 72, 34, 224, 330, $white, true);
        }
        imagefilledrectangle($image, 34, 410, 224, 440, $navy);
        $this->drawCenteredText($image, 'OFFICIAL STUDENT', 9, 34, 224, 431, $white, true);

        $this->drawText($image, 'OFFICIAL STUDENT RECORD', 11, 255, 177, $muted, true);
        $this->drawFittedText($image, mb_strtoupper($displayName), 34, 22, 255, 222, 485, $navy, true);

        $this->drawText($image, 'STUDENT NUMBER', 10, 255, 254, $muted, true);
        $this->drawFittedText($image, $details?->student_number ?: 'Not provided', 22, 14, 255, 283, 465, $navy, true);
        if ($design['show_component']) {
            $this->drawField($image, 'NSTP COMPONENT', $enrollment?->component?->code ?: 'Not assigned', 255, 310, 220, $line, $muted, $ink);
        }
        $this->drawField($image, 'ACADEMIC YEAR', $enrollment?->academic_year ?: now()->format('Y'), 500, 310, 220, $line, $muted, $ink);
        if ($design['show_course']) {
            $this->drawField($image, 'COURSE', $details?->course ?: 'Not provided', 255, 375, 465, $line, $muted, $ink);
        }
        $emergencyContact = $details?->emergency_contact_name ?: 'Not provided';
        if ($details?->emergency_relationship) {
            $emergencyContact .= ' · '.$details->emergency_relationship;
        }
        if ($design['show_emergency_contact']) {
            $this->drawField($image, 'EMERGENCY CONTACT', $emergencyContact, 255, 440, 465, $line, $muted, $ink);
            $this->drawText($image, $details?->emergency_contact_number ?: 'Contact number not provided', 12, 255, 529, $muted);
        }

        imagefilledrectangle($image, 759, 168, 977, 456, $white);
        imagerectangle($image, 759, 168, 977, 456, $line);
        $qrImage = imagecreatefromstring($qrPng);
        if ($qrImage !== false) {
            imagecopyresampled($image, $qrImage, 775, 181, 0, 0, 186, 186, imagesx($qrImage), imagesy($qrImage));
            imagedestroy($qrImage);
        }
        $this->drawCenteredText($image, 'SCAN FOR ATTENDANCE', 11, 759, 977, 395, $navy, true);
        $this->drawCenteredText($image, 'Permanent student QR', 10, 759, 977, 425, $muted);

        imagefilledrectangle($image, 0, 566, self::WIDTH, self::HEIGHT, $navy);
        $this->drawFittedText($image, $design['footer_text'], 12, 9, 31, 609, 690, $white);
        $this->drawFittedText($image, $branding['organization_short_name'].' · '.$branding['program_short_name'], 15, 10, 820, 609, 158, $white, true);

        ob_start();
        if ($format === 'png') {
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 92);
        }
        $contents = ob_get_clean();
        imagedestroy($image);

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to generate the student ID image.');
        }

        return $contents;
    }

    /** @param array<string, mixed> $branding */
    private function brandingLogoPath(array $branding, string $type): string
    {
        $key = $type === 'program' ? 'program_logo_path' : 'organization_logo_path';
        if (filled($branding[$key] ?? null) && Storage::disk('public')->exists($branding[$key])) {
            return Storage::disk('public')->path($branding[$key]);
        }

        return public_path($type === 'program' ? 'images/branding/nstp-logo.png' : 'images/branding/tau-logo.png');
    }

    private function drawContainedImage($canvas, string $path, int $x, int $y, int $width, int $height): void
    {
        $source = $this->loadImage($path);
        if (! $source) {
            return;
        }

        $scale = min($width / imagesx($source), $height / imagesy($source));
        $drawWidth = (int) round(imagesx($source) * $scale);
        $drawHeight = (int) round(imagesy($source) * $scale);
        imagecopyresampled($canvas, $source, $x + intdiv($width - $drawWidth, 2), $y + intdiv($height - $drawHeight, 2), 0, 0, $drawWidth, $drawHeight, imagesx($source), imagesy($source));
        imagedestroy($source);
    }

    private function drawCroppedImage($canvas, string $path, int $x, int $y, int $width, int $height): void
    {
        $source = $this->loadImage($path);
        if (! $source) {
            return;
        }

        $sourceRatio = imagesx($source) / imagesy($source);
        $targetRatio = $width / $height;
        if ($sourceRatio > $targetRatio) {
            $sourceHeight = imagesy($source);
            $sourceWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = intdiv(imagesx($source) - $sourceWidth, 2);
            $sourceY = 0;
        } else {
            $sourceWidth = imagesx($source);
            $sourceHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = intdiv(imagesy($source) - $sourceHeight, 2);
        }
        imagecopyresampled($canvas, $source, $x, $y, $sourceX, $sourceY, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);
    }

    private function loadImage(string $path)
    {
        if (! is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        return $contents === false ? false : @imagecreatefromstring($contents);
    }

    private function drawField($image, string $label, string $value, int $x, int $y, int $width, int $line, int $muted, int $ink): void
    {
        imageline($image, $x, $y, $x + $width, $y, $line);
        $this->drawText($image, $label, 10, $x, $y + 22, $muted, true);
        $this->drawFittedText($image, $value, 16, 11, $x, $y + 52, $width, $ink, true);
    }

    private function drawCenteredText($image, string $text, int $size, int $left, int $right, int $baseline, int $color, bool $bold = false): void
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);
        $width = $box[2] - $box[0];
        $this->drawText($image, $text, $size, $left + intdiv(($right - $left) - $width, 2), $baseline, $color, $bold);
    }

    private function drawCenteredFittedText($image, string $text, int $size, int $minimumSize, int $left, int $right, int $baseline, int $color, bool $bold = false): void
    {
        while ($size > $minimumSize) {
            $box = imagettfbbox($size, 0, $this->font($bold), $text);
            if (($box[2] - $box[0]) <= ($right - $left - 16)) {
                break;
            }
            $size--;
        }

        $this->drawCenteredText($image, $text, $size, $left, $right, $baseline, $color, $bold);
    }

    private function drawFittedText($image, string $text, int $size, int $minimumSize, int $x, int $baseline, int $maximumWidth, int $color, bool $bold = false): void
    {
        while ($size > $minimumSize) {
            $box = imagettfbbox($size, 0, $this->font($bold), $text);
            if (($box[2] - $box[0]) <= $maximumWidth) {
                break;
            }
            $size--;
        }

        $this->drawText($image, $text, $size, $x, $baseline, $color, $bold);
    }

    private function drawText($image, string $text, int $size, int $x, int $baseline, int $color, bool $bold = false): void
    {
        imagettftext($image, $size, 0, $x, $baseline, $color, $this->font($bold), $text);
    }

    private function allocateHexColor($image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private function font(bool $bold): string
    {
        $candidates = $bold
            ? ['C:\\Windows\\Fonts\\arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
            : ['C:\\Windows\\Fonts\\arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('A TrueType font is required to generate the student ID image.');
    }
}
