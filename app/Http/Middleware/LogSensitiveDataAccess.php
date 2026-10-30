<?php

namespace App\Http\Middleware;

use App\Models\SensitiveFieldAccessLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogSensitiveDataAccess
{
    private const ROUTES = [
        'admin.registrations.show' => ['identity', 'contact', 'demographic', 'academic', 'emergency_contact'],
        'admin.registrations.documents.show' => ['identity_document'],
        'admin.registrations.documents.download' => ['identity_document'],
        'admin.students.index' => ['identity', 'contact', 'academic'],
        'admin.reports.export' => ['identity', 'academic', 'attendance', 'assessment'],
        'admin.reports.document' => ['identity', 'academic', 'attendance', 'assessment'],
        'admin.reports.pdf' => ['identity', 'academic', 'attendance', 'assessment'],
        'student.profile.show' => ['identity', 'contact', 'demographic', 'emergency_contact'],
        'student.profile.edit' => ['identity', 'contact', 'demographic', 'emergency_contact'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = $request->route()?->getName();
        $actor = $request->user();

        if ($actor && $response->getStatusCode() < 400 && isset(self::ROUTES[$routeName])) {
            $parameters = collect($request->route()?->parameters() ?? []);
            $resource = $parameters->first(fn (mixed $value): bool => $value instanceof Model);
            $subject = $resource instanceof User ? $resource : null;
            SensitiveFieldAccessLog::create([
                'actor_id' => $actor->id,
                'subject_user_id' => $subject?->id,
                'actor_name' => $actor->name,
                'actor_role' => $actor->role,
                'resource_type' => $resource ? class_basename($resource) : str($routeName)->beforeLast('.')->headline(),
                'resource_id' => $resource?->getRouteKey(),
                'field_categories' => self::ROUTES[$routeName],
                'purpose' => 'Authorized '.$routeName.' access',
                'route_name' => $routeName,
                'ip_address' => $request->ip(),
                'accessed_at' => now(),
            ]);
        }

        return $response;
    }
}
