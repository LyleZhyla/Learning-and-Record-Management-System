<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComponentController extends Controller
{
    public function index(): View
    {
        $components = NstpComponent::withCount(['sections', 'enrollments'])
            ->orderByRaw("FIELD(code, 'CWTS', 'LTS', 'ROTC')")
            ->get();

        return view('nstp_admin.components.index', compact('components'));
    }

    public function edit(NstpComponent $component): View
    {
        $component->loadCount(['sections', 'enrollments']);

        return view('nstp_admin.components.edit', compact('component'));
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

        return redirect()->route('nstp_admin.components.index')
            ->with('status', "{$component->code} configuration updated successfully.");
    }
}
