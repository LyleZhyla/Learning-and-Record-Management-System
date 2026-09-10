<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportedStudentDocumentsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class RequiredDocumentController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_upload_student_documents) {
            return redirect()->route('student.dashboard');
        }

        abort_unless($request->user()->studentProfile()->exists(), 409, 'Complete student profile information is required before uploading documents.');

        return view('student.required-documents', ['user' => $request->user()]);
    }

    public function store(StoreImportedStudentDocumentsRequest $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 409, 'Complete student profile information is required before uploading documents.');

        $corPath = null;
        $photoPath = null;

        try {
            $corPath = $request->file('cor')->store('student-imports/cor', 'local');
            $photoPath = $request->file('formal_photo')->store('student-imports/formal-photos', 'local');

            DB::transaction(function () use ($request, $profile, $corPath, $photoPath): void {
                $profile->forceFill([
                    'cor_path' => $corPath,
                    'formal_photo_path' => $photoPath,
                ])->save();

                $request->user()->forceFill([
                    'must_upload_student_documents' => false,
                ])->save();
            });
        } catch (Throwable $exception) {
            if ($corPath) {
                Storage::disk('local')->delete($corPath);
            }
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            throw $exception;
        }

        return redirect()->route('student.dashboard')->with(
            'status',
            'Your COR and formal photo were uploaded successfully. Your student portal is now available.'
        );
    }
}
