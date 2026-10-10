<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:12px}h1,h2,p{margin:0 0 8px}.header{text-align:center;border-bottom:2px solid #1c6b4f;padding-bottom:14px;margin-bottom:20px}.meta,.amounts{width:100%;border-collapse:collapse;margin:16px 0}.meta td,.amounts th,.amounts td{border:1px solid #ccd5df;padding:8px}.amounts th{background:#eef5f1;text-align:left}.right{text-align:right}.total{font-weight:bold;background:#f4f8f6}.references{padding:12px;border:1px solid #ccd5df;background:#f8faf9}.footer{margin-top:40px;border-top:1px solid #ccd5df;padding-top:12px;color:#526071}
    </style>
</head>
<body>
<div class="header"><h1>Tarlac Agricultural University</h1><h2>National Service Training Program</h2><p>Honorarium Document Package for University Cashier</p></div>
<table class="meta"><tr><td><strong>NSTP reference</strong><br>{{ $honorarium->reference_number }}</td><td><strong>Document status</strong><br>{{ $honorarium->statusLabel() }}</td></tr><tr><td><strong>Facilitator</strong><br>{{ $honorarium->facilitator->name }}<br>{{ $honorarium->facilitator->facilitatorProfile?->employee_number }}</td><td><strong>Component</strong><br>{{ $honorarium->component->code }} — {{ $honorarium->component->name }}</td></tr><tr><td><strong>Academic term</strong><br>{{ $honorarium->academic_year }} · {{ $honorarium->semesterLabel() }}</td><td><strong>Service period</strong><br>{{ $honorarium->period_start->format('M d, Y') }} – {{ $honorarium->period_end->format('M d, Y') }}</td></tr></table>
<table class="amounts"><thead><tr><th>Description</th><th class="right">Amount</th></tr></thead><tbody><tr><td>Gross honorarium</td><td class="right">PHP {{ number_format((float)$honorarium->gross_amount, 2) }}</td></tr><tr><td>Deductions</td><td class="right">PHP {{ number_format((float)$honorarium->deductions, 2) }}</td></tr><tr class="total"><td>Net amount for cashier processing</td><td class="right">PHP {{ number_format((float)$honorarium->net_amount, 2) }}</td></tr></tbody></table>
<div class="references"><p><strong>Disbursement voucher number:</strong> {{ $honorarium->disbursement_voucher_number ?: 'Not provided' }}</p><p><strong>Obligation request number:</strong> {{ $honorarium->obligation_request_number ?: 'Not provided' }}</p><p><strong>Payroll/reference number:</strong> {{ $honorarium->payroll_reference ?: 'Not provided' }}</p><p><strong>Prepared by:</strong> {{ $honorarium->preparer?->name ?? 'NSTP Admin' }}{{ $honorarium->prepared_at ? ' · '.$honorarium->prepared_at->format('M d, Y g:i A') : '' }}</p>@if($honorarium->cashier_forwarded_at)<p><strong>Forwarded to University Cashier:</strong> {{ $honorarium->cashier_forwarded_at->format('M d, Y g:i A') }}</p>@endif</div>
<div class="footer">Generated {{ now()->format('M d, Y g:i A') }}. This is an NSTP document-preparation record for cashier processing. It is not an approval, proof of payment, payslip, or disbursement receipt. Final approval and fund release are handled by the University Cashier.</div>
</body>
</html>
