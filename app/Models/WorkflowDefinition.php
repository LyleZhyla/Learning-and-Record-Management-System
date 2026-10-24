<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class WorkflowDefinition extends Model
{
    protected $fillable = ['key', 'name', 'description', 'steps', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'is_active' => 'boolean'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function stepCompletions(): HasMany
    {
        return $this->hasMany(WorkflowStepCompletion::class);
    }

    /** @return array<string, mixed> */
    public function resolvedSteps(): array
    {
        $defaults = config('workflows.'.$this->key.'.steps', []);
        $stored = collect($this->steps ?? [])->keyBy('key');

        $systemSteps = collect($defaults)->map(function (array $definition, string $key) use ($stored): array {
            $step = $stored->get($key, []);

            return [
                'key' => $key,
                'type' => 'system_rule',
                'name' => $definition['name'],
                'description' => $definition['description'],
                'enabled' => array_key_exists('enabled', $step) ? (bool) $step['enabled'] : (bool) $definition['enabled'],
                'mode' => in_array($step['mode'] ?? null, ['manual', 'automatic'], true) ? $step['mode'] : $definition['mode'],
                'sort_order' => (int) ($step['sort_order'] ?? ((array_search($key, array_keys($defaults), true) + 1) * 10)),
            ];
        });

        $customSteps = $stored->filter(fn (array $step, string $key): bool => ! array_key_exists($key, $defaults))
            ->filter(fn (array $step): bool => ($step['type'] ?? null) === 'manual_checkpoint')
            ->map(fn (array $step, string $key): array => [
                'key' => $key,
                'type' => 'manual_checkpoint',
                'name' => (string) ($step['name'] ?? str($key)->headline()),
                'description' => (string) ($step['description'] ?? ''),
                'enabled' => (bool) ($step['enabled'] ?? true),
                'mode' => 'manual',
                'sort_order' => (int) ($step['sort_order'] ?? 999),
                'assigned_role' => in_array($step['assigned_role'] ?? null, ['any_admin', 'super_admin', 'nstp_admin'], true)
                    ? $step['assigned_role'] : 'any_admin',
                'required' => (bool) ($step['required'] ?? true),
                'applies_from' => $step['applies_from'] ?? null,
            ]);

        return $systemSteps->concat($customSteps)->sortBy('sort_order')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function customStepsFor(?Model $subject = null, bool $enabledOnly = true): array
    {
        return collect($this->resolvedSteps())
            ->where('type', 'manual_checkpoint')
            ->when($enabledOnly, fn ($steps) => $steps->where('enabled', true))
            ->filter(function (array $step) use ($subject): bool {
                if (! $subject || blank($step['applies_from'] ?? null) || ! $subject->created_at) {
                    return true;
                }

                return $subject->created_at->greaterThanOrEqualTo($step['applies_from']);
            })
            ->values()->all();
    }

    public function allowsCustomCheckpoints(): bool
    {
        return (bool) config('workflows.'.$this->key.'.allow_custom_checkpoints', false);
    }

    public static function ruleEnabled(string $workflow, string $step): bool
    {
        $fallback = (bool) config("workflows.{$workflow}.steps.{$step}.enabled", false);
        if (! Schema::hasTable('workflow_definitions')) {
            return $fallback;
        }

        $definition = static::where('key', $workflow)->first();
        if (! $definition || ! $definition->is_active) {
            return false;
        }

        $configured = collect($definition->resolvedSteps())->firstWhere('key', $step);

        return $configured ? (bool) $configured['enabled'] : $fallback;
    }

    public static function mode(string $workflow, string $step): string
    {
        if (Schema::hasTable('workflow_definitions')) {
            $definition = static::where('key', $workflow)->first();
            $configured = $definition ? collect($definition->resolvedSteps())->firstWhere('key', $step) : null;
            if ($configured) {
                return $configured['mode'];
            }
        }

        return config("workflows.{$workflow}.steps.{$step}.mode", 'manual');
    }

    public static function runsAutomatically(string $workflow, string $step): bool
    {
        return static::ruleEnabled($workflow, $step) && static::mode($workflow, $step) === 'automatic';
    }
}
