<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCoordinator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isCoordinator(), 403, 'Coordinator access is required.');

        return $next($request);
    }
}
