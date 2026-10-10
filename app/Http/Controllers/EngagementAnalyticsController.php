<?php

namespace App\Http\Controllers;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Services\EngagementAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EngagementAnalyticsController extends Controller
{
    public function __construct(private EngagementAnalyticsService $analytics) {}

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'section_id' => ['nullable', 'integer', 'exists:nstp_sections,id'],
            'status' => ['nullable', Rule::in(['engaged', 'monitor', 'at_risk'])],
        ]);
        $user = $request->user();
        $query = NstpEnrollment::query()->with(['student', 'component', 'section.component'])->where('status', 'enrolled');

        if ($user->role === 'coordinator') {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->role === 'facilitator') {
            $query->whereHas('section', fn ($section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->role === 'student') {
            $query->where('student_id', $user->id);
        }

        $query->when($filters['component_id'] ?? null, fn ($builder, $value) => $builder->where('component_id', $value))
            ->when($filters['section_id'] ?? null, fn ($builder, $value) => $builder->where('section_id', $value));

        $rows = $this->analytics->analyze($query->get());
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(strtolower($row['student']->name.' '.$row['student']->email), $search))->values();
        }
        if (filled($filters['status'] ?? null)) {
            $rows = $rows->where('status', $filters['status'])->values();
        }

        $metrics = [
            'total' => $rows->count(),
            'average' => $rows->isEmpty() ? null : round($rows->avg('score'), 1),
            'engaged' => $rows->where('status', 'engaged')->count(),
            'monitor' => $rows->where('status', 'monitor')->count(),
            'at_risk' => $rows->where('status', 'at_risk')->count(),
        ];
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginatedRows = new LengthAwarePaginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $sections = NstpSection::with('component')->orderByDesc('academic_year')->orderBy('code');
        $components = NstpComponent::orderBy('code');
        if ($user->role === 'coordinator') {
            $sections->where('component_id', $user->nstp_component_id ?? 0);
            $components->whereKey($user->nstp_component_id ?? 0);
        } elseif ($user->role === 'facilitator') {
            $sections->where('facilitator_id', $user->id);
            $components->whereHas('sections', fn ($section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->role === 'student') {
            $sections->whereHas('enrollments', fn ($enrollment) => $enrollment->where('student_id', $user->id)->where('status', 'enrolled'));
            $components->whereHas('enrollments', fn ($enrollment) => $enrollment->where('student_id', $user->id)->where('status', 'enrolled'));
        }

        $layout = match ($user->role) {
            'super_admin' => 'layouts.admin',
            'nstp_admin' => 'layouts.nstp-admin',
            default => 'layouts.'.$user->role,
        };

        return view('engagement-analytics.index', [
            'rows' => $paginatedRows,
            'metrics' => $metrics,
            'filters' => $filters,
            'components' => $components->get(),
            'sections' => $sections->get(),
            'layout' => $layout,
            'isStudent' => $user->role === 'student',
        ]);
    }
}
