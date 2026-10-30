<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Services\InstitutionContext;
use App\Services\InstitutionProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionController extends Controller
{
    public function index(InstitutionContext $context): View
    {
        return view('admin.institutions.index', [
            'institutions' => Institution::withCount(['campuses', 'users'])->orderBy('name')->get(),
            'activeInstitutionId' => $context->id(),
        ]);
    }

    public function create(): View
    {
        return view('admin.institutions.form', ['institution' => new Institution]);
    }

    public function store(Request $request, InstitutionProvisioner $provisioner, InstitutionContext $context): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['code'] = Str::upper($validated['code']);

        $institution = DB::transaction(function () use ($validated, $provisioner, $context): Institution {
            $institution = Institution::create($validated);
            $provisioner->provision($institution, $context->id());

            return $institution;
        });

        return redirect()->route('admin.institutions.edit', $institution)
            ->with('status', $institution->name.' was created with isolated roles, components, policies, workflows, notifications, and academic-term settings.');
    }

    public function edit(Institution $institution): View
    {
        $institution->load('campuses');

        return view('admin.institutions.form', compact('institution'));
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $validated = $this->validated($request, $institution);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['code'] = Str::upper($validated['code']);
        $institution->update($validated);

        return back()->with('status', 'Institution profile updated.');
    }

    public function switch(Request $request, Institution $institution): RedirectResponse
    {
        abort_unless($institution->is_active, 422, 'The selected institution is inactive.');
        $request->session()->put('active_institution_id', $institution->id);

        return redirect()->route('admin.dashboard')->with('status', 'Active institution changed to '.$institution->name.'.');
    }

    private function validated(Request $request, ?Institution $institution = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('institutions')->ignore($institution?->id)],
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('institutions')->ignore($institution?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
