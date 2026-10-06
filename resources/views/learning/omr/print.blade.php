<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $sheet->assessment->title }} · Answer Sheet</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#dfe4ea;font-family:Arial,sans-serif}.toolbar{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:12px 20px;background:#10264b;color:white}.toolbar p{margin:0;font-size:13px}.toolbar-actions{display:flex;gap:9px}.toolbar a,.toolbar button{display:inline-flex;min-height:38px;align-items:center;border:0;border-radius:7px;padding:0 17px;background:white;color:#163b69;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.sheet{width:min(190mm,calc(100% - 32px));margin:20px auto}.sheet img{display:block;width:100%;height:auto;background:white;box-shadow:0 10px 35px #0002}@media print{body{background:white}.toolbar{display:none}.sheet{width:190mm;margin:0}.sheet img{box-shadow:none}@page{size:A4;margin:10mm}}
    </style>
</head>
<body>
    <div class="toolbar"><p>This compact image grows only with the number of items. Print at 100% scale and keep all four markers visible.</p><div class="toolbar-actions"><a href="{{ route(request()->routeIs('coordinator.*') ? 'coordinator.omr.image' : 'facilitator.omr.image', [$sheet, 'download' => 1]) }}">Download image</a><button onclick="window.print()">Print answer sheet</button></div></div>
    <main class="sheet"><img src="{{ route(request()->routeIs('coordinator.*') ? 'coordinator.omr.image' : 'facilitator.omr.image', $sheet) }}" alt="SNAPIE answer sheet for {{ $sheet->assessment->title }}"></main>
</body>
</html>
