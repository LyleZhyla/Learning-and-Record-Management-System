<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\OpenAiProjectProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProjectProposalGuideController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.project-proposal-guide', [
            'guidance' => null,
            'draft' => [],
            'isConfigured' => filled(config('services.openai.api_key')),
            'defaultComponent' => $this->defaultComponent($request),
        ]);
    }

    public function generate(Request $request, OpenAiProjectProposalService $guide): View|RedirectResponse
    {
        $proposal = $request->validate([
            'component' => ['required', Rule::in(['CWTS', 'LTS', 'ROTC'])],
            'project_title' => ['required', 'string', 'max:180'],
            'community_need' => ['required', 'string', 'max:3000'],
            'target_beneficiaries' => ['required', 'string', 'max:1200'],
            'proposed_objectives' => ['nullable', 'string', 'max:3000'],
            'proposed_activities' => ['nullable', 'string', 'max:3000'],
            'timeline' => ['nullable', 'string', 'max:1200'],
            'available_resources' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $guidance = $guide->guide($request->user(), $proposal);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return back()->withErrors([
                'proposal_guidance' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'AI proposal guidance is temporarily unavailable. Please try again later.',
            ])->withInput();
        }

        return view('student.project-proposal-guide', [
            'guidance' => $guidance,
            'draft' => $proposal,
            'isConfigured' => true,
            'defaultComponent' => $proposal['component'],
        ]);
    }

    private function defaultComponent(Request $request): string
    {
        return $request->user()->latestNstpEnrollment()
            ->with('component')->first()?->component?->code ?? 'CWTS';
    }
}
