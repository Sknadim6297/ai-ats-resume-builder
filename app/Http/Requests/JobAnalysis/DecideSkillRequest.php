<?php

namespace App\Http\Requests\JobAnalysis;

use App\Models\JobAnalysisSkillDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideSkillRequest extends FormRequest
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
        return [
            'skill_name' => ['required', 'string', 'max:120'],
            'requirement_type' => ['required', 'string', Rule::in(JobAnalysisSkillDecision::REQUIREMENT_TYPES)],
            'decision' => ['required', 'string', Rule::in(JobAnalysisSkillDecision::DECISIONS)],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Please confirm that you genuinely possess this skill before approving it.',
            'decision.in' => 'Invalid skill decision.',
            'requirement_type.in' => 'Invalid requirement type.',
        ];
    }
}
