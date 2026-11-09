<?php

namespace App\Http\Controllers;

use App\Http\Requests\Skills\StoreSkillRequest;
use App\Http\Requests\Skills\UpdateSkillRequest;
use App\Services\Profile\SkillManagerService;
use App\Support\SkillCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillManagerService $skills,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'category', 'status']);
        $paginator = $this->skills->listForUser($request->user(), $filters);

        $skills = $paginator->through(fn ($skill) => [
            'id' => $skill->id,
            'name' => $skill->name,
            'category' => $skill->category,
            'proficiency' => $skill->pivot->proficiency,
            'years_experience' => $skill->pivot->years_experience,
            'last_used_year' => $skill->pivot->last_used_year,
            'is_primary' => (bool) $skill->pivot->is_primary,
            'is_active' => (bool) $skill->pivot->is_active,
        ]);

        return Inertia::render('Skills/Index', [
            'skills' => $skills,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'categories' => SkillCategories::OPTIONS,
            'status' => session('status'),
        ]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        $this->skills->add($request->user(), $request->validated());

        return redirect()->route('skills.index')->with('status', 'skill-created');
    }

    public function update(UpdateSkillRequest $request, int $skill): RedirectResponse
    {
        $this->skills->update($request->user(), $skill, $request->validated());

        return redirect()->route('skills.index')->with('status', 'skill-updated');
    }

    public function destroy(Request $request, int $skill): RedirectResponse
    {
        $this->skills->delete($request->user(), $skill);

        return redirect()->route('skills.index')->with('status', 'skill-deleted');
    }

    public function toggle(Request $request, int $skill): RedirectResponse
    {
        $this->skills->toggleActive($request->user(), $skill);

        return redirect()->route('skills.index')->with('status', 'skill-toggled');
    }
}
