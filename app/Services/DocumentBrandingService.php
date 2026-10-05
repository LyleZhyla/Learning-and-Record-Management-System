<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DocumentBrandingService
{
    public function headerPath(): string
    {
        return $this->assetPath('official-document-header.png');
    }

    public function footerPath(): string
    {
        return $this->assetPath('official-document-footer.png');
    }

    public function headerDataUri(): string
    {
        return $this->dataUri($this->headerPath());
    }

    public function footerDataUri(): string
    {
        return $this->dataUri($this->footerPath());
    }

    public function applyToWorksheet(Worksheet $sheet): void
    {
        $header = (new HeaderFooterDrawing)
            ->setName('Tarlac Agricultural University document header')
            ->setPath($this->headerPath())
            ->setWidth(760);
        $footer = (new HeaderFooterDrawing)
            ->setName('Tarlac Agricultural University document footer')
            ->setPath($this->footerPath())
            ->setWidth(760);

        $sheet->getHeaderFooter()
            ->setOddHeader('&C&G')
            ->setOddFooter('&C&G')
            ->addImage($header, HeaderFooter::IMAGE_HEADER_CENTER)
            ->addImage($footer, HeaderFooter::IMAGE_FOOTER_CENTER);

        $sheet->getPageMargins()
            ->setTop(1.2)
            ->setBottom(1.05)
            ->setHeader(0.1)
            ->setFooter(0.1);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    }

    private function assetPath(string $filename): string
    {
        $path = public_path('images/'.$filename);

        if (! is_file($path)) {
            throw new RuntimeException("The official document branding asset [{$filename}] is unavailable.");
        }

        return $path;
    }

    private function dataUri(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The official document branding asset could not be read.');
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
