<?php

namespace App\Http\Requests\ResumeImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitResumeImportRequest extends FormRequest
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
            'merge_strategy' => ['required', Rule::in(['new_profile', 'merge'])],
            'profile_id' => [
                'nullable',
                'integer',
                Rule::exists('profiles', 'id')->where(fn ($q) => $q->where('user_id', $this->user()?->id)),
            ],
            'new_profile_name' => ['nullable', 'string', 'max:120'],
            'review_payload' => ['nullable', 'array'],
        ];
    }
}
