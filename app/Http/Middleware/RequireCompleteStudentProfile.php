<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompleteStudentProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isStudent() || ! $this->guardsStudentExperience($request) || ! $user->mustCompleteStudentProfile()) {
            return $next($request);
        }

        if ($request->routeIs(
            'student.profile.*',
            'student.password.update',
            'locations.*',
            'profile.photo',
            'logout',
        )) {
            return $next($request);
        }

        return redirect()->route('student.profile.edit', ['edit' => 1])->with(
            'warning',
            'Complete all required personal, emergency contact, and academic details before using the student portal.'
        );
    }

    private function guardsStudentExperience(Request $request): bool
    {
        return $request->routeIs(
            'student.*',
            'notifications.*',
            'announcements.attachment.download',
            'ai-assistant.*',
        );
    }
}
