<?php

namespace App\Services\AI\JobAnalysis;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class JobAnalysisSchema
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $normalized = [
            'job_title' => $this->nullableString($payload['job_title'] ?? null, 255),
            'job_category' => $this->nullableString($payload['job_category'] ?? null, 120),
            'required_skills' => $this->stringList($payload['required_skills'] ?? []),
            'preferred_skills' => $this->stringList($payload['preferred_skills'] ?? []),
            'frameworks_libraries' => $this->stringList($payload['frameworks_libraries'] ?? []),
            'databases' => $this->stringList($payload['databases'] ?? []),
            'tools_platforms' => $this->stringList($payload['tools_platforms'] ?? []),
            'experience' => [
                'minimum_years' => $this->nullableInt(Arr::get($payload, 'experience.minimum_years'), 0, 60),
                'maximum_years' => $this->nullableInt(Arr::get($payload, 'experience.maximum_years'), 0, 60),
                'summary' => $this->nullableString(Arr::get($payload, 'experience.summary'), 500),
            ],
            'education_requirements' => $this->stringList($payload['education_requirements'] ?? [], 20, 255),
            'responsibilities' => $this->stringList($payload['responsibilities'] ?? [], 40, 500),
            'technical_keywords' => $this->stringList($payload['technical_keywords'] ?? [], 60, 100),
            'work_arrangement' => $this->nullableString($payload['work_arrangement'] ?? null, 100),
            'location' => $this->nullableString($payload['location'] ?? null, 255),
            'notes' => $this->nullableString($payload['notes'] ?? null, 1000),
        ];

        $min = $normalized['experience']['minimum_years'];
        $max = $normalized['experience']['maximum_years'];

        if ($min !== null && $max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'analysis' => 'AI experience range was inconsistent and was rejected.',
            ]);
        }

        // Keep required/preferred disjoint when exact duplicates appear.
        $requiredLookup = array_fill_keys(array_map('mb_strtolower', $normalized['required_skills']), true);
        $normalized['preferred_skills'] = array_values(array_filter(
            $normalized['preferred_skills'],
            fn (string $skill) => ! isset($requiredLookup[mb_strtolower($skill)])
        ));

        return $normalized;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value)) {
            throw ValidationException::withMessages([
                'analysis' => 'AI response contained an invalid string field.',
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
            throw ValidationException::withMessages([
                'analysis' => 'AI response contained an invalid numeric field.',
            ]);
        }

        $int = (int) $value;

        return max($min, min($max, $int));
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value, int $maxItems = 40, int $maxLength = 120): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'analysis' => 'AI response contained an invalid list field.',
            ]);
        }

        $items = [];
        foreach (array_slice($value, 0, $maxItems) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $items[] = mb_substr($text, 0, $maxLength);
        }

        return array_values(array_unique($items));
    }
}
