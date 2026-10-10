<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceRolePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if (! $user || ! $routeName) {
            return $next($request);
        }

        if ($routeName === 'logout') {
            return $next($request);
        }

        abort_unless($user->isActive(), 403, 'This account is inactive.');
        abort_if($user->accessRole && ! $user->accessRole->is_active, 403, 'This account role is inactive.');

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach (config('role_permissions.route_map', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                abort_unless($user->hasPermission($permission), 403, 'Your assigned role does not have permission to access this feature.');
                break;
            }
        }

        return $next($request);
    }
}
