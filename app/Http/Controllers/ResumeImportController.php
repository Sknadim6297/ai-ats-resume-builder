<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResumeImport\CommitResumeImportRequest;
use App\Http\Requests\ResumeImport\StoreResumeImportRequest;
use App\Http\Requests\ResumeImport\UpdateResumeImportReviewRequest;
use App\Models\Profile;
use App\Models\ResumeImport;
use App\Services\ResumeImport\ResumeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumeImportController extends Controller
{
    public function __construct(
        private readonly ResumeImportService $imports,
    ) {}

    public function create(): Response
    {
        return Inertia::render('ResumeImports/Wizard', [
            'import' => null,
            'mergeProfiles' => [],
            'status' => session('status'),
        ]);
    }

    public function store(StoreResumeImportRequest $request): RedirectResponse
    {
        $import = $this->imports->storeUpload($request->user(), $request->file('resume'));

        return redirect()
            ->route('resume-imports.show', $import)
            ->with('status', 'resume-import-uploaded');
    }

    public function show(Request $request, ResumeImport $resumeImport): Response
    {
        $owned = $this->imports->findOwned($request->user(), $resumeImport->id);

        $mergeProfiles = Profile::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'headline'])
            ->map(fn (Profile $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'headline' => $p->headline,
            ])
            ->all();

        return Inertia::render('ResumeImports/Wizard', [
            'import' => $this->imports->present($owned),
            'mergeProfiles' => $mergeProfiles,
            'status' => session('status'),
        ]);
    }

    public function extract(Request $request, ResumeImport $resumeImport): RedirectResponse
    {
        $resumeImport = $this->imports->findOwned($request->user(), $resumeImport->id);
        $this->imports->runExtraction($request->user(), $resumeImport);

        return redirect()
            ->route('resume-imports.show', $resumeImport)
            ->with('status', 'resume-import-extracted');
    }

    public function parse(Request $request, ResumeImport $resumeImport): RedirectResponse
    {
        $resumeImport = $this->imports->findOwned($request->user(), $resumeImport->id);
        $this->imports->runParsing($request->user(), $resumeImport);

        return redirect()
            ->route('resume-imports.show', $resumeImport)
            ->with('status', 'resume-import-parsed');
    }

    public function updateReview(UpdateResumeImportReviewRequest $request, ResumeImport $resumeImport): RedirectResponse
    {
        $resumeImport = $this->imports->findOwned($request->user(), $resumeImport->id);
        $this->imports->saveReview($request->user(), $resumeImport, $request->validated());

        return redirect()
            ->route('resume-imports.show', $resumeImport)
            ->with('status', 'resume-import-review-saved');
    }

    public function commit(CommitResumeImportRequest $request, ResumeImport $resumeImport): RedirectResponse
    {
        $resumeImport = $this->imports->findOwned($request->user(), $resumeImport->id);
        $profile = $this->imports->commit($request->user(), $resumeImport, $request->validated());

        return redirect()
            ->route('profiles.edit', $profile)
            ->with('status', 'resume-import-completed');
    }

    public function cancel(Request $request, ResumeImport $resumeImport): RedirectResponse
    {
        $resumeImport = $this->imports->findOwned($request->user(), $resumeImport->id);
        $this->imports->cancel($request->user(), $resumeImport);

        return redirect()
            ->route('resume-imports.create')
            ->with('status', 'resume-import-cancelled');
    }
}
