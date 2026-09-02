<?php

namespace App\Http\Controllers;

use App\Services\QrCodeService;
use App\Services\StudentImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function create(Request $request): View
    {
        return view('student-import.create', $this->viewData($request));
    }

    public function store(Request $request, StudentImportService $importer, QrCodeService $qrCode): StreamedResponse|Response
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv'],
            'credential_delivery' => ['nullable', Rule::in(['download', 'view'])],
        ]);

        $result = $importer->import($request->file('file'));

        return ($validated['credential_delivery'] ?? 'download') === 'view'
            ? $this->credentialsView($request, $result, $qrCode)
            : $this->credentialsDownload($result, $qrCode);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Import');
        $sheet->fromArray([StudentImportService::HEADERS], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A1:B1')->getAlignment()->setWrapText(true);

        foreach (['A' => 32, 'B' => 38] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Column', 'Requirement', 'Example'],
            ['name', 'Required; maximum 100 characters', 'Juan Dela Cruz'],
            ['email', 'Required; must be unique', 'juan@example.edu.ph'],
        ], null, 'A1');
        $instructions->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(70);
        $instructions->getColumnDimension('C')->setWidth(28);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'student-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{
     *     students: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, qr_payload: string}>
     * }  $result
     */
    private function credentialsDownload(array $result, QrCodeService $qrCode): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Generated Credentials');
        $sheet->mergeCells('A1:D1')->setCellValue('A1', 'Imported Student Credentials and Attendance QR Codes');
        $sheet->mergeCells('A2:D2')->setCellValue('A2', "{$result['students']} account(s) imported. Keep this file secure.");
        $sheet->fromArray([['Full name', 'Email', 'Temporary password', 'Attendance QR']], null, 'A4');

        foreach ($result['credentials'] as $index => $credential) {
            $row = $index + 5;
            $sheet->fromArray([[$credential['name'], $credential['email'], $credential['temporary_password']]], null, 'A'.$row);
            $image = imagecreatefromstring($qrCode->generatePng($credential['qr_payload'], 180));

            if ($image !== false) {
                (new MemoryDrawing)
                    ->setName($credential['name'].' attendance QR')
                    ->setDescription('Permanent attendance QR for '.$credential['name'])
                    ->setImageResource($image)
                    ->setRenderingFunction(MemoryDrawing::RENDERING_PNG)
                    ->setMimeType(MemoryDrawing::MIMETYPE_PNG)
                    ->setHeight(82)
                    ->setCoordinates('D'.$row)
                    ->setOffsetX(8)
                    ->setOffsetY(5)
                    ->setWorksheet($sheet);
                $sheet->getRowDimension($row)->setRowHeight(70);
            }
        }

        $lastRow = count($result['credentials']) + 4;
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:D{$lastRow}");
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A2:D2')->getFont()->setItalic(true)->getColor()->setARGB('FF4D5D73');
        $sheet->getStyle('A4:D4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2468CA');
        $sheet->getStyle("C5:C{$lastRow}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("A5:C{$lastRow}")->getAlignment()->setVertical('center');

        foreach (['A' => 28, 'B' => 34, 'C' => 25, 'D' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'student-temporary-credentials-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Imported-Students' => (string) $result['students'],
        ]);
    }

    /**
     * @param  array{
     *     students: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, qr_payload: string}>
     * }  $result
     */
    private function credentialsView(Request $request, array $result, QrCodeService $qrCode): Response
    {
        return response()->view('student-import.credentials', $this->viewData($request) + [
            'studentCount' => $result['students'],
            'credentials' => collect($result['credentials'])->map(fn (array $credential) => [
                'name' => $credential['name'],
                'email' => $credential['email'],
                'temporary_password' => $credential['temporary_password'],
                'qr_data_uri' => 'data:image/png;base64,'.base64_encode($qrCode->generatePng($credential['qr_payload'], 220)),
            ])->all(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /** @return array{layout: string, routePrefix: string, backRoute: string} */
    private function viewData(Request $request): array
    {
        $prefix = $this->routePrefix($request);

        return [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'backRoute' => $prefix.'.students.index',
        ];
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
