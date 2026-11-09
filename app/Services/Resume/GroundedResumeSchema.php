<?php

namespace App\Services\Resume;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class GroundedResumeSchema
{
    public const VERSION = '1.0';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $maxSummary = (int) config('ai.resume_drafting.max_summary_chars', 700);
        $maxBullet = (int) config('ai.resume_drafting.max_bullet_chars', 280);
        $maxExpBullets = (int) config('ai.resume_drafting.max_bullets_per_experience', 8);
        $maxProjBullets = (int) config('ai.resume_drafting.max_bullets_per_project', 6);
        $maxSkills = (int) config('ai.resume_drafting.max_skills', 40);

        $contact = Arr::get($payload, 'contact');
        if (! is_array($contact)) {
            throw ValidationException::withMessages([
                'resume' => 'AI resume response was missing contact data.',
            ]);
        }

        $normalized = [
            'schema_version' => self::VERSION,
            'contact' => [
                'full_name' => $this->requiredString($contact['full_name'] ?? null, 120, 'contact.full_name'),
                'headline' => $this->nullableString($contact['headline'] ?? null, 160),
                'email' => $this->requiredString($contact['email'] ?? null, 255, 'contact.email'),
                'phone' => $this->nullableString($contact['phone'] ?? null, 40),
                'location' => $this->nullableString($contact['location'] ?? null, 120),
                'linkedin_url' => $this->nullableString($contact['linkedin_url'] ?? null, 255),
                'github_url' => $this->nullableString($contact['github_url'] ?? null, 255),
                'portfolio_url' => $this->nullableString($contact['portfolio_url'] ?? null, 255),
            ],
            'professional_summary' => $this->requiredString(
                $payload['professional_summary'] ?? null,
                $maxSummary,
                'professional_summary'
            ),
            'technical_skills' => $this->stringList($payload['technical_skills'] ?? [], $maxSkills, 80),
            'experience' => [],
            'projects' => [],
            'education' => [],
        ];

        foreach (array_slice(Arr::wrap($payload['experience'] ?? []), 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized['experience'][] = [
                'source_id' => $this->requiredInt($row['source_id'] ?? null, 'experience.source_id'),
                'company_name' => $this->requiredString($row['company_name'] ?? null, 160, 'experience.company_name'),
                'job_title' => $this->requiredString($row['job_title'] ?? null, 160, 'experience.job_title'),
                'employment_type' => $this->nullableString($row['employment_type'] ?? null, 80),
                'location' => $this->nullableString($row['location'] ?? null, 120),
                'start_date' => $this->nullableString($row['start_date'] ?? null, 20),
                'end_date' => $this->nullableString($row['end_date'] ?? null, 20),
                'is_current' => (bool) ($row['is_current'] ?? false),
                'bullets' => $this->stringList($row['bullets'] ?? [], $maxExpBullets, $maxBullet),
            ];
        }

        foreach (array_slice(Arr::wrap($payload['projects'] ?? []), 0, 10) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized['projects'][] = [
                'source_id' => $this->requiredInt($row['source_id'] ?? null, 'projects.source_id'),
                'name' => $this->requiredString($row['name'] ?? null, 160, 'projects.name'),
                'project_type' => $this->nullableString($row['project_type'] ?? null, 80),
                'technologies' => $this->stringList($row['technologies'] ?? [], 30, 80),
                'bullets' => $this->stringList($row['bullets'] ?? [], $maxProjBullets, $maxBullet),
            ];
        }

        foreach (array_slice(Arr::wrap($payload['education'] ?? []), 0, 15) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized['education'][] = [
                'source_id' => $this->requiredInt($row['source_id'] ?? null, 'education.source_id'),
                'institution_name' => $this->requiredString($row['institution_name'] ?? null, 160, 'education.institution_name'),
                'degree' => $this->requiredString($row['degree'] ?? null, 160, 'education.degree'),
                'field_of_study' => $this->nullableString($row['field_of_study'] ?? null, 160),
                'start_year' => $this->nullableInt($row['start_year'] ?? null),
                'end_year' => $this->nullableInt($row['end_year'] ?? null),
                'grade' => $this->nullableString($row['grade'] ?? null, 80),
            ];
        }

        return $normalized;
    }

    private function requiredString(mixed $value, int $max, string $field): string
    {
        $string = $this->nullableString($value, $max);
        if ($string === null || $string === '') {
            throw ValidationException::withMessages([
                'resume' => "AI resume response was missing required field: {$field}.",
            ]);
        }

        return $string;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_scalar($value)) {
            throw ValidationException::withMessages([
                'resume' => 'AI resume response contained an invalid string field.',
            ]);
        }

        // Strip markup so AI/profile HTML cannot reach preview/PDF/DOCX as tags.
        $string = $this->plainText((string) $value);

        return $string === '' ? null : mb_substr($string, 0, $max);
    }

    private function plainText(string $value): string
    {
        $withoutBlocks = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value) ?? $value;

        return trim(html_entity_decode(strip_tags($withoutBlocks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'resume' => "AI resume response was missing required id: {$field}.",
            ]);
        }

        return (int) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, int $maxItems, int $maxLength): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'resume' => 'AI resume response contained an invalid list field.',
            ]);
        }

        $items = [];
        foreach (array_slice($value, 0, $maxItems) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $text = $this->plainText((string) $item);
            if ($text === '') {
                continue;
            }
            $items[] = mb_substr($text, 0, $maxLength);
        }

        return array_values(array_unique($items));
    }
}
