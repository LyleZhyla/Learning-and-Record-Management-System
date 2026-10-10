<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class NotificationRule extends Model
{
    public const CHANNELS = [
        'bell' => 'Notification bell',
        'sidebar' => 'Sidebar badge',
    ];

    protected $fillable = [
        'event_key', 'name', 'description', 'is_enabled', 'channels', 'title_template',
        'body_template', 'schedule_mode', 'delay_minutes', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'channels' => 'array', 'delay_minutes' => 'integer'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function configured(string $event): self
    {
        $defaults = config('notification_rules.'.$event, []);
        $rule = Schema::hasTable('notification_rules')
            ? static::where('event_key', $event)->first()
            : null;

        return $rule ?? new static([
            'event_key' => $event,
            'name' => $defaults['name'] ?? str($event)->headline()->toString(),
            'description' => $defaults['description'] ?? null,
            'is_enabled' => true,
            'channels' => array_keys(self::CHANNELS),
            'title_template' => $defaults['title_template'] ?? '{title}',
            'body_template' => $defaults['body_template'] ?? '{body}',
            'schedule_mode' => 'immediate',
            'delay_minutes' => 0,
        ]);
    }

    public function usesChannel(string $channel): bool
    {
        return $this->is_enabled && in_array($channel, $this->channels ?? [], true);
    }

    public function availableAt(?CarbonInterface $from = null): CarbonInterface
    {
        $availableAt = ($from ?? now())->copy();

        return $this->schedule_mode === 'delayed'
            ? $availableAt->addMinutes(max(1, (int) $this->delay_minutes))
            : $availableAt;
    }

    /** @param array<string, scalar|null> $values */
    public function render(string $template, array $values): string
    {
        return strtr($template, collect($values)->mapWithKeys(
            fn (mixed $value, string $key): array => ['{'.$key.'}' => (string) ($value ?? '')]
        )->all());
    }

    /** @param array<string, scalar|null> $values */
    public function renderedTitle(array $values): string
    {
        return str($this->render($this->title_template, $values))->limit(180, '')->toString();
    }

    /** @param array<string, scalar|null> $values */
    public function renderedBody(array $values): string
    {
        return $this->render($this->body_template, $values);
    }
}
