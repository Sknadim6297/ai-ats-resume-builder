<?php

namespace App\Services\AI\JobAnalysis;

class SkillNormalizer
{
    /**
     * Obvious alias map only. Different technologies are never merged.
     *
     * @var array<string, string>
     */
    private array $aliases = [
        'react' => 'React.js',
        'reactjs' => 'React.js',
        'react.js' => 'React.js',
        'react native' => 'React Native',
        'node' => 'Node.js',
        'nodejs' => 'Node.js',
        'node.js' => 'Node.js',
        'js' => 'JavaScript',
        'javascript' => 'JavaScript',
        'ts' => 'TypeScript',
        'typescript' => 'TypeScript',
        'postgres' => 'PostgreSQL',
        'postgresql' => 'PostgreSQL',
        'mongo' => 'MongoDB',
        'mongodb' => 'MongoDB',
        'mysql' => 'MySQL',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'express' => 'Express.js',
        'express.js' => 'Express.js',
        'rest' => 'REST API',
        'rest api' => 'REST API',
        'restful api' => 'REST API',
        'expo' => 'Expo',
    ];

    public function normalize(string $skill): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $skill) ?? $skill);
        $key = mb_strtolower($trimmed);

        return $this->aliases[$key] ?? $trimmed;
    }

    /**
     * @param  list<string>  $skills
     * @return list<string>
     */
    public function normalizeList(array $skills): array
    {
        $normalized = [];

        foreach ($skills as $skill) {
            $value = $this->normalize($skill);
            if ($value === '') {
                continue;
            }
            $normalized[$this->comparisonKey($value)] = $value;
        }

        return array_values($normalized);
    }

    public function comparisonKey(string $skill): string
    {
        return mb_strtolower($this->normalize($skill));
    }
}
