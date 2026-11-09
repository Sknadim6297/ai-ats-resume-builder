<?php

namespace Tests\Unit;

use App\Services\AI\JobAnalysis\SkillNormalizer;
use App\Services\Resume\ResumeGroundingValidator;
use Tests\TestCase;

class ResumeGroundingValidatorTest extends TestCase
{
    public function test_it_rejects_unapproved_skills_and_keeps_source_facts(): void
    {
        $validator = new ResumeGroundingValidator(new SkillNormalizer);

        $snapshot = [
            'contact' => [
                'full_name' => 'Alex',
                'headline' => 'Engineer',
                'email' => 'alex@example.com',
                'phone' => null,
                'location' => null,
                'linkedin_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary_source' => 'Source summary',
            'years_of_experience' => 3,
            'approved_skills' => ['Laravel', 'PHP'],
            'excluded' => [
                'ignored_skills' => ['Docker'],
                'pending_skills' => ['Kubernetes'],
            ],
            'experiences' => [[
                'source_id' => 1,
                'company_name' => 'Acme',
                'job_title' => 'Developer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2021-01-01',
                'end_date' => null,
                'is_current' => true,
                'responsibilities' => ['Maintained APIs'],
                'achievements' => [],
            ]],
            'projects' => [[
                'source_id' => 9,
                'name' => 'Portal',
                'project_type' => 'Web Application',
                'short_description' => 'Portal app',
                'detailed_description' => 'Portal details',
                'technologies' => ['Laravel'],
                'features' => ['Auth'],
                'responsibilities' => ['Built modules'],
            ]],
            'education' => [[
                'source_id' => 3,
                'institution_name' => 'Uni',
                'degree' => 'B.S.',
                'field_of_study' => 'CS',
                'start_year' => 2015,
                'end_year' => 2019,
                'grade' => null,
            ]],
        ];

        $draft = [
            'contact' => [
                'full_name' => 'Hacker',
                'headline' => 'CEO',
                'email' => 'evil@example.com',
            ],
            'professional_summary' => 'Invented 900% growth story',
            'technical_skills' => ['Laravel', 'Docker', 'Kubernetes', 'Go'],
            'experience' => [[
                'source_id' => 1,
                'company_name' => 'Other',
                'job_title' => 'CTO',
                'employment_type' => 'Contract',
                'location' => 'NY',
                'start_date' => '2000-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Grew metrics by 900%', 'Maintained APIs'],
            ]],
            'projects' => [[
                'source_id' => 9,
                'name' => 'Fake',
                'project_type' => 'Game',
                'technologies' => ['Laravel', 'Go'],
                'bullets' => ['Auth'],
            ]],
            'education' => [[
                'source_id' => 3,
                'institution_name' => 'Fake Uni',
                'degree' => 'PhD',
                'field_of_study' => 'Magic',
                'start_year' => 1900,
                'end_year' => 1901,
                'grade' => 'A+',
            ]],
        ];

        $result = $validator->validate($draft, $snapshot);

        $this->assertSame('Alex', $result['contact']['full_name']);
        $this->assertSame('alex@example.com', $result['contact']['email']);
        $this->assertContains('Laravel', $result['technical_skills']);
        $this->assertContains('PHP', $result['technical_skills']);
        $this->assertNotContains('Docker', $result['technical_skills']);
        $this->assertNotContains('Kubernetes', $result['technical_skills']);
        $this->assertNotContains('Go', $result['technical_skills']);
        $this->assertSame('Acme', $result['experience'][0]['company_name']);
        $this->assertSame('2021-01-01', $result['experience'][0]['start_date']);
        $this->assertNotContains('Grew metrics by 900%', $result['experience'][0]['bullets']);
        $this->assertSame('Portal', $result['projects'][0]['name']);
        $this->assertSame(['Laravel'], $result['projects'][0]['technologies']);
        $this->assertSame('Uni', $result['education'][0]['institution_name']);
        $this->assertSame('B.S.', $result['education'][0]['degree']);
        $this->assertSame('Source summary', $result['professional_summary']);
    }
}
