<?php

namespace App\Http\Controllers;

use App\Http\Requests\Education\StoreEducationRequest;
use App\Http\Requests\Education\UpdateEducationRequest;
use App\Models\Education;
use App\Services\Profile\EducationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EducationController extends Controller
{
    public function __construct(
        private readonly EducationService $educations,
    ) {}

    public function index(Request $request): Response
    {
        $items = $this->educations->listForUser($request->user())
            ->map(fn (Education $item) => [
                'id' => $item->id,
                'institution_name' => $item->institution_name,
                'degree' => $item->degree,
                'field_of_study' => $item->field_of_study,
                'start_year' => $item->start_year,
                'end_year' => $item->end_year,
                'grade' => $item->grade,
                'description' => $item->description,
            ]);

        return Inertia::render('Education/Index', [
            'educations' => $items,
            'status' => session('status'),
        ]);
    }

    public function store(StoreEducationRequest $request): RedirectResponse
    {
        $this->educations->create($request->user(), $request->validated());

        return redirect()->route('education.index')->with('status', 'education-created');
    }

    public function update(UpdateEducationRequest $request, Education $education): RedirectResponse
    {
        $owned = $this->educations->findOwned($request->user(), $education->id);
        $this->educations->update($request->user(), $owned, $request->validated());

        return redirect()->route('education.index')->with('status', 'education-updated');
    }

    public function destroy(Request $request, Education $education): RedirectResponse
    {
        $owned = $this->educations->findOwned($request->user(), $education->id);
        $this->educations->delete($request->user(), $owned);

        return redirect()->route('education.index')->with('status', 'education-deleted');
    }
}
