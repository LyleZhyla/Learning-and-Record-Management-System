<svg viewBox="0 0 1000 {{ $sheet->answerImageHeight() }}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="SNAPIE standardized answer sheet">
    <rect width="1000" height="{{ $sheet->answerImageHeight() }}" fill="white"/>
    <rect x="52" y="52" width="36" height="36" fill="#000"/>
    <rect x="912" y="52" width="36" height="36" fill="#000"/>
    <rect x="52" y="{{ $sheet->answerImageBottomMarkerY() - 18 }}" width="36" height="36" fill="#000"/>
    <rect x="912" y="{{ $sheet->answerImageBottomMarkerY() - 18 }}" width="36" height="36" fill="#000"/>
    <text x="500" y="78" text-anchor="middle" font-family="Arial, sans-serif" font-size="31" font-weight="700" fill="#10264b">SNAPIE ANSWER SHEET</text>
    <text x="500" y="112" text-anchor="middle" font-family="Arial, sans-serif" font-size="15" fill="#4b5e78">{{ $sheet->assessment->title }} · {{ $sheet->assessment->section->code }} · Sheet #{{ $sheet->id }}</text>
    <text x="95" y="158" font-family="Arial, sans-serif" font-size="16" font-weight="700">STUDENT NAME:</text><line x1="245" y1="160" x2="900" y2="160" stroke="#111" stroke-width="1"/>
    <text x="95" y="198" font-family="Arial, sans-serif" font-size="16" font-weight="700">STUDENT ID:</text><line x1="225" y1="200" x2="520" y2="200" stroke="#111" stroke-width="1"/><text x="590" y="198" font-family="Arial, sans-serif" font-size="13" fill="#555">Fill one bubble per item completely.</text>
    @for($i = 0; $i < $sheet->item_count; $i++)
        @php($y = 245 + ($i * 34))
        <text x="245" y="{{ $y + 6 }}" text-anchor="end" font-family="Arial, sans-serif" font-size="16" font-weight="700">{{ $i + 1 }}</text>
        @for($choice = 0; $choice < $sheet->choice_count; $choice++)
            @php($x = 330 + ($choice * 100))
            <circle cx="{{ $x }}" cy="{{ $y }}" r="17" fill="white" stroke="#111" stroke-width="2"/>
            <text x="{{ $x }}" y="{{ $y + 6 }}" text-anchor="middle" font-family="Arial, sans-serif" font-size="15" font-weight="700">{{ chr(65 + $choice) }}</text>
        @endfor
    @endfor
    <text x="500" y="{{ $sheet->answerImageBottomMarkerY() + 45 }}" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#657287">Keep the complete image and all four corner markers visible when scanning.</text>
</svg>
