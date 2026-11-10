<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #111827;
            line-height: 1.35;
        }
        h1 {
            font-size: 18pt;
            margin: 0 0 2px 0;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .headline, .meta, .links {
            text-align: center;
            font-size: 9pt;
            margin: 0 0 2px 0;
        }
        .headline {
            font-size: 9.5pt;
            color: #1f2937;
        }
        h2 {
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #6b7280;
            padding-bottom: 2px;
            margin: 12px 0 5px 0;
        }
        p { margin: 0 0 5px 0; }
        .job-title { font-weight: bold; margin: 0; }
        .job-meta { font-size: 9pt; color: #374151; margin: 0 0 3px 0; }
        .skill-line { margin: 0 0 2px 0; font-size: 9.5pt; }
        .skill-label { font-weight: bold; }
        ul { margin: 0 0 7px 16px; padding: 0; }
        li { margin: 0 0 2px 0; }
        .section-block { margin-bottom: 5px; }
    </style>
</head>
<body>
    @php($contact = $document['contact'])
    <h1>{{ $contact['full_name'] }}</h1>
    @if(!empty($contact['headline']))
        <p class="headline">{{ $contact['headline'] }}</p>
    @endif
    <p class="meta">
        {{ collect([$contact['location'] ?? null, $contact['email'] ?? null, $contact['phone'] ?? null])->filter()->implode('  |  ') }}
    </p>
    @php($links = collect([$contact['linkedin_url'] ?? null, $contact['github_url'] ?? null, $contact['portfolio_url'] ?? null])->filter())
    @if($links->isNotEmpty())
        <p class="links">{{ $links->implode('  |  ') }}</p>
    @endif

    <h2>Professional Summary</h2>
    <p>{{ $document['professional_summary'] }}</p>

    <h2>Technical Skills</h2>
    @if(!empty($document['skill_groups']))
        @foreach($document['skill_groups'] as $group)
            <p class="skill-line">
                <span class="skill-label">{{ $group['category'] }}:</span>
                {{ implode(', ', $group['skills']) }}
            </p>
        @endforeach
    @else
        <p>{{ implode(', ', $document['technical_skills']) }}</p>
    @endif

    @if(!empty($document['experience']))
        <h2>Professional Experience</h2>
        @foreach($document['experience'] as $job)
            <div class="section-block">
                <p class="job-title">{{ $job['company_name'] }} — {{ $job['job_title'] }}</p>
                <p class="job-meta">
                    @if(!empty($job['location']))
                        {{ $job['location'] }} |
                    @endif
                    {{ $job['start_date'] ?? '' }}
                    – {{ !empty($job['is_current']) ? 'Present' : ($job['end_date'] ?? '') }}
                    @if(!empty($job['employment_type']))
                        · {{ $job['employment_type'] }}
                    @endif
                </p>
                @if(!empty($job['bullets']))
                    <ul>
                        @foreach($job['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if(!empty($document['projects']))
        <h2>Selected Projects</h2>
        @foreach($document['projects'] as $project)
            <div class="section-block">
                <p class="job-title">
                    {{ $project['name'] }}
                    @if(!empty($project['project_type'])) ({{ $project['project_type'] }}) @endif
                </p>
                @if(!empty($project['technologies']))
                    <p class="job-meta">Tech Stack: {{ implode(', ', $project['technologies']) }}</p>
                @endif
                @if(!empty($project['bullets']))
                    <ul>
                        @foreach($project['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if(!empty($document['education']))
        <h2>Education</h2>
        @foreach($document['education'] as $edu)
            <div class="section-block">
                <p class="job-title">
                    {{ $edu['institution_name'] }}
                    @if(!empty($edu['start_year']) || !empty($edu['end_year']))
                        | {{ collect([$edu['start_year'] ?? null, $edu['end_year'] ?? null])->filter()->implode(' – ') }}
                    @endif
                </p>
                <p class="job-meta">
                    {{ $edu['degree'] }}@if(!empty($edu['field_of_study'])), {{ $edu['field_of_study'] }}@endif
                </p>
                @if(!empty($edu['grade']))
                    <p class="job-meta">{{ $edu['grade'] }}</p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
