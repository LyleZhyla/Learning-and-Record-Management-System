<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetDownloadService
{
    public function __construct(private ReportSpreadsheetService $spreadsheets) {}

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function download(string $title, array $headers, iterable $rows, string $scope = 'All records'): StreamedResponse
    {
        $spreadsheet = $this->spreadsheets->create([
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now(),
        ], $scope);
        $filename = Str::slug($title).'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
