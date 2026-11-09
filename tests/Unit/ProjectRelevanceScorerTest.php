<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\ProjectResponsibility;
use App\Models\ProjectTechnology;
use App\Models\User;
use App\Services\AI\JobAnalysis\SkillNormalizer;
use App\Services\Projects\ProjectRelevanceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRelevanceScorerTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $user, array $attrs, array $techs, array $features = [], array $responsibilities = []): Project
    {
        $project = Project::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Sample',
            'short_description' => 'Short',
            'detailed_description' => 'Detailed',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ], $attrs));

        foreach ($techs as $i => $tech) {
            ProjectTechnology::query()->create([
                'project_id' => $project->id,
                'name' => $tech,
                'sort_order' => $i,
            ]);
        }
        foreach ($features as $i => $feature) {
            ProjectFeature::query()->create([
                'project_id' => $project->id,
                'description' => $feature,
                'sort_order' => $i,
            ]);
        }
        foreach ($responsibilities as $i => $item) {
            ProjectResponsibility::query()->create([
                'project_id' => $project->id,
                'description' => $item,
                'sort_order' => $i,
            ]);
        }

        return $project->load(['technologies', 'features', 'responsibilities']);
    }

    public function test_laravel_job_ranks_laravel_project_highly(): void
    {
        $user = User::factory()->create();
        $laravel = $this->makeProject($user, ['name' => 'Hire Portal', 'project_type' => 'Web Application'], ['Laravel', 'MySQL', 'PHP']);
        $mobile = $this->makeProject($user, ['name' => 'Field App', 'project_type' => 'Mobile Application'], ['React Native', 'Expo']);

        $scorer = new ProjectRelevanceScorer(new SkillNormalizer);
        $analysis = [
            'job_title' => 'Laravel Developer',
            'job_category' => 'Backend Web',
            'required_skills' => ['Laravel', 'PHP', 'MySQL'],
            'preferred_skills' => ['Redis'],
            'frameworks_libraries' => ['Laravel'],
            'databases' => ['MySQL'],
            'tools_platforms' => [],
            'technical_keywords' => ['api', 'backend'],
            'responsibilities' => ['Build REST APIs'],
        ];

        $laravelScore = $scorer->score($laravel, $analysis);
        $mobileScore = $scorer->score($mobile, $analysis);

        $this->assertGreaterThan($mobileScore['score'], $laravelScore['score']);
        $this->assertContains('Laravel', $laravelScore['matched_required_skills']);
        $this->assertGreaterThanOrEqual(0, $laravelScore['score']);
        $this->assertLessThanOrEqual(100, $laravelScore['score']);
    }

    public function test_react_native_job_ranks_mobile_project(): void
    {
        $user = User::factory()->create();
        $mobile = $this->makeProject($user, ['name' => 'Courier App', 'project_type' => 'Mobile Application'], ['React Native', 'JavaScript', 'Expo']);
        $mern = $this->makeProject($user, ['name' => 'Shop API', 'project_type' => 'Web Application'], ['MongoDB', 'Express.js', 'React.js', 'Node.js']);

        $scorer = new ProjectRelevanceScorer(new SkillNormalizer);
        $analysis = [
            'job_title' => 'React Native Developer',
            'job_category' => 'Mobile',
            'required_skills' => ['React Native', 'JavaScript'],
            'preferred_skills' => ['TypeScript'],
            'frameworks_libraries' => ['React Native', 'Expo'],
            'databases' => [],
            'tools_platforms' => ['Expo'],
            'technical_keywords' => ['mobile'],
            'responsibilities' => ['Develop mobile applications'],
        ];

        $mobileScore = $scorer->score($mobile, $analysis);
        $mernScore = $scorer->score($mern, $analysis);

        $this->assertGreaterThan($mernScore['score'], $mobileScore['score']);
        $this->assertContains('React Native', $mobileScore['matched_required_skills']);
    }

    public function test_mern_job_ranks_mern_stack_project(): void
    {
        $user = User::factory()->create();
        $mern = $this->makeProject($user, ['name' => 'Commerce Hub'], ['MongoDB', 'Express.js', 'React.js', 'Node.js']);
        $laravel = $this->makeProject($user, ['name' => 'ERP'], ['Laravel', 'MySQL']);

        $scorer = new ProjectRelevanceScorer(new SkillNormalizer);
        $analysis = [
            'job_title' => 'MERN Developer',
            'job_category' => 'Full Stack',
            'required_skills' => ['MongoDB', 'Express.js', 'React.js', 'Node.js'],
            'preferred_skills' => ['TypeScript'],
            'frameworks_libraries' => ['React.js', 'Express.js'],
            'databases' => ['MongoDB'],
            'tools_platforms' => [],
            'technical_keywords' => ['mern'],
            'responsibilities' => [],
        ];

        $mernScore = $scorer->score($mern, $analysis);
        $laravelScore = $scorer->score($laravel, $analysis);

        $this->assertGreaterThan($laravelScore['score'], $mernScore['score']);
        $this->assertCount(4, $mernScore['matched_required_skills']);
    }

    public function test_alias_normalization_and_empty_data_safe(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProject($user, ['name' => 'Node Service'], ['Node.js', 'React.js']);

        $scorer = new ProjectRelevanceScorer(new SkillNormalizer);
        $score = $scorer->score($project, [
            'job_title' => null,
            'job_category' => null,
            'required_skills' => ['Node', 'React'],
            'preferred_skills' => [],
            'frameworks_libraries' => [],
            'databases' => [],
            'tools_platforms' => [],
            'technical_keywords' => [],
            'responsibilities' => [],
        ]);

        $this->assertContains('Node.js', $score['matched_required_skills']);
        $this->assertContains('React.js', $score['matched_required_skills']);
        $this->assertGreaterThan(0, $score['score']);
        $this->assertLessThanOrEqual(100, $score['score']);
    }

    public function test_required_weighs_more_than_preferred(): void
    {
        $user = User::factory()->create();
        $requiredHeavy = $this->makeProject($user, ['name' => 'Req Match'], ['Laravel', 'PHP']);
        $preferredHeavy = $this->makeProject($user, ['name' => 'Pref Match'], ['Redis', 'Docker']);

        $scorer = new ProjectRelevanceScorer(new SkillNormalizer);
        $analysis = [
            'job_title' => 'Backend Engineer',
            'job_category' => 'Backend',
            'required_skills' => ['Laravel', 'PHP'],
            'preferred_skills' => ['Redis', 'Docker'],
            'frameworks_libraries' => [],
            'databases' => [],
            'tools_platforms' => [],
            'technical_keywords' => [],
            'responsibilities' => [],
        ];

        $req = $scorer->score($requiredHeavy, $analysis);
        $pref = $scorer->score($preferredHeavy, $analysis);

        $this->assertGreaterThan($pref['score'], $req['score']);
    }
}
