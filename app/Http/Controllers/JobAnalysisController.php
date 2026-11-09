<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobAnalysis\AnalyzeJobRequest;
use App\Http\Requests\JobAnalysis\DecideSkillRequest;
use App\Http\Requests\JobAnalysis\SaveProjectSelectionRequest;
use App\Models\JobAnalysis;
use App\Services\AI\JobAnalysis\JobAnalysisService;
use App\Services\AI\JobAnalysis\JobAnalysisSkillDecisionService;
use App\Services\Projects\JobAnalysisProjectSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobAnalysisController extends Controller
{
    public function __construct(
        private readonly JobAnalysisService $analyses,
        private readonly JobAnalysisSkillDecisionService $skillDecisions,
        private readonly JobAnalysisProjectSelectionService $projectSelections,
    ) {}

    public function index(Request $request): Response
    {
        $paginator = $this->analyses->listForUser($request->user());

        return Inertia::render('JobAnalyzer/Index', [
            'analyses' => $paginator->through(fn (JobAnalysis $item) => [
                'id' => $item->id,
                'job_title' => $item->job_title,
                'company_name' => $item->company_name,
                'profile_name' => $item->profile?->name,
                'status' => $item->status,
                'created_at' => optional($item->created_at)?->toDateTimeString(),
            ]),
            'limits' => [
                'min' => (int) config('ai.job_analysis.min_description_length', 80),
                'max' => (int) config('ai.job_analysis.max_description_length', 20000),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function store(AnalyzeJobRequest $request): RedirectResponse
    {
        $analysis = $this->analyses->analyze($request->user(), $request->validated());

        return redirect()
            ->route('job-analyzer.show', $analysis)
            ->with('status', 'job-analysis-completed');
    }

    public function show(Request $request, JobAnalysis $jobAnalysis): Response
    {
        $owned = $this->analyses->findOwned($request->user(), $jobAnalysis->id);

        return Inertia::render('JobAnalyzer/Show', [
            'analysis' => $this->analyses->present($request->user(), $owned),
            'status' => session('status'),
        ]);
    }

    public function decideSkill(DecideSkillRequest $request, JobAnalysis $jobAnalysis): RedirectResponse
    {
        $owned = $this->analyses->findOwned($request->user(), $jobAnalysis->id);
        $this->skillDecisions->decide($request->user(), $owned, $request->validated());

        return redirect()
            ->route('job-analyzer.show', $owned)
            ->with('status', 'skill-decision-saved');
    }

    public function saveProjectSelection(SaveProjectSelectionRequest $request, JobAnalysis $jobAnalysis): RedirectResponse
    {
        $owned = $this->analyses->findOwned($request->user(), $jobAnalysis->id);
        $this->projectSelections->save(
            $request->user(),
            $owned,
            $request->validated('project_ids') ?? [],
            asManual: true,
        );

        return redirect()
            ->route('job-analyzer.show', $owned)
            ->with('status', 'project-selection-saved');
    }

    public function resetProjectSelection(Request $request, JobAnalysis $jobAnalysis): RedirectResponse
    {
        $owned = $this->analyses->findOwned($request->user(), $jobAnalysis->id);
        $this->projectSelections->resetToRecommendations($request->user(), $owned);

        return redirect()
            ->route('job-analyzer.show', $owned)
            ->with('status', 'project-selection-reset');
    }
}
