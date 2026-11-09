<?php

namespace App\Http\Requests\Skills;

use App\Support\SkillCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', Rule::in(SkillCategories::OPTIONS)],
            'proficiency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'last_used_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
