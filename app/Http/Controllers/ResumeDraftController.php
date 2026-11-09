<?php

namespace App\Http\Controllers;

use App\Http\Requests\Resume\GenerateResumeRequest;
use App\Http\Requests\Resume\UpdateResumeDraftRequest;
use App\Models\JobAnalysis;
use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Services\AI\JobAnalysis\JobAnalysisService;
use App\Services\Resume\ResumeDraftingService;
use App\Services\Resume\ResumeEditService;
use App\Services\Resume\ResumeExportService;
use App\Services\Resume\ResumeManagementService;
use App\Services\Resume\ResumeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeDraftController extends Controller
{
    public function __construct(
        private readonly JobAnalysisService $analyses,
        private readonly ResumeDraftingService $drafts,
        private readonly ResumeEditService $editor,
        private readonly ResumeVersionService $versions,
        private readonly ResumeExportService $exports,
        private readonly ResumeManagementService $management,
    ) {}

    public function index(Request $request): Response
    {
        $paginator = $this->management->listForUser($request->user(), [
            'search' => $request->string('search')->toString(),
            'sort' => $request->string('sort')->toString() ?: 'updated_desc',
        ]);

        return Inertia::render('ResumeDrafts/Index', [
            'resumes' => $paginator->through(fn (ResumeDraft $draft) => $this->management->presentListItem($draft)),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $request->string('sort')->toString() ?: 'updated_desc',
            ],
            'status' => session('status'),
        ]);
    }

    public function generate(GenerateResumeRequest $request, JobAnalysis $jobAnalysis): RedirectResponse
    {
        $owned = $this->analyses->findOwned($request->user(), $jobAnalysis->id);
        $draft = $this->drafts->generate(
            $request->user(),
            $owned,
            (bool) $request->boolean('confirm_replace'),
        );

        return redirect()
            ->route('resume-drafts.show', $draft)
            ->with('status', 'resume-draft-generated');
    }

    public function show(Request $request, ResumeDraft $resumeDraft): Response
    {
        $owned = $this->drafts->findOwned($request->user(), $resumeDraft->id);

        return Inertia::render('ResumeDrafts/Show', [
            'draft' => $this->drafts->present($request->user(), $owned),
            'status' => session('status'),
        ]);
    }

    public function edit(Request $request, ResumeDraft $resumeDraft): Response
    {
        $owned = $this->drafts->findOwned($request->user(), $resumeDraft->id);

        return Inertia::render('ResumeDrafts/Edit', [
            'draft' => $this->drafts->present($request->user(), $owned),
            'status' => session('status'),
        ]);
    }

    public function update(UpdateResumeDraftRequest $request, ResumeDraft $resumeDraft): RedirectResponse
    {
        $owned = $this->drafts->findOwned($request->user(), $resumeDraft->id);
        $this->editor->save($request->user(), $owned, $request->validated());

        return redirect()
            ->route('resume-drafts.show', $owned)
            ->with('status', 'resume-draft-saved');
    }

    public function restore(Request $request, ResumeDraft $resumeDraft, ResumeVersion $resumeVersion): RedirectResponse
    {
        $ownedDraft = $this->drafts->findOwned($request->user(), $resumeDraft->id);
        $ownedVersion = $this->versions->findOwned($request->user(), $resumeVersion->id);

        if ($ownedVersion->resume_draft_id !== $ownedDraft->id) {
            abort(404);
        }

        $this->versions->restore($request->user(), $ownedVersion);

        return redirect()
            ->route('resume-drafts.show', $ownedDraft)
            ->with('status', 'resume-version-restored');
    }

    public function downloadPdf(Request $request, ResumeDraft $resumeDraft, ?ResumeVersion $resumeVersion = null): StreamedResponse
    {
        $ownedDraft = $this->drafts->findOwned($request->user(), $resumeDraft->id);
        $version = $resumeVersion
            ? $this->versions->findOwned($request->user(), $resumeVersion->id)
            : $this->exports->currentVersionForDraft($request->user(), $ownedDraft);

        if ($version->resume_draft_id !== $ownedDraft->id) {
            abort(404);
        }

        return $this->exports->downloadPdf($request->user(), $version);
    }

    public function downloadDocx(Request $request, ResumeDraft $resumeDraft, ?ResumeVersion $resumeVersion = null): StreamedResponse
    {
        $ownedDraft = $this->drafts->findOwned($request->user(), $resumeDraft->id);
        $version = $resumeVersion
            ? $this->versions->findOwned($request->user(), $resumeVersion->id)
            : $this->exports->currentVersionForDraft($request->user(), $ownedDraft);

        if ($version->resume_draft_id !== $ownedDraft->id) {
            abort(404);
        }

        return $this->exports->downloadDocx($request->user(), $version);
    }

    public function showVersion(Request $request, ResumeDraft $resumeDraft, ResumeVersion $resumeVersion): Response
    {
        $ownedDraft = $this->drafts->findOwned($request->user(), $resumeDraft->id);
        $ownedVersion = $this->versions->findOwned($request->user(), $resumeVersion->id);

        if ($ownedVersion->resume_draft_id !== $ownedDraft->id) {
            abort(404);
        }

        $presented = $this->drafts->present($request->user(), $ownedDraft);
        $presented['content'] = $ownedVersion->structured_content;
        $presented['current_version_number'] = $ownedVersion->version_number;
        $presented['current_version_id'] = $ownedVersion->id;
        $presented['viewing_historical'] = ! $ownedVersion->is_current;

        return Inertia::render('ResumeDrafts/Show', [
            'draft' => $presented,
            'status' => session('status'),
        ]);
    }
}
