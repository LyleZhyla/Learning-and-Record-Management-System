<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\ScheduleSetting;
use App\Models\SectionSchedule;
use App\Services\AutomaticScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(private AutomaticScheduleService $scheduler) {}

    public function index(Request $request): View
    {
        $componentId = $this->componentId($request);
        $academicYear = $request->input('academic_year') ?: $this->defaultAcademicYear($componentId);
        $semester = $request->input('semester', 'first');
        $request->validate([
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', Rule::in(array_keys(NstpSection::SEMESTERS))],
        ]);

        $setting = ScheduleSetting::firstOrNew(
            ['component_id' => $componentId, 'academic_year' => $academicYear, 'semester' => $semester],
            ['day_of_week' => 6, 'day_start' => '08:00:00', 'day_end' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'session_minutes' => 240],
        );
        $sections = NstpSection::with(['component', 'facilitator', 'schedule'])
            ->where('component_id', $componentId)->where('academic_year', $academicYear)->where('semester', $semester)
            ->where('status', 'active')->orderBy('code')->paginate(15)->withQueryString();

        return view('learning.schedules.index', [
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
            'components' => $this->components($request),
            'componentId' => $componentId,
            'academicYear' => $academicYear,
            'academicYears' => NstpSection::where('component_id', $componentId)->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'semester' => $semester,
            'setting' => $setting,
            'sections' => $sections,
            'days' => self::DAYS,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $componentId = $this->componentId($request);
        $validated = $request->validate($this->settingRules());
        $this->validateWindow($validated);
        ScheduleSetting::updateOrCreate(
            ['component_id' => $componentId, 'academic_year' => $validated['academic_year'], 'semester' => $validated['semester']],
            [...collect($validated)->except(['component_id'])->all(), 'updated_by' => $request->user()->id],
        );

        return back()->with('status', 'Scheduling hours saved. Run automatic scheduling to apply the new time window to sections.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $componentId = $this->componentId($request);
        $validated = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
        ]);
        $setting = ScheduleSetting::where('component_id', $componentId)
            ->where('academic_year', $validated['academic_year'])->where('semester', $validated['semester'])->first();
        if (! $setting) {
            throw ValidationException::withMessages(['schedule' => 'Save the scheduling hours before generating section schedules.']);
        }

        $count = DB::transaction(fn () => $this->scheduler->generate($setting, $request->user()));

        return back()->with('status', "Automatic scheduling completed for {$count} active section(s).");
    }

    public function updateSection(Request $request, NstpSection $section): RedirectResponse
    {
        $componentId = $this->componentId($request);
        abort_unless($section->component_id === $componentId, 403);
        $setting = ScheduleSetting::where('component_id', $componentId)
            ->where('academic_year', $section->academic_year)->where('semester', $section->semester)->firstOrFail();
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(self::DAYS))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);
        $minutes = $this->scheduler->teachingMinutes($validated['starts_at'], $validated['ends_at'], $setting->break_start, $setting->break_end);
        if ($minutes !== $setting->session_minutes) {
            throw ValidationException::withMessages(['ends_at' => 'The schedule must contain exactly '.$this->durationLabel($setting->session_minutes).' of class time, excluding the noon break.']);
        }
        if ($setting->day_start > $validated['starts_at'].':00' || $setting->day_end < $validated['ends_at'].':00') {
            throw ValidationException::withMessages(['starts_at' => 'The section schedule must stay within the configured daily start and end time.']);
        }

        if ($section->facilitator_id) {
            $conflict = $this->scheduler->conflictingSchedule(
                $section->facilitator_id,
                $section->academic_year,
                $section->semester,
                (int) $validated['day_of_week'],
                $validated['starts_at'],
                $validated['ends_at'],
                $section->id,
            );
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'This conflicts with '.$conflict->section->code.', which is handled by the same facilitator.']);
            }
        }

        SectionSchedule::updateOrCreate(['section_id' => $section->id], [
            ...$validated, 'is_automatic' => false, 'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', "Schedule for {$section->code} updated successfully.");
    }

    private function settingRules(): array
    {
        return [
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(self::DAYS))],
            'day_start' => ['required', 'date_format:H:i'],
            'day_end' => ['required', 'date_format:H:i', 'after:day_start'],
            'break_start' => ['nullable', 'required_with:break_end', 'date_format:H:i'],
            'break_end' => ['nullable', 'required_with:break_start', 'date_format:H:i', 'after:break_start'],
            'session_hours' => ['required', 'numeric', 'min:0.5', 'max:12'],
        ];
    }

    private function validateWindow(array &$values): void
    {
        if (($values['break_start'] ?? null) && ($values['break_start'] <= $values['day_start'] || $values['break_end'] >= $values['day_end'])) {
            throw ValidationException::withMessages(['break_start' => 'The noon break must be fully inside the daily scheduling window.']);
        }
        $values['session_minutes'] = (int) round((float) $values['session_hours'] * 60);
        unset($values['session_hours']);
        $temporary = new ScheduleSetting($values);
        if ($this->scheduler->availableSlots($temporary)->isEmpty()) {
            throw ValidationException::withMessages(['session_hours' => 'The daily window cannot fit a session of this length around the configured break.']);
        }
    }

    private function componentId(Request $request): int
    {
        if ($request->user()->isCoordinator()) {
            abort_unless($request->user()->nstp_component_id, 403);
            if ($request->filled('component_id')) {
                abort_unless($request->integer('component_id') === $request->user()->nstp_component_id, 403);
            }

            return (int) $request->user()->nstp_component_id;
        }
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isNstpAdmin(), 403);

        return $request->integer('component_id') ?: (int) NstpComponent::where('is_active', true)->orderBy('code')->value('id');
    }

    private function components(Request $request)
    {
        return NstpComponent::query()->when($request->user()->isCoordinator(), fn ($query) => $query->whereKey($request->user()->nstp_component_id))->where('is_active', true)->orderBy('code')->get();
    }

    private function defaultAcademicYear(int $componentId): string
    {
        return NstpSection::where('component_id', $componentId)->orderByDesc('academic_year')->value('academic_year')
            ?? (now()->month >= 6 ? now()->year.'-'.(now()->year + 1) : (now()->year - 1).'-'.now()->year);
    }

    private function durationLabel(int $minutes): string
    {
        return $minutes % 60 === 0 ? ($minutes / 60).' hour(s)' : number_format($minutes / 60, 1).' hours';
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : ($request->user()->isCoordinator() ? 'coordinator' : 'nstp_admin');
    }

    private function layout(Request $request): string
    {
        return 'layouts.'.str_replace('_', '-', $this->routePrefix($request));
    }
}
