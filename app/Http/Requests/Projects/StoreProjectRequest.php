<?php

namespace App\Http\Requests\Projects;

use App\Support\ProjectTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
        $userId = $this->user()->id;
        $projectId = $this->route('project')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($projectId),
            ],
            'short_description' => ['required', 'string', 'max:500'],
            'detailed_description' => ['nullable', 'string', 'max:10000'],
            'project_type' => ['required', 'string', Rule::in(ProjectTypes::OPTIONS)],
            'github_url' => ['nullable', 'url', 'max:255'],
            'live_url' => ['nullable', 'url', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_ongoing' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'include_in_resume' => ['sometimes', 'boolean'],
            'technologies' => ['required', 'array', 'min:1'],
            'technologies.*' => ['required', 'string', 'max:100'],
            'features' => ['nullable', 'array'],
            'features.*' => ['required', 'string', 'max:500'],
            'responsibilities' => ['nullable', 'array'],
            'responsibilities.*' => ['required', 'string', 'max:500'],
        ];
    }
}
