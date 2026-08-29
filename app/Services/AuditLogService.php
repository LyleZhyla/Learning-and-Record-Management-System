<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AuditLogService
{
    public function record(Request $request, User $actor, int $statusCode, int $durationMs): void
    {
        try {
            $routeName = $request->route()?->getName();
            [$action, $description] = $this->describe($request->method(), $routeName);

            AuditLog::create([
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role,
                'action' => $action,
                'description' => $description,
                'method' => $request->method(),
                'route_name' => $routeName,
                'path' => '/'.$request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'metadata' => $this->metadata($request),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function describe(string $method, ?string $routeName): array
    {
        $special = [
            'login.store' => ['login', 'Signed in to SNAPIE'],
            'logout' => ['logout', 'Signed out of SNAPIE'],
        ];
        if ($routeName && isset($special[$routeName])) {
            return $special[$routeName];
        }

        $operation = $routeName ? Str::afterLast($routeName, '.') : strtolower($method);
        $subject = $routeName
            ? Str::of($routeName)->replace(['.index', '.show', '.create', '.edit', '.store', '.update', '.destroy', '.status', '.password', '.scan', '.mark', '.close', '.download', '.export', '.print', '.qr'], '')->replace(['.', '_'], ' ')->headline()->lower()
            : 'page';

        return match ($operation) {
            'index', 'show', 'create', 'edit', 'dashboard' => ['view', "Viewed {$subject}"],
            'store' => ['create', "Created or submitted {$subject}"],
            'update', 'status', 'password', 'mark' => ['update', "Updated {$subject}"],
            'destroy' => ['delete', "Deleted {$subject}"],
            'scan' => ['qr_scan', 'Scanned a student attendance QR'],
            'close' => ['close', 'Closed an attendance session'],
            'download', 'qr' => ['download', "Downloaded {$subject}"],
            'export' => ['export', "Exported {$subject}"],
            'print' => ['print', "Opened printable {$subject}"],
            default => [strtolower($method), ucfirst(strtolower($method))." request to {$subject}"],
        };
    }

    private function metadata(Request $request): ?array
    {
        $routeParameters = collect($request->route()?->parameters() ?? [])->map(function (mixed $value): mixed {
            if ($value instanceof Model) {
                return ['type' => class_basename($value), 'id' => $value->getRouteKey()];
            }

            return is_scalar($value) ? Str::limit((string) $value, 150, '') : null;
        })->filter(fn (mixed $value) => $value !== null)->all();

        $query = collect($request->query())
            ->except(['password', 'token', 'remember_token'])
            ->map(fn (mixed $value) => is_scalar($value) ? Str::limit((string) $value, 150, '') : '[filtered]')
            ->all();

        $metadata = array_filter(['route_parameters' => $routeParameters, 'query' => $query]);

        return $metadata ?: null;
    }
}
