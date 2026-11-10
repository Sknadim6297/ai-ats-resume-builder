<?php

namespace Tests\Unit;

use App\Services\Resume\ResumeSkillCategorizer;
use Tests\TestCase;

class ResumeSkillCategorizerTest extends TestCase
{
    public function test_groups_skills_like_full_stack_resume_template(): void
    {
        $groups = (new ResumeSkillCategorizer)->group([
            'React.js',
            'JavaScript',
            'PHP',
            'Laravel',
            'Eloquent ORM',
            'React Native',
            'MySQL',
            'REST APIs',
            'Razorpay',
            'Docker',
            'Git',
            'OOP',
        ]);

        $byCategory = collect($groups)->mapWithKeys(
            fn (array $group) => [$group['category'] => $group['skills']]
        )->all();

        $this->assertSame(['React.js', 'JavaScript'], $byCategory['Frontend']);
        $this->assertContains('PHP', $byCategory['Backend']);
        $this->assertContains('Laravel', $byCategory['Backend']);
        $this->assertContains('Eloquent ORM', $byCategory['Laravel']);
        $this->assertArrayHasKey('Mobile', $byCategory);
        $this->assertSame(['React Native'], $byCategory['Mobile']);
        $this->assertNotContains('React Native', $byCategory['Frontend'] ?? []);
        $this->assertContains('MySQL', $byCategory['Database']);
        $this->assertContains('REST APIs', $byCategory['APIs & Integrations']);
        $this->assertContains('Docker', $byCategory['Deployment & DevOps']);
        $this->assertContains('Git', $byCategory['Tools']);
        $this->assertContains('OOP', $byCategory['Development']);
    }
}
