<?php

namespace App\Services\ResumeImport;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ResumeImportSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $personal = Arr::get($payload, 'personal');
        if (! is_array($personal)) {
            $personal = Arr::get($payload, 'contact', []);
        }
        if (! is_array($personal)) {
            $personal = [];
        }

        $summary = $personal['professional_summary'] ?? Arr::get($payload, 'professional_summary');

        $experiences = Arr::get($payload, 'work_experiences');
        if (! is_array($experiences)) {
            $experiences = Arr::get($payload, 'experience', []);
        }
        if (! is_array($experiences)) {
            $experiences = [];
        }

        return [
            'personal' => [
                'full_name' => $this->nullableString($personal['full_name'] ?? null, 255),
                'headline' => $this->nullableString($personal['headline'] ?? null, 255),
                'email' => $this->nullableString($personal['email'] ?? null, 255),
                'phone' => $this->nullableString($personal['phone'] ?? null, 50),
                'location' => $this->nullableString($personal['location'] ?? null, 255),
                'linkedin_url' => $this->nullableString($personal['linkedin_url'] ?? null, 255),
                'github_url' => $this->nullableString($personal['github_url'] ?? null, 255),
                'portfolio_url' => $this->nullableString($personal['portfolio_url'] ?? null, 255),
                'professional_summary' => $this->nullableString($summary, 5000),
                'years_of_experience' => $this->nullableInt($personal['years_of_experience'] ?? null, 0, 60),
                'additional_links' => $this->linkList($personal['additional_links'] ?? []),
            ],
            'skills' => $this->stringList($payload['skills'] ?? [], 80, 120),
            'work_experiences' => $this->workExperiences($experiences),
            'projects' => $this->projects($payload['projects'] ?? []),
            'education' => $this->educationList($payload['education'] ?? []),
            'certifications' => $this->stringList($payload['certifications'] ?? [], 30, 255),
            'uncertain_fields' => $this->stringList($payload['uncertain_fields'] ?? [], 40, 120),
        ];
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function workExperiences(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 25) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $company = $this->nullableString($row['company_name'] ?? null, 255);
            $title = $this->nullableString($row['job_title'] ?? null, 255);
            if ($company === null && $title === null) {
                continue;
            }
            $out[] = [
                'company_name' => $company ?? '',
                'job_title' => $title ?? '',
                'employment_type' => $this->nullableString($row['employment_type'] ?? null, 80),
                'location' => $this->nullableString($row['location'] ?? null, 255),
                'start_date' => $this->nullableString($row['start_date'] ?? null, 40),
                'end_date' => $this->nullableString($row['end_date'] ?? null, 40),
                'is_current' => (bool) ($row['is_current'] ?? false),
                'responsibilities' => $this->stringList($row['responsibilities'] ?? [], 20, 500),
                'achievements' => $this->stringList($row['achievements'] ?? [], 20, 500),
                'technologies' => $this->stringList($row['technologies'] ?? [], 30, 100),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function projects(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 25) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = $this->nullableString($row['name'] ?? null, 255);
            if ($name === null) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'short_description' => $this->nullableString($row['short_description'] ?? null, 500),
                'detailed_description' => $this->nullableString($row['detailed_description'] ?? null, 5000),
                'project_type' => $this->nullableString($row['project_type'] ?? null, 120),
                'github_url' => $this->nullableString($row['github_url'] ?? null, 255),
                'live_url' => $this->nullableString($row['live_url'] ?? null, 255),
                'technologies' => $this->stringList($row['technologies'] ?? [], 30, 100),
                'features' => $this->stringList($row['features'] ?? [], 20, 500),
                'responsibilities' => $this->stringList($row['responsibilities'] ?? [], 20, 500),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function educationList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $institution = $this->nullableString($row['institution_name'] ?? null, 255);
            if ($institution === null) {
                continue;
            }
            $out[] = [
                'institution_name' => $institution,
                'degree' => $this->nullableString($row['degree'] ?? null, 255),
                'field_of_study' => $this->nullableString($row['field_of_study'] ?? null, 255),
                'start_year' => $this->nullableInt($row['start_year'] ?? null, 1950, 2100),
                'end_year' => $this->nullableInt($row['end_year'] ?? null, 1950, 2100),
                'grade' => $this->nullableString($row['grade'] ?? null, 100),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $items
     * @return list<array{label:?string,url:?string}>
     */
    private function linkList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_slice($items, 0, 10) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = $this->nullableString($row['label'] ?? null, 100);
            $url = $this->nullableString($row['url'] ?? null, 255);
            if ($label === null && $url === null) {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url];
        }

        return $out;
    }

    /**
     * @param  mixed  $list
     * @return list<string>
     */
    private function stringList(mixed $list, int $maxItems, int $maxLen): array
    {
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $out[] = mb_substr($value, 0, $maxLen);
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value)) {
            throw ValidationException::withMessages([
                'resume' => 'AI response contained an invalid string field.',
            ]);
        }

        $string = trim((string) $value);

        return $string === '' ? null : mb_substr($string, 0, $max);
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return max($min, min($max, $int));
    }
}
