<?php

namespace App\Http\Requests\Resume;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResumeDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSummary = (int) config('ai.resume_drafting.max_summary_chars', 700);
        $maxBullet = (int) config('ai.resume_drafting.max_bullet_chars', 280);
        $maxSkills = (int) config('ai.resume_drafting.max_skills', 40);
        $maxExpBullets = (int) config('ai.resume_drafting.max_bullets_per_experience', 8);
        $maxProjBullets = (int) config('ai.resume_drafting.max_bullets_per_project', 6);

        return [
            'contact' => ['required', 'array'],
            'contact.full_name' => ['required', 'string', 'max:120'],
            'contact.headline' => ['nullable', 'string', 'max:160'],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'contact.location' => ['nullable', 'string', 'max:120'],
            'contact.linkedin_url' => ['nullable', 'string', 'max:255'],
            'contact.github_url' => ['nullable', 'string', 'max:255'],
            'contact.portfolio_url' => ['nullable', 'string', 'max:255'],
            'professional_summary' => ['required', 'string', 'max:'.$maxSummary],
            'technical_skills' => ['required', 'array', 'max:'.$maxSkills],
            'technical_skills.*' => ['string', 'max:80'],
            'experience' => ['present', 'array', 'max:20'],
            'experience.*.source_id' => ['required', 'integer', 'min:1'],
            'experience.*.company_name' => ['required', 'string', 'max:160'],
            'experience.*.job_title' => ['required', 'string', 'max:160'],
            'experience.*.employment_type' => ['nullable', 'string', 'max:80'],
            'experience.*.location' => ['nullable', 'string', 'max:120'],
            'experience.*.start_date' => ['nullable', 'string', 'max:20'],
            'experience.*.end_date' => ['nullable', 'string', 'max:20'],
            'experience.*.is_current' => ['sometimes', 'boolean'],
            'experience.*.bullets' => ['present', 'array', 'max:'.$maxExpBullets],
            'experience.*.bullets.*' => ['string', 'max:'.$maxBullet],
            'projects' => ['present', 'array', 'max:10'],
            'projects.*.source_id' => ['required', 'integer', 'min:1'],
            'projects.*.name' => ['required', 'string', 'max:160'],
            'projects.*.project_type' => ['nullable', 'string', 'max:80'],
            'projects.*.technologies' => ['present', 'array', 'max:30'],
            'projects.*.technologies.*' => ['string', 'max:80'],
            'projects.*.bullets' => ['present', 'array', 'max:'.$maxProjBullets],
            'projects.*.bullets.*' => ['string', 'max:'.$maxBullet],
            'education' => ['present', 'array', 'max:15'],
            'education.*.source_id' => ['required', 'integer', 'min:1'],
            'education.*.institution_name' => ['required', 'string', 'max:160'],
            'education.*.degree' => ['required', 'string', 'max:160'],
            'education.*.field_of_study' => ['nullable', 'string', 'max:160'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'education.*.grade' => ['nullable', 'string', 'max:80'],
        ];
    }
}
