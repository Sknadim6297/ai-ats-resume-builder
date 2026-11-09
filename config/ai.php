<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "grok", "gemini", "null"
    | Keep "null" until a provider key + model are configured.
    |
    */

    // Unquoted AI_PROVIDER=null in .env becomes PHP null; coerce to the "null" driver.
    'default' => env('AI_PROVIDER') ?: 'null',

    'providers' => [

        'grok' => [
            'driver' => 'grok',
            'api_key' => env('XAI_API_KEY'),
            'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
            // Set XAI_MODEL explicitly from current xAI docs (e.g. grok-4.7).
            'model' => env('XAI_MODEL'),
            'timeout' => (int) env('XAI_TIMEOUT', 45),
            'max_retries' => min(2, (int) env('XAI_MAX_RETRIES', 1)),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            // Example: gemini-2.5-flash
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 90),
            'max_retries' => min(2, (int) env('GEMINI_MAX_RETRIES', 1)),
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    'tasks' => [
        'jd_extraction' => [
            'response_format' => 'json',
            'schema' => 'JobAnalysisSchema',
            'provider' => env('AI_JD_PROVIDER'),
        ],
        'skill_matching' => [
            'response_format' => 'json',
            'schema' => 'SkillMatchSchema',
        ],
        'project_ranking' => [
            'response_format' => 'json',
            'schema' => 'ProjectRankingSchema',
        ],
        'resume_generation' => [
            'response_format' => 'json',
            'schema' => 'GroundedResumeSchema',
            'provider' => env('AI_RESUME_PROVIDER'),
        ],
        'resume_import' => [
            'response_format' => 'json',
            'schema' => 'ResumeImportSchema',
            // Prefer Gemini for resume extraction unless overridden.
            'provider' => env('AI_RESUME_IMPORT_PROVIDER', 'gemini'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Description Analysis Limits
    |--------------------------------------------------------------------------
    */

    'job_analysis' => [
        'min_description_length' => 80,
        'max_description_length' => 20000,
        'rate_limit_per_minute' => (int) env('AI_JD_RATE_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deterministic Project Ranking (no paid AI required)
    |--------------------------------------------------------------------------
    */

    'project_ranking' => [
        'default_recommendation_count' => (int) env('AI_PROJECT_RECOMMEND_COUNT', 3),
        'min_recommendation_count' => 2,
        'max_recommendation_count' => 5,
        'max_selected_projects' => 5,
        'low_confidence_score' => 25,
        'weights' => [
            'required_skills' => 0.40,
            'preferred_skills' => 0.15,
            'technologies' => 0.20,
            'features_responsibilities' => 0.15,
            'type_category' => 0.10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resume Drafting (Phase 7)
    |--------------------------------------------------------------------------
    */

    'resume_import' => [
        'max_text_length' => (int) env('AI_RESUME_IMPORT_MAX_TEXT', 50000),
        'rate_limit_per_minute' => (int) env('AI_RESUME_IMPORT_RATE_LIMIT', 5),
    ],

    'resume_drafting' => [
        'rate_limit_per_minute' => (int) env('AI_RESUME_RATE_LIMIT', 3),
        'max_summary_chars' => 700,
        'max_bullet_chars' => 280,
        'max_bullets_per_experience' => 8,
        'max_bullets_per_project' => 6,
        'max_skills' => 40,
        'temperature' => 0.2,
    ],

];
