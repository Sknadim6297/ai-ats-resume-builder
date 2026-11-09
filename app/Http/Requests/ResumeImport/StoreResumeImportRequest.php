<?php

namespace App\Http\Requests\ResumeImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreResumeImportRequest extends FormRequest
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
            'resume' => [
                'required',
                File::types(['pdf', 'docx'])
                    ->max(5 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resume.required' => 'Please choose a PDF or DOCX resume file.',
            'resume.max' => 'Resume file must not exceed 5 MB.',
        ];
    }
}
