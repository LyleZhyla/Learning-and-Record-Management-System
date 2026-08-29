<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceInactivityTimeout
{
    private const SESSION_KEY = 'snapie_last_activity_at';

    public function __construct(private AuditLogService $auditLogs) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('profile.photo')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user) {
            $timeoutMinutes = SystemSetting::inactivityTimeoutMinutes();
            $lastActivity = (int) $request->session()->get(self::SESSION_KEY, 0);

            if ($lastActivity > 0 && now()->timestamp - $lastActivity >= $timeoutMinutes * 60) {
                $request->attributes->set('inactivity_timeout_triggered', true);
                $this->auditLogs->recordInactivityLogout($request, $user, $timeoutMinutes);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your session expired due to inactivity.'], 401);
                }

                return redirect()->route('login')->with('inactivity_timeout', "You were signed out after {$timeoutMinutes} minutes of inactivity.");
            }

            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }

        $response = $next($request);

        if (! $user && $request->user()) {
            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }

        return $response;
    }
}
