<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use App\Services\InstitutionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveInstitutionContext
{
    public function __construct(private InstitutionContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $institutionId = null;

        if ($user?->isSystemAdmin()) {
            $selected = (int) $request->session()->get('active_institution_id', 0);
            $institutionId = Institution::query()->whereKey($selected)->where('is_active', true)->value('id');
        } elseif ($user?->institution_id) {
            abort_unless(Institution::query()->whereKey($user->institution_id)->where('is_active', true)->exists(), 403, 'Your institution workspace is inactive.');
            $institutionId = (int) $user->institution_id;
        }

        $institutionId ??= Institution::query()->where('is_active', true)->orderBy('id')->value('id');
        $this->context->set($institutionId ? (int) $institutionId : null, $user?->isSystemAdmin() === true);

        if ($user?->isSystemAdmin() && $institutionId) {
            $request->session()->put('active_institution_id', $institutionId);
        }

        return $next($request);
    }
}
