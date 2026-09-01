<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComponentController extends Controller
{
    public function index(Request $request): View
    {
        $components = NstpComponent::withCount(['sections', 'enrollments'])
            ->orderByRaw("CASE code WHEN 'CWTS' THEN 1 WHEN 'LTS' THEN 2 WHEN 'ROTC' THEN 3 ELSE 4 END")
            ->get();

        return view('nstp_admin.components.index', [
            'components' => $components,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function edit(Request $request, NstpComponent $component): View
    {
        $component->loadCount(['sections', 'enrollments']);

        return view('nstp_admin.components.edit', [
            'component' => $component,
            'routePrefix' => $this->routePrefix($request),
            'returnTo' => $request->string('return_to')->toString() === 'sectioning' ? 'sectioning' : 'components',
        ]);
    }

    public function update(Request $request, NstpComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_section_capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['required', 'boolean'],
            'return_to' => ['nullable', 'in:components,sectioning'],
        ]);

        $returnTo = $validated['return_to'] ?? 'components';
        unset($validated['return_to']);
        $component->update($validated);

        return redirect()->route($this->routePrefix($request).($returnTo === 'sectioning' ? '.sections.index' : '.components.index'))
            ->with('status', "{$component->code} configuration updated successfully.");
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
