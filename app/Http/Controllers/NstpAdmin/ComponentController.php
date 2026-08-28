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
            ->orderByRaw("FIELD(code, 'CWTS', 'LTS', 'ROTC')")
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
        ]);
    }

    public function update(Request $request, NstpComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_section_capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ]);

        $component->update($validated);

        return redirect()->route($this->routePrefix($request).'.components.index')
            ->with('status', "{$component->code} configuration updated successfully.");
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
