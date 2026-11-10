<?php

namespace App\Services\Resume;

/**
 * Shared normalized document model for preview, PDF, and DOCX.
 */
class ResumeDocumentNormalizer
{
    public function __construct(
        private readonly ResumeSkillCategorizer $skillCategorizer = new ResumeSkillCategorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array{
     *     contact: array<string, mixed>,
     *     professional_summary: string,
     *     technical_skills: list<string>,
     *     skill_groups: list<array{category: string, skills: list<string>}>,
     *     experience: list<array<string, mixed>>,
     *     projects: list<array<string, mixed>>,
     *     education: list<array<string, mixed>>
     * }
     */
    public function normalize(array $content): array
    {
        $contact = is_array($content['contact'] ?? null) ? $content['contact'] : [];

        $technicalSkills = array_values(array_filter(array_map(
            fn ($skill) => $this->plain((string) $skill),
            is_array($content['technical_skills'] ?? null) ? $content['technical_skills'] : []
        )));

        $skillGroups = $this->normalizeSkillGroups($content['skill_groups'] ?? null, $technicalSkills);

        return [
            'contact' => [
                'full_name' => $this->plain((string) ($contact['full_name'] ?? '')),
                'headline' => $this->plainNullable($contact['headline'] ?? null),
                'email' => $this->plain((string) ($contact['email'] ?? '')),
                'phone' => $this->plainNullable($contact['phone'] ?? null),
                'location' => $this->plainNullable($contact['location'] ?? null),
                'linkedin_url' => $this->plainNullable($contact['linkedin_url'] ?? null),
                'github_url' => $this->plainNullable($contact['github_url'] ?? null),
                'portfolio_url' => $this->plainNullable($contact['portfolio_url'] ?? null),
            ],
            'professional_summary' => $this->plain((string) ($content['professional_summary'] ?? '')),
            'technical_skills' => $technicalSkills,
            'skill_groups' => $skillGroups,
            'experience' => array_values(array_map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'source_id' => (int) ($row['source_id'] ?? 0),
                    'company_name' => $this->plain((string) ($row['company_name'] ?? '')),
                    'job_title' => $this->plain((string) ($row['job_title'] ?? '')),
                    'employment_type' => $this->plainNullable($row['employment_type'] ?? null),
                    'location' => $this->plainNullable($row['location'] ?? null),
                    'start_date' => $this->plainNullable($row['start_date'] ?? null),
                    'end_date' => $this->plainNullable($row['end_date'] ?? null),
                    'is_current' => (bool) ($row['is_current'] ?? false),
                    'bullets' => array_values(array_filter(array_map(
                        fn ($b) => $this->plain((string) $b),
                        is_array($row['bullets'] ?? null) ? $row['bullets'] : []
                    ))),
                ];
            }, is_array($content['experience'] ?? null) ? $content['experience'] : [])),
            'projects' => array_values(array_map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'source_id' => (int) ($row['source_id'] ?? 0),
                    'name' => $this->plain((string) ($row['name'] ?? '')),
                    'project_type' => $this->plainNullable($row['project_type'] ?? null),
                    'technologies' => array_values(array_filter(array_map(
                        fn ($t) => $this->plain((string) $t),
                        is_array($row['technologies'] ?? null) ? $row['technologies'] : []
                    ))),
                    'bullets' => array_values(array_filter(array_map(
                        fn ($b) => $this->plain((string) $b),
                        is_array($row['bullets'] ?? null) ? $row['bullets'] : []
                    ))),
                ];
            }, is_array($content['projects'] ?? null) ? $content['projects'] : [])),
            'education' => array_values(array_map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'source_id' => (int) ($row['source_id'] ?? 0),
                    'institution_name' => $this->plain((string) ($row['institution_name'] ?? '')),
                    'degree' => $this->plain((string) ($row['degree'] ?? '')),
                    'field_of_study' => $this->plainNullable($row['field_of_study'] ?? null),
                    'start_year' => $row['start_year'] ?? null,
                    'end_year' => $row['end_year'] ?? null,
                    'grade' => $this->plainNullable($row['grade'] ?? null),
                ];
            }, is_array($content['education'] ?? null) ? $content['education'] : [])),
        ];
    }

    /**
     * Prefer explicit skill_groups when present; otherwise categorize flat technical_skills.
     *
     * @param  mixed  $groups
     * @param  list<string>  $technicalSkills
     * @return list<array{category: string, skills: list<string>}>
     */
    private function normalizeSkillGroups(mixed $groups, array $technicalSkills): array
    {
        if (is_array($groups) && $groups !== []) {
            $normalized = [];
            foreach ($groups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $category = $this->plainNullable($group['category'] ?? null);
                $skills = array_values(array_filter(array_map(
                    fn ($skill) => $this->plain((string) $skill),
                    is_array($group['skills'] ?? null) ? $group['skills'] : []
                )));
                if ($category === null || $skills === []) {
                    continue;
                }
                $normalized[] = [
                    'category' => $category,
                    'skills' => $skills,
                ];
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return $this->skillCategorizer->group($technicalSkills);
    }

    private function plain(string $value): string
    {
        $withoutBlocks = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value) ?? $value;

        return trim(html_entity_decode(strip_tags($withoutBlocks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function plainNullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $plain = $this->plain((string) $value);

        return $plain === '' ? null : $plain;
    }
}
