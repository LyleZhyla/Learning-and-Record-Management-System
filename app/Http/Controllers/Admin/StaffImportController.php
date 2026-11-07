<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Services\DocumentBrandingService;
use App\Services\StaffImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffImportController extends Controller
{
    public function __construct(private readonly DocumentBrandingService $branding) {}

    public function create(): View
    {
        return view('admin.staff-import.create', [
            'components' => NstpComponent::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, StaffImportService $importer): StreamedResponse|Response
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! in_array(strtolower($value->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
                    $fail('Use a valid .xlsx, .xls, or .csv staff import file.');
                }
            }],
            'credential_delivery' => ['nullable', Rule::in(['download', 'view'])],
        ]);

        $result = $importer->import($request->file('file'));

        return ($validated['credential_delivery'] ?? 'download') === 'view'
            ? $this->credentialsView($result)
            : $this->credentialsDownload($result);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff Import');
        $sheet->fromArray([StaffImportService::HEADERS], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count(StaffImportService::HEADERS));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('E2:E1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (['A' => 28, 'B' => 36, 'C' => 20, 'D' => 20, 'E' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Column', 'Requirement', 'Example'],
            ['name', 'Required; full account holder name', 'Juan Dela Cruz'],
            ['email', 'Required; valid and unique login email', 'juan@example.edu.ph'],
            ['role', 'Required; facilitator, coordinator, or nstp_admin', 'facilitator'],
            ['component_code', 'Required for facilitator/coordinator; must match an active component; blank for NSTP Admin', 'CWTS'],
            ['contact_number', 'Required only for facilitator; 11 digits beginning with 09', '09171234567'],
        ], null, 'A1');
        $instructions->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $instructions->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(82);
        $instructions->getColumnDimension('C')->setWidth(36);

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $this->branding->applyToWorksheet($worksheet);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'staff-account-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param array{accounts: int, credentials: array<int, array<string, string>>} $result */
    private function credentialsDownload(array $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Generated Credentials');
        $sheet->mergeCells('A1:D1')->setCellValue('A1', 'Imported Staff Account Credentials');
        $sheet->mergeCells('A2:D2')->setCellValue('A2', "{$result['accounts']} account(s) imported. No email was sent automatically; keep this file secure.");
        $sheet->fromArray([['Full name', 'Email', 'Role', 'Temporary password']], null, 'A4');
        foreach ($result['credentials'] as $index => $credential) {
            $sheet->fromArray([[$credential['name'], $credential['email'], $credential['role_label'], $credential['temporary_password']]], null, 'A'.($index + 5));
        }
        $lastRow = count($result['credentials']) + 4;
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:D{$lastRow}");
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF174D84');
        $sheet->getStyle('A4:D4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2468CA');
        foreach (['A' => 28, 'B' => 36, 'C' => 22, 'D' => 25] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $this->branding->applyToWorksheet($sheet);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'staff-temporary-credentials-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Imported-Staff-Accounts' => (string) $result['accounts'],
        ]);
    }

    /** @param array{accounts: int, credentials: array<int, array<string, string>>} $result */
    private function credentialsView(array $result): Response
    {
        return response()->view('admin.staff-import.credentials', [
            'accountCount' => $result['accounts'],
            'credentials' => $result['credentials'],
        ])->withHeaders(['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }
}
