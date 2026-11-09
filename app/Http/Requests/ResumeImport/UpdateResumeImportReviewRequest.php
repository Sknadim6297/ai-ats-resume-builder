<?php

namespace App\Http\Requests\ResumeImport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResumeImportReviewRequest extends FormRequest
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
            'review_payload' => ['required', 'array'],
        ];
    }
}
