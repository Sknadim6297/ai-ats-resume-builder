<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Services\Profile\ProjectService;
use App\Support\ProjectTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type', 'technology', 'status']);
        $paginator = $this->projects->listForUser($request->user(), $filters);

        return Inertia::render('Projects/Index', [
            'projects' => $paginator->through(fn (Project $project) => $this->transform($project)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'technology' => $filters['technology'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'projectTypes' => ProjectTypes::OPTIONS,
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Form', [
            'project' => null,
            'projectTypes' => ProjectTypes::OPTIONS,
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->projects->create($request->user(), $request->validated());

        return redirect()->route('projects.index')->with('status', 'project-created');
    }

    public function show(Request $request, Project $project): Response
    {
        $project = $this->projects->findOwned($request->user(), $project->id);

        return Inertia::render('Projects/Show', [
            'project' => $this->transform($project),
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        $project = $this->projects->findOwned($request->user(), $project->id);

        return Inertia::render('Projects/Form', [
            'project' => $this->transform($project),
            'projectTypes' => ProjectTypes::OPTIONS,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $owned = $this->projects->findOwned($request->user(), $project->id);
        $this->projects->update($request->user(), $owned, $request->validated());

        return redirect()->route('projects.index')->with('status', 'project-updated');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $owned = $this->projects->findOwned($request->user(), $project->id);
        $this->projects->delete($request->user(), $owned);

        return redirect()->route('projects.index')->with('status', 'project-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'short_description' => $project->short_description,
            'detailed_description' => $project->detailed_description,
            'project_type' => $project->project_type,
            'github_url' => $project->github_url,
            'live_url' => $project->live_url,
            'start_date' => optional($project->start_date)?->format('Y-m-d'),
            'end_date' => optional($project->end_date)?->format('Y-m-d'),
            'is_ongoing' => $project->is_ongoing,
            'is_active' => $project->is_active,
            'include_in_resume' => $project->include_in_resume,
            'technologies' => $project->technologies->pluck('name')->values(),
            'features' => $project->features->pluck('description')->values(),
            'responsibilities' => $project->responsibilities->pluck('description')->values(),
        ];
    }
}
