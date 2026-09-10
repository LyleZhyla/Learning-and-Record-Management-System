<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireImportedStudentDocuments
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isStudent() || ! $user->must_upload_student_documents) {
            return $next($request);
        }

        if ($request->routeIs('student.required-documents.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('student.required-documents.create')->with(
            'warning',
            'Upload your COR and formal photo to open your student portal.'
        );
    }
}
