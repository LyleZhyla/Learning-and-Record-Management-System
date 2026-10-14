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
        $navy = imagecolorallocate($image, 23, 61, 109);
        $blue = imagecolorallocate($image, 40, 120, 206);
        $green = imagecolorallocate($image, 39, 167, 139);
        $ink = imagecolorallocate($image, 22, 43, 71);
        $muted = imagecolorallocate($image, 101, 118, 139);
        $line = imagecolorallocate($image, 225, 232, 240);
        $photoBlue = imagecolorallocate($image, 31, 93, 158);

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, 570, 12, $navy);
        imagefilledrectangle($image, 570, 0, 850, 12, $blue);
        imagefilledrectangle($image, 850, 0, self::WIDTH, 12, $green);
        imageline($image, 0, 133, self::WIDTH, 133, $line);

        $branding = SystemSetting::branding();
        $this->drawContainedImage($image, $this->brandingLogoPath($branding, 'organization'), 29, 28, 82, 82);
        $this->drawContainedImage($image, $this->brandingLogoPath($branding, 'program'), 90, 28, 82, 82);

        $this->drawText($image, $branding['organization_short_name'].' '.$branding['program_short_name'], 26, 182, 68, $navy, true);
        $this->drawText($image, $branding['platform_name'], 12, 182, 94, $muted);
        imagefilledrectangle($image, 829, 48, 978, 91, $navy);
        $this->drawCenteredText($image, 'STUDENT ID', 15, 829, 978, 76, $white, true);

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

        $this->drawText($image, 'FULL NAME', 11, 255, 177, $muted, true);
        $this->drawFittedText($image, mb_strtoupper($displayName), 34, 22, 255, 222, 485, $navy, true);

        $this->drawField($image, 'STUDENT NUMBER', $details?->student_number ?: 'Not provided', 255, 269, 220, $line, $muted, $ink);
        $this->drawField($image, 'NSTP COMPONENT', $enrollment?->component?->code ?: 'Not assigned', 500, 269, 220, $line, $muted, $ink);
        $this->drawField($image, 'COURSE', $details?->course ?: 'Not provided', 255, 346, 465, $line, $muted, $ink);
        $emergencyContact = $details?->emergency_contact_name ?: 'Not provided';
        if ($details?->emergency_relationship) {
            $emergencyContact .= ' · '.$details->emergency_relationship;
        }
        $this->drawField($image, 'EMERGENCY CONTACT', $emergencyContact, 255, 423, 465, $line, $muted, $ink);
        $this->drawText($image, $details?->emergency_contact_number ?: 'Contact number not provided', 12, 255, 512, $muted);

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
        $this->drawText($image, 'Valid while enrolled in '.$branding['organization_short_name'].' '.$branding['program_short_name'], 12, 31, 609, $white);
        $this->drawText($image, $enrollment?->academic_year ?: now()->format('Y'), 15, 883, 609, $white, true);

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
