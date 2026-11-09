<?php

namespace App\Http\Requests\JobAnalysis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeJobRequest extends FormRequest
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
        $min = (int) config('ai.job_analysis.min_description_length', 80);
        $max = (int) config('ai.job_analysis.max_description_length', 20000);

        return [
            'profile_id' => [
                'nullable',
                'integer',
                Rule::exists('profiles', 'id')->where(fn ($q) => $q
                    ->where('user_id', $this->user()?->id)
                    ->whereNull('archived_at')),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'job_url' => ['nullable', 'url', 'max:255'],
            'job_description' => ['required', 'string', 'min:'.$min, 'max:'.$max],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'job_description.min' => 'Please paste a fuller job description so analysis can be useful.',
            'job_description.max' => 'Job description is too long.',
        ];
    }
}
