<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $sheet->assessment->title }} · Answer Sheet</title>
<style>body{margin:0;background:#dfe4ea;font-family:Arial,sans-serif}.toolbar{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#10264b;color:white}.toolbar p{margin:0;font-size:13px}.toolbar button{padding:9px 17px;border:0;border-radius:7px;background:white;color:#163b69;font-weight:700;cursor:pointer}.sheet{width:min(210mm,100%);margin:20px auto;background:white;box-shadow:0 10px 35px #0002}.sheet svg{display:block;width:100%;height:auto}@media print{body{background:white}.toolbar{display:none}.sheet{width:210mm;margin:0;box-shadow:none}@page{size:A4;margin:0}}</style></head>
<body><div class="toolbar"><p>Print at 100% scale on A4 paper. Do not crop or fit.</p><button onclick="window.print()">Print answer sheet</button></div>
<main class="sheet"><svg viewBox="0 0 1000 1414" xmlns="http://www.w3.org/2000/svg" aria-label="SNAPIE standardized answer sheet">
    <rect width="1000" height="1414" fill="white"/>
    <rect x="52" y="52" width="36" height="36" fill="#000"/><rect x="912" y="52" width="36" height="36" fill="#000"/><rect x="52" y="1326" width="36" height="36" fill="#000"/><rect x="912" y="1326" width="36" height="36" fill="#000"/>
    <text x="500" y="78" text-anchor="middle" font-size="31" font-weight="700" fill="#10264b">SNAPIE ANSWER SHEET</text>
    <text x="500" y="112" text-anchor="middle" font-size="15" fill="#4b5e78">{{ $sheet->assessment->title }} · {{ $sheet->assessment->section->code }} · Sheet #{{ $sheet->id }}</text>
    <text x="95" y="158" font-size="16" font-weight="700">STUDENT NAME:</text><line x1="245" y1="160" x2="900" y2="160" stroke="#111" stroke-width="1"/>
    <text x="95" y="198" font-size="16" font-weight="700">STUDENT ID:</text><line x1="225" y1="200" x2="520" y2="200" stroke="#111" stroke-width="1"/><text x="590" y="198" font-size="13" fill="#555">Fill one bubble per item completely.</text>
    @for($i = 0; $i < $sheet->item_count; $i++)
        @php($y = 245 + ($i * 34))
        <text x="245" y="{{ $y + 6 }}" text-anchor="end" font-size="16" font-weight="700">{{ $i + 1 }}</text>
        @for($choice = 0; $choice < $sheet->choice_count; $choice++)
            @php($x = 330 + ($choice * 100))
            <circle cx="{{ $x }}" cy="{{ $y }}" r="17" fill="white" stroke="#111" stroke-width="2"/>
            <text x="{{ $x }}" y="{{ $y + 6 }}" text-anchor="middle" font-size="15" font-weight="700">{{ chr(65 + $choice) }}</text>
        @endfor
    @endfor
    <text x="500" y="1380" text-anchor="middle" font-size="12" fill="#657287">SNAPIE standardized optical answer sheet · Keep paper flat and all corner markers visible when scanning.</text>
</svg></main></body></html>
