<?php

namespace App\Services;

use App\Models\RetentionPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RetentionPolicyService
{
    public function enforce(RetentionPolicy $policy): int
    {
        $cutoff = now()->subDays($policy->retention_days);
        $query = DB::table($policy->record_type)
            ->where('institution_id', $policy->institution_id)
            ->where('created_at', '<', $cutoff);

        $affected = match ($policy->disposition) {
            'delete' => $query->delete(),
            'archive' => $this->archive($policy, $query),
            'anonymize' => $this->anonymize($policy, $query),
            default => throw new InvalidArgumentException('Unsupported retention disposition.'),
        };

        $policy->forceFill([
            'last_enforced_at' => now(),
            'last_affected_count' => $affected,
        ])->save();

        return $affected;
    }

    private function archive(RetentionPolicy $policy, mixed $query): int
    {
        if ($policy->record_type !== 'audit_logs') {
            throw new InvalidArgumentException('Archiving is supported only for system audit logs.');
        }

        return $query->whereNull('archived_at')->update(['archived_at' => now()]);
    }

    private function anonymize(RetentionPolicy $policy, mixed $query): int
    {
        if ($policy->record_type !== 'privacy_consents') {
            throw new InvalidArgumentException('Anonymization is supported only for expired consent records.');
        }

        $ids = $query->pluck('id');
        foreach ($ids as $id) {
            DB::table('privacy_consents')->where('id', $id)->update([
                'user_id' => null,
                'subject_email' => "anonymized-{$id}@privacy.invalid",
                'ip_address' => null,
                'source_type' => null,
                'source_id' => null,
                'updated_at' => now(),
            ]);
        }

        return $ids->count();
    }
}
