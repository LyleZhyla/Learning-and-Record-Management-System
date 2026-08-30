<?php

namespace App\Http\Controllers;

use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
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

    public function store(Request $request, StudentImportService $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv']]);

        $result = $importer->import($request->file('file'));
        $message = "{$result['students']} student account(s) imported successfully.";

        if ($result['enrollments'] > 0) {
            $message .= " {$result['enrollments']} NSTP enrollment(s) were also created.";
        }

        return redirect()->route($this->routePrefix($request).'.students.import.create')->with('status', $message);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Import');
        $sheet->fromArray([StudentImportService::HEADERS], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A1:H1')->getAlignment()->setWrapText(true);

        foreach (['A' => 28, 'B' => 32, 'C' => 24, 'D' => 14, 'E' => 18, 'F' => 18, 'G' => 16, 'H' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Column', 'Requirement', 'Example'],
            ['full_name', 'Required; maximum 100 characters', 'Juan Dela Cruz'],
            ['email', 'Required; must be unique', 'juan@example.edu.ph'],
            ['temporary_password', 'Required; 12+ characters with uppercase, lowercase, number, and symbol', 'Student!2026Pass'],
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
