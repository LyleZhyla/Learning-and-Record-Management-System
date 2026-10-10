<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return array<string, mixed> */
    public function resolvedSteps(): array
    {
        $defaults = config('workflows.'.$this->key.'.steps', []);
        $stored = collect($this->steps ?? [])->keyBy('key');

        return collect($defaults)->map(function (array $definition, string $key) use ($stored): array {
            $step = $stored->get($key, []);

            return [
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'enabled' => array_key_exists('enabled', $step) ? (bool) $step['enabled'] : (bool) $definition['enabled'],
                'mode' => in_array($step['mode'] ?? null, ['manual', 'automatic'], true) ? $step['mode'] : $definition['mode'],
                'sort_order' => (int) ($step['sort_order'] ?? ((array_search($key, array_keys($defaults), true) + 1) * 10)),
            ];
        })->sortBy('sort_order')->values()->all();
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
