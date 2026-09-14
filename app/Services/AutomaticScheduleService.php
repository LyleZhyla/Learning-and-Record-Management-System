<?php

namespace App\Services;

use App\Models\NstpSection;
use App\Models\ScheduleSetting;
use App\Models\SectionSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AutomaticScheduleService
{
    public function generate(ScheduleSetting $setting, User $actor): int
    {
        $sections = NstpSection::query()
            ->where('component_id', $setting->component_id)
            ->where('academic_year', $setting->academic_year)
            ->where('semester', $setting->semester)
            ->where('status', 'active')->orderBy('code')->get();
        $slots = $this->availableSlots($setting);

        if ($sections->isNotEmpty() && $slots->isEmpty()) {
            throw ValidationException::withMessages(['schedule' => 'The configured time window cannot fit one complete section session.']);
        }

        $targetIds = $sections->pluck('id');
        $occupied = SectionSchedule::query()->with('section:id,facilitator_id,academic_year,semester')
            ->whereNotIn('section_id', $targetIds)
            ->where('day_of_week', $setting->day_of_week)
            ->whereHas('section', fn ($query) => $query->where('academic_year', $setting->academic_year)->where('semester', $setting->semester)->whereNotNull('facilitator_id'))
            ->get()->groupBy(fn ($schedule) => $schedule->section->facilitator_id);
        $assigned = collect();

        foreach ($sections as $section) {
            $facilitatorBusy = $section->facilitator_id
                ? collect($occupied->get($section->facilitator_id, []))->concat($assigned->get($section->facilitator_id, collect()))
                : collect();
            $slot = $slots->first(fn ($candidate) => ! $this->overlapsAny($candidate, $facilitatorBusy));

            if (! $slot) {
                $name = $section->facilitator?->name ?? 'the unassigned section group';
                throw ValidationException::withMessages(['schedule' => "No conflict-free slot remains for {$name}. Extend the day window, shorten the session hours, or change the meeting day."]);
            }

            $schedule = SectionSchedule::updateOrCreate(['section_id' => $section->id], [
                'day_of_week' => $setting->day_of_week,
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'is_automatic' => true,
                'updated_by' => $actor->id,
            ]);
            if ($section->facilitator_id) {
                $assigned->put($section->facilitator_id, $assigned->get($section->facilitator_id, collect())->push($schedule));
            }
        }

        return $sections->count();
    }

    public function availableSlots(ScheduleSetting $setting): Collection
    {
        $cursor = $this->time($setting->day_start);
        $end = $this->time($setting->day_end);
        $breakStart = $setting->break_start ? $this->time($setting->break_start) : null;
        $breakEnd = $setting->break_end ? $this->time($setting->break_end) : null;
        $slots = collect();

        while ($cursor->lt($end)) {
            if ($breakStart && $breakEnd && $cursor->gte($breakStart) && $cursor->lt($breakEnd)) {
                $cursor = $breakEnd;
            }
            $slotEnd = $cursor->addMinutes($setting->session_minutes);
            if ($breakStart && $breakEnd && $cursor->lt($breakStart) && $slotEnd->gt($breakStart)) {
                $slotEnd = $slotEnd->addMinutes($breakStart->diffInMinutes($breakEnd));
            }
            if ($slotEnd->gt($end)) {
                break;
            }
            $slots->push(['starts_at' => $cursor->format('H:i:s'), 'ends_at' => $slotEnd->format('H:i:s')]);
            $cursor = $slotEnd;
        }

        return $slots;
    }

    public function teachingMinutes(string $start, string $end, ?string $breakStart, ?string $breakEnd): int
    {
        $startAt = $this->time($start);
        $endAt = $this->time($end);
        $minutes = $startAt->diffInMinutes($endAt, false);
        if ($minutes <= 0) {
            return 0;
        }
        if ($breakStart && $breakEnd) {
            $overlapStart = $startAt->max($this->time($breakStart));
            $overlapEnd = $endAt->min($this->time($breakEnd));
            if ($overlapEnd->gt($overlapStart)) {
                $minutes -= $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        return $minutes;
    }

    public function overlapsAny(array $candidate, Collection $schedules): bool
    {
        return $schedules->contains(fn ($schedule) => $candidate['starts_at'] < $schedule->ends_at && $candidate['ends_at'] > $schedule->starts_at);
    }

    private function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('H:i:s', strlen($value) === 5 ? $value.':00' : $value);
    }
}
