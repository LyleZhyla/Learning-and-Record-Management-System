<?php

namespace App\Http\Controllers;

use App\Services\StudentImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function create(Request $request): View
    {
        return view('student-import.create', $this->viewData($request));
    }

    public function store(Request $request, StudentImportService $importer): StreamedResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv']]);

        $result = $importer->import($request->file('file'));

        return $this->credentialsDownload($result);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Import');
        $sheet->fromArray([StudentImportService::HEADERS], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A1:G1')->getAlignment()->setWrapText(true);

        foreach (['A' => 28, 'B' => 32, 'C' => 14, 'D' => 18, 'E' => 18, 'F' => 16, 'G' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Column', 'Requirement', 'Example'],
            ['full_name', 'Required; maximum 100 characters', 'Juan Dela Cruz'],
            ['email', 'Required; must be unique', 'juan@example.edu.ph'],
            ['status', 'Optional; active or inactive. Defaults to active', 'active'],
            ['component_code', 'Optional; active component such as CWTS, ROTC, or LTS', 'CWTS'],
            ['academic_year', 'Required when component_code is provided; consecutive years', '2026-2027'],
            ['semester', 'Required when component_code is provided; first, second, or summer', 'first'],
            ['section_code', 'Optional; must match the component and term', 'CWTS-01'],
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
     *     enrollments: int,
     *     credentials: array<int, array{name: string, email: string, temporary_password: string, component: string, section: string}>
     * }  $result
     */
    private function credentialsDownload(array $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Generated Credentials');
        $sheet->mergeCells('A1:E1')->setCellValue('A1', 'Imported Student Temporary Credentials');
        $sheet->mergeCells('A2:E2')->setCellValue('A2', "{$result['students']} account(s) imported; {$result['enrollments']} enrollment(s) created. Keep this file secure.");
        $sheet->fromArray([['Full name', 'Email', 'Temporary password', 'Component', 'Section']], null, 'A4');

        foreach ($result['credentials'] as $index => $credential) {
            $sheet->fromArray([array_values($credential)], null, 'A'.($index + 5));
        }

        $lastRow = count($result['credentials']) + 4;
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:E{$lastRow}");
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A2:E2')->getFont()->setItalic(true)->getColor()->setARGB('FF4D5D73');
        $sheet->getStyle('A4:E4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:E4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2468CA');
        $sheet->getStyle("C5:C{$lastRow}")->getNumberFormat()->setFormatCode('@');

        foreach (['A' => 28, 'B' => 34, 'C' => 25, 'D' => 15, 'E' => 18] as $column => $width) {
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

    /** @return array{layout: string, routePrefix: string, backRoute: string} */
    private function viewData(Request $request): array
    {
        $prefix = $this->routePrefix($request);

        return [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'backRoute' => $prefix === 'admin' ? 'admin.users.index' : 'nstp_admin.accounts.index',
        ];
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
