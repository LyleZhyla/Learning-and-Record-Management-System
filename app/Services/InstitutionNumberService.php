<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstitutionNumberService
{
    public function __construct(private InstitutionContext $context) {}

    public function next(string $sequenceKey, string $prefix): string
    {
        $institution = $this->context->institution();
        if (! $institution) {
            throw new RuntimeException('An active institution is required to generate a reference number.');
        }

        $number = DB::transaction(function () use ($institution, $sequenceKey): int {
            $row = DB::table('institution_number_sequences')
                ->where('institution_id', $institution->id)
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->first();
            if (! $row) {
                DB::table('institution_number_sequences')->insert([
                    'institution_id' => $institution->id,
                    'sequence_key' => $sequenceKey,
                    'next_number' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }
            DB::table('institution_number_sequences')->where('id', $row->id)->update([
                'next_number' => $row->next_number + 1,
                'updated_at' => now(),
            ]);

            return (int) $row->next_number;
        });

        return sprintf('%s-%05d', $prefix, $number);
    }
}
