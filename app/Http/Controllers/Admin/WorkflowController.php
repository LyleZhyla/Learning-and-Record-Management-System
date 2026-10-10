<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $prefix = $this->prefix($request);
        $workflows = WorkflowDefinition::with('updater')->orderBy('id')->get();

        return view('admin.workflows.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'workflows' => $workflows,
        ]);
    }

    public function update(Request $request, WorkflowDefinition $workflow): RedirectResponse
    {
        $definition = config('workflows.'.$workflow->key);
        abort_unless($definition, 404);

        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array'],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.mode' => ['required', Rule::in(['manual', 'automatic'])],
            'steps.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $allowedKeys = array_keys($definition['steps']);
        if (array_diff(array_keys($validated['steps']), $allowedKeys) !== []) {
            throw ValidationException::withMessages(['steps' => 'The workflow contains an unsupported step.']);
        }

        $steps = collect($allowedKeys)->map(function (string $key) use ($validated, $definition): array {
            $input = $validated['steps'][$key] ?? [];
            $fallback = $definition['steps'][$key];

            return [
                'key' => $key,
                'enabled' => array_key_exists('enabled', $input) ? (bool) $input['enabled'] : (bool) $fallback['enabled'],
                'mode' => $input['mode'] ?? $fallback['mode'],
                'sort_order' => (int) ($input['sort_order'] ?? 999),
            ];
        })->sortBy('sort_order')->values()->all();

        $workflow->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
            'steps' => $steps,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', $workflow->name.' workflow rules were updated.');
    }

    private function prefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
