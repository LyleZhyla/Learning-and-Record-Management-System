<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    private const MODULES = [
        'registration' => ['label' => 'Registration Reviews', 'route' => 'admin.registrations.index', 'keys' => ['registration_review', 'account_creation']],
        'components' => ['label' => 'NSTP Components', 'route' => 'admin.components.index', 'keys' => ['component_selection', 'rotc_approval']],
        'documents' => ['label' => 'Documents & Forms', 'route' => 'admin.document-forms.index', 'keys' => ['document_verification']],
        'grades' => ['label' => 'Grades', 'route' => 'admin.grades.index', 'keys' => ['grading']],
        'sections' => ['label' => 'Sectioning', 'route' => 'admin.sections.index', 'keys' => ['sectioning']],
        'archives' => ['label' => 'Records Archive', 'route' => 'admin.archives.index', 'keys' => ['archiving']],
    ];

    public function index(Request $request): View
    {
        $prefix = $this->prefix($request);
        $module = self::MODULES[$request->string('module')->toString()] ?? null;
        $workflows = WorkflowDefinition::with('updater')
            ->when($module, fn ($query) => $query->whereIn('key', $module['keys']))
            ->orderBy('id')->get();

        return view('admin.workflows.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'workflows' => $workflows,
            'module' => $module,
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
            'steps.*.name' => ['nullable', 'string', 'max:120'],
            'steps.*.description' => ['nullable', 'string', 'max:1000'],
            'steps.*.assigned_role' => ['nullable', Rule::in(['any_admin', 'super_admin', 'nstp_admin'])],
            'steps.*.required' => ['nullable', 'boolean'],
        ]);

        $resolved = collect($workflow->resolvedSteps())->keyBy('key');
        $allowedKeys = $resolved->keys()->all();
        if (array_diff(array_keys($validated['steps']), $allowedKeys) !== []) {
            throw ValidationException::withMessages(['steps' => 'The workflow contains an unsupported step.']);
        }

        $steps = collect($allowedKeys)->map(function (string $key) use ($validated, $definition, $resolved): array {
            $input = $validated['steps'][$key] ?? [];
            $fallback = $resolved->get($key);

            if (($fallback['type'] ?? 'system_rule') === 'manual_checkpoint') {
                return [
                    'key' => $key,
                    'type' => 'manual_checkpoint',
                    'name' => $input['name'] ?? $fallback['name'],
                    'description' => $input['description'] ?? $fallback['description'],
                    'enabled' => array_key_exists('enabled', $input) ? (bool) $input['enabled'] : (bool) $fallback['enabled'],
                    'mode' => 'manual',
                    'sort_order' => (int) ($input['sort_order'] ?? $fallback['sort_order']),
                    'assigned_role' => $input['assigned_role'] ?? $fallback['assigned_role'],
                    'required' => array_key_exists('required', $input) ? (bool) $input['required'] : (bool) $fallback['required'],
                    'applies_from' => $fallback['applies_from'],
                ];
            }

            $systemFallback = $definition['steps'][$key];

            return [
                'key' => $key,
                'enabled' => array_key_exists('enabled', $input) ? (bool) $input['enabled'] : (bool) $systemFallback['enabled'],
                'mode' => $input['mode'] ?? $systemFallback['mode'],
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

    public function storeStep(Request $request, WorkflowDefinition $workflow): RedirectResponse
    {
        abort_unless($workflow->allowsCustomCheckpoints(), 422, 'This workflow does not support custom checkpoints.');

        $request->merge([
            'required' => $request->boolean('required'),
            'apply_to_existing' => $request->boolean('apply_to_existing'),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'assigned_role' => ['required', Rule::in(['any_admin', 'super_admin', 'nstp_admin'])],
            'required' => ['required', 'boolean'],
            'apply_to_existing' => ['required', 'boolean'],
        ]);

        $key = 'custom_'.Str::slug($validated['name'], '_').'_'.Str::lower(Str::random(6));
        $steps = collect($workflow->steps ?? []);
        $steps->push([
            'key' => $key,
            'type' => 'manual_checkpoint',
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'enabled' => true,
            'mode' => 'manual',
            'sort_order' => ((int) collect($workflow->resolvedSteps())->max('sort_order')) + 10,
            'assigned_role' => $validated['assigned_role'],
            'required' => $validated['required'],
            'applies_from' => $validated['apply_to_existing'] ? null : now()->toIso8601String(),
        ]);

        $workflow->update(['steps' => $steps->values()->all(), 'updated_by' => $request->user()->id]);

        return back()->with('status', $validated['name'].' was added to the registration workflow.');
    }

    public function destroyStep(Request $request, WorkflowDefinition $workflow, string $step): RedirectResponse
    {
        $configured = collect($workflow->resolvedSteps())->firstWhere('key', $step);
        abort_unless(($configured['type'] ?? null) === 'manual_checkpoint', 404);

        $workflow->update([
            'steps' => collect($workflow->steps ?? [])->reject(fn (array $item): bool => ($item['key'] ?? null) === $step)->values()->all(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', $configured['name'].' was removed from the active workflow. Existing audit records were retained.');
    }

    private function prefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
