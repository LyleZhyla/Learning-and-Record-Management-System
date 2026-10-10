<?php

namespace App\Http\Controllers;

use App\Models\NstpStudentSerialNumber;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SerialNumberVerificationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:100'],
        ]);
        $searched = $request->filled('serial_number');
        $serialRecord = null;

        if ($searched) {
            $serialNumber = strtoupper(trim($validated['serial_number']));
            $serialRecord = NstpStudentSerialNumber::with([
                'student.studentProfile', 'release.component', 'enrollment.section',
            ])->whereRaw('UPPER(serial_number) = ?', [$serialNumber])->first();
        }

        return view('serial-number-verification', compact('serialRecord', 'searched'));
    }
}
