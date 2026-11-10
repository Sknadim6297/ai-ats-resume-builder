<?php

namespace App\Services\Resume;

use App\Models\Skill;

/**
 * Groups flat technical skills into ATS-friendly category lines
 * matching common resume templates (Frontend / Backend / Database / …).
 */
class ResumeSkillCategorizer
{
    /**
     * Preferred display order (matches common full-stack resume layouts).
     *
     * @var list<string>
     */
    public const ORDER = [
        'Frontend',
        'Backend',
        'Laravel',
        'Mobile',
        'Database',
        'APIs & Integrations',
        'Development',
        'Deployment & DevOps',
        'Tools',
        'Other',
    ];

    /**
     * @param  list<string>  $skills
     * @param  array<string, string>|null  $categoryOverrides  skill name (case-insensitive) => category
     * @return list<array{category: string, skills: list<string>}>
     */
    public function group(array $skills, ?array $categoryOverrides = null): array
    {
        $skills = array_values(array_filter(array_map(
            fn ($skill) => trim((string) $skill),
            $skills
        ), fn ($skill) => $skill !== ''));

        if ($skills === []) {
            return [];
        }

        $catalog = $this->catalogCategories();
        $overrides = [];
        foreach ($categoryOverrides ?? [] as $name => $category) {
            $key = mb_strtolower(trim((string) $name));
            $label = trim((string) $category);
            if ($key !== '' && $label !== '') {
                $overrides[$key] = $label;
            }
        }

        $buckets = [];
        foreach ($skills as $skill) {
            $key = mb_strtolower($skill);
            $category = $overrides[$key]
                ?? $catalog[$key]
                ?? $this->inferCategory($skill);

            $buckets[$category] ??= [];
            if (! in_array($skill, $buckets[$category], true)) {
                $buckets[$category][] = $skill;
            }
        }

        $grouped = [];
        foreach (self::ORDER as $category) {
            if (! empty($buckets[$category])) {
                $grouped[] = [
                    'category' => $category,
                    'skills' => array_values($buckets[$category]),
                ];
                unset($buckets[$category]);
            }
        }

        // Any unexpected categories from the skill catalog.
        foreach ($buckets as $category => $items) {
            $grouped[] = [
                'category' => $category,
                'skills' => array_values($items),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, string> lowercase skill name => category
     */
    private function catalogCategories(): array
    {
        try {
            $map = [];
            foreach (Skill::query()->whereNotNull('category')->get(['name', 'category']) as $skill) {
                $name = mb_strtolower(trim((string) $skill->name));
                $category = trim((string) $skill->category);
                if ($name !== '' && $category !== '') {
                    $map[$name] = $category;
                }
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    private function inferCategory(string $skill): string
    {
        $value = mb_strtolower($skill);

        $rules = [
            'Frontend' => [
                'react', 'javascript', 'typescript', 'html', 'css', 'tailwind', 'jquery', 'ajax',
                'next.js', 'vue', 'angular', 'bootstrap', 'sass', 'inertia',
            ],
            'Backend' => [
                'php', 'laravel', 'codeigniter', 'node', 'express', 'nestjs', 'python', 'fastapi',
                'django', 'flask', 'java', 'spring', 'csharp', '.net', 'ruby', 'rails',
            ],
            'Laravel' => [
                'eloquent', 'migration', 'middleware', 'rbac', 'multi-guard', 'queue', 'job',
                'event', 'listener', 'blade', 'artisan', 'mvc', 'validation', 'authorization',
                'authentication', 'routing', 'controller',
            ],
            'Mobile' => [
                'react native', 'flutter', 'swift', 'kotlin', 'android', 'ios', 'expo',
            ],
            'Database' => [
                'mysql', 'mongodb', 'postgres', 'sqlite', 'redis', 'sql', 'database', 'indexing',
                'query', 'eloquent relationship', 'schema',
            ],
            'APIs & Integrations' => [
                'rest', 'api', 'graphql', 'razorpay', 'stripe', 'paypal', 'whatsapp', 'pusher',
                'oauth', 'webhook', 'llm', 'openai', 'gemini', 'ocr',
            ],
            'Development' => [
                'oop', 'crud', 'debugging', 'security', 'performance', 'caching', 'testing',
                'phpunit', 'jest', 'cypress', 'business logic', 'error handling', 'workflow',
            ],
            'Deployment & DevOps' => [
                'aws', 'docker', 'linux', 'deploy', 'ci/cd', 'nginx', 'apache', 'kubernetes',
                'container', 'environment',
            ],
            'Tools' => [
                'git', 'github', 'gitlab', 'postman', 'cursor', 'vs code', 'jira', 'figma',
            ],
        ];

        // More specific buckets first (e.g. Mobile before Frontend so "React Native" wins).
        foreach (['Laravel', 'Mobile', 'Frontend', 'Database', 'APIs & Integrations', 'Deployment & DevOps', 'Tools', 'Development', 'Backend'] as $category) {
            foreach ($rules[$category] as $needle) {
                if (str_contains($value, $needle)) {
                    return $category;
                }
            }
        }

        return 'Other';
    }
}
