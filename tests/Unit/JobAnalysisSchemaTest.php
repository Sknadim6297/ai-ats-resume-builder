<?php

namespace Tests\Unit;

use App\Services\AI\JobAnalysis\JobAnalysisSchema;
use App\Services\AI\JobAnalysis\SkillNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JobAnalysisSchemaTest extends TestCase
{
    public function test_it_validates_and_normalizes_payload(): void
    {
        $schema = new JobAnalysisSchema;

        $result = $schema->validate([
            'job_title' => 'React Native Developer',
            'required_skills' => ['React Native', 'JavaScript', 'React Native'],
            'preferred_skills' => ['TypeScript', 'JavaScript'],
            'experience' => ['minimum_years' => 2, 'maximum_years' => 4, 'summary' => '2-4 years'],
            'responsibilities' => ['Build mobile apps'],
        ]);

        $this->assertSame('React Native Developer', $result['job_title']);
        $this->assertSame(['React Native', 'JavaScript'], $result['required_skills']);
        $this->assertSame(['TypeScript'], $result['preferred_skills']);
        $this->assertSame(2, $result['experience']['minimum_years']);
    }

    public function test_it_rejects_inconsistent_experience_range(): void
    {
        $this->expectException(ValidationException::class);

        (new JobAnalysisSchema)->validate([
            'experience' => ['minimum_years' => 5, 'maximum_years' => 2],
        ]);
    }

    public function test_skill_normalizer_aliases(): void
    {
        $normalizer = new SkillNormalizer;

        $this->assertSame(['React.js', 'Node.js'], $normalizer->normalizeList(['react', 'Node', 'react.js']));
    }
}
