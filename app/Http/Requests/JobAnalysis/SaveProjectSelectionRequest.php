<?php

namespace App\Http\Requests\JobAnalysis;

use Illuminate\Foundation\Http\FormRequest;

class SaveProjectSelectionRequest extends FormRequest
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
        $max = (int) config('ai.project_ranking.max_selected_projects', 5);

        return [
            'project_ids' => ['present', 'array', 'max:'.$max],
            'project_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_ids.max' => 'Too many projects selected for this resume context.',
            'project_ids.*.distinct' => 'Duplicate project selections are not allowed.',
        ];
    }
}
