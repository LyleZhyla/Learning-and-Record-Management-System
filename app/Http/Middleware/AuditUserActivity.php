<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditUserActivity
{
    public function __construct(private AuditLogService $auditLogs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $actorBeforeRequest = $request->user();

        try {
            $response = $next($request);
            $this->record($request, $actorBeforeRequest ?? $request->user(), $response->getStatusCode(), $startedAt);

            return $response;
        } catch (Throwable $exception) {
            $statusCode = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                default => 500,
            };
            $this->record($request, $actorBeforeRequest ?? $request->user(), $statusCode, $startedAt);
            throw $exception;
        }
    }

    private function record(Request $request, mixed $actor, int $statusCode, int $startedAt): void
    {
        if (! $actor || $request->routeIs('profile.photo', 'admin.students.qr', 'nstp_admin.students.qr') || $request->attributes->get('inactivity_timeout_triggered')) {
            return;
        }

        $durationMs = max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
        $this->auditLogs->record($request, $actor, $statusCode, $durationMs);
    }
}
