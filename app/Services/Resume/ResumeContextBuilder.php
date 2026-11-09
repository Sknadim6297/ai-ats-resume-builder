<?php

namespace App\Services\Resume;

use App\Models\JobAnalysis;
use App\Models\User;
use App\Services\AI\JobAnalysis\ResumeSkillContextService;
use App\Services\AI\JobAnalysis\SkillNormalizer;
use App\Services\Projects\ResumeProjectContextService;
use Illuminate\Validation\ValidationException;

/**
 * Assembles a grounded, serializable context for resume drafting.
 * Never includes ignored/pending skills or unselected projects.
 */
class ResumeContextBuilder
{
    public function __construct(
        private readonly ResumeSkillContextService $skills,
        private readonly ResumeProjectContextService $projects,
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     contact: array<string, mixed>,
     *     professional_summary_source: string|null,
     *     years_of_experience: int|null,
     *     approved_skills: list<string>,
     *     skill_sources: array{master_profile: list<string>, resume_only: list<string>},
     *     experiences: list<array<string, mixed>>,
     *     projects: list<array<string, mixed>>,
     *     education: list<array<string, mixed>>,
     *     job_target: array<string, mixed>,
     *     excluded: array{ignored_skills: list<string>, pending_skills: list<string>}
     * }
     */
    public function build(User $user, JobAnalysis $analysis): array
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        if (! $analysis->isCompleted() || ! is_array($analysis->analysis_json)) {
            throw ValidationException::withMessages([
                'resume' => 'Resume drafting requires a completed job analysis.',
            ]);
        }

        $profile = $analysis->relationLoaded('profile')
            ? $analysis->profile
            : $analysis->profile()->first();
        $profile ??= $user->profile()->first();

        if (! $profile || $profile->user_id !== $user->id || blank($profile->full_name) || blank($profile->email)) {
            throw ValidationException::withMessages([
                'resume' => 'Complete the selected professional profile contact name and email before generating a resume.',
            ]);
        }

        $skillContext = $this->skills->forAnalysis($user, $analysis);
        $projectContext = $this->projects->forAnalysis($user, $analysis);

        $blockedKeys = [];
        foreach (array_merge($skillContext['ignored_skills'], $skillContext['pending_skills']) as $skill) {
            $blockedKeys[$this->normalizer->comparisonKey($skill)] = true;
        }

        $approvedSkills = [];
        foreach ($this->normalizer->normalizeList(array_merge(
            $skillContext['verified_master_profile_skills'],
            $skillContext['approved_resume_only_skills'],
        )) as $skill) {
            $key = $this->normalizer->comparisonKey($skill);
            if (isset($blockedKeys[$key])) {
                continue;
            }
            $approvedSkills[] = $skill;
        }
        $approvedSkills = array_values(array_unique($approvedSkills));

        if ($approvedSkills === [] && $projectContext['projects'] === []) {
            throw ValidationException::withMessages([
                'resume' => 'Add Master Profile skills or select eligible projects before generating a resume.',
            ]);
        }

        $experiences = $user->workExperiences()
            ->where('profile_id', $profile->id)
            ->with('technologies')
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($exp) => [
                'source_id' => $exp->id,
                'company_name' => $exp->company_name,
                'job_title' => $exp->job_title,
                'employment_type' => $exp->employment_type,
                'location' => $exp->location,
                'start_date' => optional($exp->start_date)?->format('Y-m-d'),
                'end_date' => $exp->is_current ? null : optional($exp->end_date)?->format('Y-m-d'),
                'is_current' => (bool) $exp->is_current,
                'responsibilities' => array_values(array_filter($exp->responsibilities ?? [])),
                'achievements' => array_values(array_filter($exp->achievements ?? [])),
                'technologies' => $exp->technologies->pluck('name')->values()->all(),
            ])
            ->all();

        $education = $user->educations()
            ->where('profile_id', $profile->id)
            ->orderByDesc('start_year')
            ->orderByDesc('end_year')
            ->get()
            ->map(fn ($edu) => [
                'source_id' => $edu->id,
                'institution_name' => $edu->institution_name,
                'degree' => $edu->degree,
                'field_of_study' => $edu->field_of_study,
                'start_year' => $edu->start_year,
                'end_year' => $edu->end_year,
                'grade' => $edu->grade,
                'description' => $edu->description,
            ])
            ->all();

        $projects = array_map(fn (array $project) => [
            'source_id' => $project['id'],
            'name' => $project['name'],
            'project_type' => $project['project_type'],
            'short_description' => $project['short_description'],
            'detailed_description' => $project['detailed_description'],
            'technologies' => $project['technologies'] ?? [],
            'features' => $project['features'] ?? [],
            'responsibilities' => $project['responsibilities'] ?? [],
        ], $projectContext['projects']);

        $analysisJson = $analysis->analysis_json;

        return [
            'contact' => [
                'full_name' => $profile->full_name,
                'headline' => $profile->headline,
                'email' => $profile->email,
                'phone' => $profile->phone,
                'location' => $profile->location,
                'linkedin_url' => $profile->linkedin_url,
                'github_url' => $profile->github_url,
                'portfolio_url' => $profile->portfolio_url,
            ],
            'professional_summary_source' => $profile->professional_summary,
            'years_of_experience' => $profile->years_of_experience,
            'approved_skills' => $approvedSkills,
            'skill_sources' => [
                'master_profile' => $skillContext['verified_master_profile_skills'],
                'resume_only' => $skillContext['approved_resume_only_skills'],
            ],
            'experiences' => $experiences,
            'projects' => $projects,
            'education' => $education,
            'job_target' => [
                'job_title' => $analysisJson['job_title'] ?? $analysis->job_title,
                'job_category' => $analysisJson['job_category'] ?? null,
                'company_name' => $analysis->company_name,
                'required_skills' => $skillContext['original_required_skills'],
                'preferred_skills' => $skillContext['original_preferred_skills'],
                'technical_keywords' => $this->normalizer->normalizeList($analysisJson['technical_keywords'] ?? []),
            ],
            'excluded' => [
                'ignored_skills' => $skillContext['ignored_skills'],
                'pending_skills' => $skillContext['pending_skills'],
            ],
        ];
    }
}
