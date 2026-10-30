<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampusController extends Controller
{
    public function store(Request $request, Institution $institution): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('campuses')->where('institution_id', $institution->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_main' => ['nullable', 'boolean'],
        ]);
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = true;

        if ($validated['is_main'] ?? false) {
            $institution->campuses()->update(['is_main' => false]);
        }
        $institution->campuses()->create($validated);

        return back()->with('status', 'Campus added to '.$institution->name.'.');
    }

    public function update(Request $request, Institution $institution, Campus $campus): RedirectResponse
    {
        abort_unless($campus->institution_id === $institution->id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('campuses')->where('institution_id', $institution->id)->ignore($campus->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_main' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($validated['is_main']) {
            $institution->campuses()->whereKeyNot($campus->id)->update(['is_main' => false]);
        }
        $campus->update($validated + ['code' => strtoupper($validated['code'])]);

        return back()->with('status', 'Campus updated.');
    }
}
