<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\NstpSection;
use App\Services\AcademicTermLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    public function __construct(private AcademicTermLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        abort_if($request->user()->isFacilitator() || $request->user()->isStudent(), 403);
        $terms = AcademicTerm::with(['approvals.section.component', 'approvals.approver', 'transitions.actor', 'snapshots'])->latest('academic_year')->paginate($this->perPage())->withQueryString();
        $sections = NstpSection::with('component')->when($request->user()->isCoordinator(), fn ($query) => $query->where('component_id', $request->user()->nstp_component_id ?? 0))->orderBy('code')->get();

        return view('academic-terms.index', compact('terms', 'sections'));
    }

    public function update(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        abort_if($request->user()->isCoordinator(), 403);
        abort_if(in_array($academicTerm->status, ['finalized', 'archived'], true), 422, 'Reopen the term before changing its settings.');
        $data = $request->validate(['starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'grade_submission_deadline' => ['required', 'date']]);
        $academicTerm->update($data);

        return back()->with('status', 'Academic-term dates and grade deadline updated.');
    }

    public function transition(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        abort_if($request->user()->isCoordinator(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(AcademicTerm::STATUSES))], 'reason' => ['nullable', 'string', 'max:2000']]);
        $this->lifecycle->transition($academicTerm, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Academic term advanced to '.AcademicTerm::STATUSES[$data['status']].'.');
    }

    public function approve(Request $request, AcademicTerm $academicTerm, NstpSection $section): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);
        $this->lifecycle->approveSection($academicTerm, $section, $request->user(), $data['remarks'] ?? null);

        return back()->with('status', $section->name.' final grades approved.');
    }

    public function reopen(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        abort_if($request->user()->isCoordinator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000'], 'grade_submission_deadline' => ['required', 'date', 'after:now']]);
        $this->lifecycle->reopen($academicTerm, $request->user(), $data['reason'], $data['grade_submission_deadline']);

        return back()->with('status', 'Term reopened to Grading with a recorded reason and new deadline.');
    }
}
