<?php

namespace App\Services\Resume;

use App\Services\AI\JobAnalysis\SkillNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * Conservative grounding: immutable facts must match source snapshot.
 * Rewritten bullets may not introduce unsupported numeric claims.
 *
 * Limitation: automated checks cannot fully prove semantic truth of rewritten
 * prose; they enforce structural provenance and conservative numeric hygiene.
 */
class ResumeGroundingValidator
{
    public function __construct(
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function validate(array $draft, array $snapshot): array
    {
        $draft['contact'] = $this->groundContact($draft['contact'] ?? [], $snapshot['contact'] ?? []);
        $draft['technical_skills'] = $this->groundSkills(
            $draft['technical_skills'] ?? [],
            $snapshot['approved_skills'] ?? [],
            $snapshot['excluded']['ignored_skills'] ?? [],
            $snapshot['excluded']['pending_skills'] ?? [],
        );
        $draft['professional_summary'] = $this->groundSummary(
            (string) ($draft['professional_summary'] ?? ''),
            $snapshot
        );
        $draft['experience'] = $this->groundExperience(
            $draft['experience'] ?? [],
            $snapshot['experiences'] ?? []
        );
        $draft['projects'] = $this->groundProjects(
            $draft['projects'] ?? [],
            $snapshot['projects'] ?? []
        );
        $draft['education'] = $this->groundEducation(
            $draft['education'] ?? [],
            $snapshot['education'] ?? []
        );

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function groundContact(array $contact, array $source): array
    {
        foreach (['full_name', 'email', 'phone', 'location', 'linkedin_url', 'github_url', 'portfolio_url', 'headline'] as $field) {
            $contact[$field] = $source[$field] ?? null;
        }

        if (blank($contact['full_name']) || blank($contact['email'])) {
            throw ValidationException::withMessages([
                'resume' => 'Contact information could not be grounded to the Master Profile.',
            ]);
        }

        return $contact;
    }

    /**
     * @param  list<string>  $skills
     * @param  list<string>  $approved
     * @param  list<string>  $ignored
     * @param  list<string>  $pending
     * @return list<string>
     */
    private function groundSkills(array $skills, array $approved, array $ignored, array $pending): array
    {
        $approvedKeys = [];
        foreach ($this->normalizer->normalizeList($approved) as $skill) {
            $approvedKeys[$this->normalizer->comparisonKey($skill)] = $skill;
        }

        $blocked = [];
        foreach (array_merge($ignored, $pending) as $skill) {
            $blocked[$this->normalizer->comparisonKey($skill)] = true;
        }

        $grounded = [];
        foreach ($this->normalizer->normalizeList($skills) as $skill) {
            $key = $this->normalizer->comparisonKey($skill);
            if (isset($blocked[$key])) {
                continue;
            }
            if (! isset($approvedKeys[$key])) {
                continue;
            }
            $grounded[$key] = $approvedKeys[$key];
        }

        // Prefer AI order when valid; append any remaining approved skills not chosen.
        foreach ($approvedKeys as $key => $skill) {
            if (! isset($grounded[$key])) {
                $grounded[$key] = $skill;
            }
        }

        $max = (int) config('ai.resume_drafting.max_skills', 40);

        return array_slice(array_values($grounded), 0, $max);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function groundSummary(string $summary, array $snapshot): string
    {
        $summary = trim($summary);
        if ($summary === '') {
            $summary = (string) ($snapshot['professional_summary_source'] ?? '');
        }
        if ($summary === '') {
            $name = $snapshot['contact']['full_name'] ?? 'Candidate';
            $headline = $snapshot['contact']['headline'] ?? 'software developer';
            $summary = "{$name} is a {$headline} with experience aligned to the target role.";
        }

        $max = (int) config('ai.resume_drafting.max_summary_chars', 700);
        $summary = mb_substr($summary, 0, $max);

        if ($this->introducesUnsupportedNumbers($summary, $this->allowedNumberCorpus($snapshot))) {
            $fallback = (string) ($snapshot['professional_summary_source'] ?? '');
            return $fallback !== '' ? mb_substr($fallback, 0, $max) : mb_substr(preg_replace('/\d[\d,]*(?:\.\d+)?%?/u', '', $summary) ?? $summary, 0, $max);
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function groundExperience(array $rows, array $sources): array
    {
        $byId = collect($sources)->keyBy('source_id');
        $grounded = [];

        foreach ($rows as $row) {
            $source = $byId->get((int) ($row['source_id'] ?? 0));
            if (! $source) {
                continue;
            }

            $corpus = $this->allowedNumberCorpus([
                'experiences' => [$source],
                'projects' => [],
                'professional_summary_source' => null,
                'years_of_experience' => null,
                'contact' => [],
            ]);

            $bullets = [];
            foreach ($row['bullets'] ?? [] as $bullet) {
                $text = trim((string) $bullet);
                if ($text === '') {
                    continue;
                }
                if ($this->introducesUnsupportedNumbers($text, $corpus)) {
                    continue;
                }
                $bullets[] = $text;
            }

            if ($bullets === []) {
                $bullets = array_values(array_filter(array_merge(
                    $source['responsibilities'] ?? [],
                    $source['achievements'] ?? []
                )));
            }

            $grounded[] = [
                'source_id' => $source['source_id'],
                'company_name' => $source['company_name'],
                'job_title' => $source['job_title'],
                'employment_type' => $source['employment_type'],
                'location' => $source['location'],
                'start_date' => $source['start_date'],
                'end_date' => $source['end_date'],
                'is_current' => (bool) $source['is_current'],
                'bullets' => array_slice($bullets, 0, (int) config('ai.resume_drafting.max_bullets_per_experience', 8)),
            ];
        }

        // Preserve source order for any experiences the model omitted.
        if ($grounded === [] && $sources !== []) {
            foreach ($sources as $source) {
                $grounded[] = [
                    'source_id' => $source['source_id'],
                    'company_name' => $source['company_name'],
                    'job_title' => $source['job_title'],
                    'employment_type' => $source['employment_type'],
                    'location' => $source['location'],
                    'start_date' => $source['start_date'],
                    'end_date' => $source['end_date'],
                    'is_current' => (bool) $source['is_current'],
                    'bullets' => array_values(array_filter(array_merge(
                        $source['responsibilities'] ?? [],
                        $source['achievements'] ?? []
                    ))),
                ];
            }
        }

        return $grounded;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function groundProjects(array $rows, array $sources): array
    {
        $byId = collect($sources)->keyBy('source_id');
        $ordered = [];

        // Keep selected project order from snapshot.
        foreach ($sources as $source) {
            $ai = collect($rows)->firstWhere('source_id', $source['source_id']) ?? [];
            $corpus = $this->textCorpus(array_merge(
                $source['features'] ?? [],
                $source['responsibilities'] ?? [],
                [$source['short_description'] ?? '', $source['detailed_description'] ?? '']
            ));

            $techKeys = [];
            foreach ($this->normalizer->normalizeList($source['technologies'] ?? []) as $tech) {
                $techKeys[$this->normalizer->comparisonKey($tech)] = $tech;
            }

            $technologies = [];
            foreach ($this->normalizer->normalizeList($ai['technologies'] ?? $source['technologies'] ?? []) as $tech) {
                $key = $this->normalizer->comparisonKey($tech);
                if (isset($techKeys[$key])) {
                    $technologies[] = $techKeys[$key];
                }
            }
            if ($technologies === []) {
                $technologies = array_values($techKeys);
            }

            $bullets = [];
            foreach ($ai['bullets'] ?? [] as $bullet) {
                $text = trim((string) $bullet);
                if ($text === '' || $this->introducesUnsupportedNumbers($text, $corpus)) {
                    continue;
                }
                $bullets[] = $text;
            }
            if ($bullets === []) {
                $bullets = array_values(array_filter(array_merge(
                    $source['features'] ?? [],
                    $source['responsibilities'] ?? []
                )));
            }

            $ordered[] = [
                'source_id' => $source['source_id'],
                'name' => $source['name'],
                'project_type' => $source['project_type'],
                'technologies' => $technologies,
                'bullets' => array_slice($bullets, 0, (int) config('ai.resume_drafting.max_bullets_per_project', 6)),
            ];
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function groundEducation(array $rows, array $sources): array
    {
        $byId = collect($sources)->keyBy('source_id');
        $grounded = [];

        foreach ($rows as $row) {
            $source = $byId->get((int) ($row['source_id'] ?? 0));
            if (! $source) {
                continue;
            }
            $grounded[] = [
                'source_id' => $source['source_id'],
                'institution_name' => $source['institution_name'],
                'degree' => $source['degree'],
                'field_of_study' => $source['field_of_study'],
                'start_year' => $source['start_year'],
                'end_year' => $source['end_year'],
                'grade' => $source['grade'],
            ];
        }

        if ($grounded === []) {
            foreach ($sources as $source) {
                $grounded[] = [
                    'source_id' => $source['source_id'],
                    'institution_name' => $source['institution_name'],
                    'degree' => $source['degree'],
                    'field_of_study' => $source['field_of_study'],
                    'start_year' => $source['start_year'],
                    'end_year' => $source['end_year'],
                    'grade' => $source['grade'],
                ];
            }
        }

        return $grounded;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    private function allowedNumberCorpus(array $snapshot): array
    {
        $parts = [
            (string) ($snapshot['professional_summary_source'] ?? ''),
            (string) ($snapshot['years_of_experience'] ?? ''),
        ];

        foreach ($snapshot['experiences'] ?? [] as $exp) {
            $parts = array_merge(
                $parts,
                $exp['responsibilities'] ?? [],
                $exp['achievements'] ?? [],
                [(string) ($exp['start_date'] ?? ''), (string) ($exp['end_date'] ?? '')]
            );
        }

        foreach ($snapshot['projects'] ?? [] as $project) {
            $parts = array_merge(
                $parts,
                $project['features'] ?? [],
                $project['responsibilities'] ?? [],
                [(string) ($project['short_description'] ?? ''), (string) ($project['detailed_description'] ?? '')]
            );
        }

        return $this->textCorpus($parts);
    }

    /**
     * @param  list<string|null>  $parts
     * @return list<string>
     */
    private function textCorpus(array $parts): array
    {
        $numbers = [];
        foreach ($parts as $part) {
            if (! is_string($part) || $part === '') {
                continue;
            }
            if (preg_match_all('/\d[\d,]*(?:\.\d+)?%?/u', $part, $matches)) {
                foreach ($matches[0] as $match) {
                    $numbers[] = $match;
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function introducesUnsupportedNumbers(string $text, array $allowed): bool
    {
        if (! preg_match_all('/\d[\d,]*(?:\.\d+)?%?/u', $text, $matches)) {
            return false;
        }

        $allowedSet = array_fill_keys($allowed, true);
        foreach ($matches[0] as $match) {
            if (! isset($allowedSet[$match])) {
                return true;
            }
        }

        return false;
    }
}
