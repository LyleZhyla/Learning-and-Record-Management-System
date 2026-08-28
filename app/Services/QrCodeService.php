<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    public function generateSvg(string $payload): string
    {
        return Builder::create()
            ->writer(new SvgWriter())
            ->data($payload)
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(360)
            ->margin(12)
            ->build()
            ->getString();
    }
}
