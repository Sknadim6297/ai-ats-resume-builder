<?php

namespace App\Http\Controllers;

use App\Http\Requests\Experience\StoreWorkExperienceRequest;
use App\Http\Requests\Experience\UpdateWorkExperienceRequest;
use App\Models\WorkExperience;
use App\Services\Profile\WorkExperienceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkExperienceController extends Controller
{
    public function __construct(
        private readonly WorkExperienceService $experiences,
    ) {}

    public function index(Request $request): Response
    {
        $items = $this->experiences->listForUser($request->user())
            ->map(fn (WorkExperience $item) => $this->transform($item));

        return Inertia::render('Experience/Index', [
            'experiences' => $items,
            'status' => session('status'),
            'employmentTypes' => [
                'Full-time',
                'Part-time',
                'Contract',
                'Freelance',
                'Internship',
            ],
        ]);
    }

    public function store(StoreWorkExperienceRequest $request): RedirectResponse
    {
        $this->experiences->create($request->user(), $request->validated());

        return redirect()->route('experience.index')->with('status', 'experience-created');
    }

    public function update(UpdateWorkExperienceRequest $request, WorkExperience $experience): RedirectResponse
    {
        $owned = $this->experiences->findOwned($request->user(), $experience->id);
        $this->experiences->update($request->user(), $owned, $request->validated());

        return redirect()->route('experience.index')->with('status', 'experience-updated');
    }

    public function destroy(Request $request, WorkExperience $experience): RedirectResponse
    {
        $owned = $this->experiences->findOwned($request->user(), $experience->id);
        $this->experiences->delete($request->user(), $owned);

        return redirect()->route('experience.index')->with('status', 'experience-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(WorkExperience $experience): array
    {
        return [
            'id' => $experience->id,
            'company_name' => $experience->company_name,
            'job_title' => $experience->job_title,
            'employment_type' => $experience->employment_type,
            'location' => $experience->location,
            'start_date' => optional($experience->start_date)?->format('Y-m-d'),
            'end_date' => optional($experience->end_date)?->format('Y-m-d'),
            'is_current' => $experience->is_current,
            'responsibilities' => $experience->responsibilities ?? [],
            'achievements' => $experience->achievements ?? [],
            'technologies' => $experience->technologies->pluck('name')->values(),
        ];
    }
}
