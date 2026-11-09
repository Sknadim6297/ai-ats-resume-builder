<?php

namespace App\Http\Requests\Experience;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkExperienceRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', 'string', Rule::in([
                'Full-time',
                'Part-time',
                'Contract',
                'Freelance',
                'Internship',
            ])],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_unless:is_current,true,1'],
            'is_current' => ['sometimes', 'boolean'],
            'responsibilities' => ['nullable', 'array'],
            'responsibilities.*' => ['required', 'string', 'max:500'],
            'achievements' => ['nullable', 'array'],
            'achievements.*' => ['required', 'string', 'max:500'],
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['required', 'string', 'max:100'],
        ];
    }
}
