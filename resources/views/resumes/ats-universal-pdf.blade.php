<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            color: #111827;
            line-height: 1.35;
        }
        h1 {
            font-size: 20pt;
            margin: 0 0 4px 0;
            text-align: center;
        }
        .headline, .meta, .links {
            text-align: center;
            font-size: 9.5pt;
            margin: 0 0 2px 0;
        }
        h2 {
            font-size: 11.5pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #9ca3af;
            padding-bottom: 2px;
            margin: 14px 0 6px 0;
        }
        p { margin: 0 0 6px 0; }
        .job-title { font-weight: bold; margin: 0; }
        .job-meta { font-size: 9pt; color: #374151; margin: 0 0 4px 0; }
        ul { margin: 0 0 8px 18px; padding: 0; }
        li { margin: 0 0 3px 0; }
        .section-block { margin-bottom: 4px; }
    </style>
</head>
<body>
    @php($contact = $document['contact'])
    <h1>{{ $contact['full_name'] }}</h1>
    @if(!empty($contact['headline']))
        <p class="headline">{{ $contact['headline'] }}</p>
    @endif
    <p class="meta">
        {{ collect([$contact['email'] ?? null, $contact['phone'] ?? null, $contact['location'] ?? null])->filter()->implode(' · ') }}
    </p>
    @php($links = collect([$contact['linkedin_url'] ?? null, $contact['github_url'] ?? null, $contact['portfolio_url'] ?? null])->filter())
    @if($links->isNotEmpty())
        <p class="links">{{ $links->implode(' · ') }}</p>
    @endif

    <h2>Professional Summary</h2>
    <p>{{ $document['professional_summary'] }}</p>

    <h2>Technical Skills</h2>
    <p>{{ implode(', ', $document['technical_skills']) }}</p>

    @if(!empty($document['experience']))
        <h2>Professional Experience</h2>
        @foreach($document['experience'] as $job)
            <div class="section-block">
                <p class="job-title">{{ $job['job_title'] }} — {{ $job['company_name'] }}</p>
                <p class="job-meta">
                    {{ $job['start_date'] ?? '' }}
                    – {{ !empty($job['is_current']) ? 'Present' : ($job['end_date'] ?? '') }}
                    @if(!empty($job['location']) || !empty($job['employment_type']))
                        · {{ collect([$job['location'] ?? null, $job['employment_type'] ?? null])->filter()->implode(' · ') }}
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
                    <p class="job-meta">Technologies: {{ implode(', ', $project['technologies']) }}</p>
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
                    {{ $edu['degree'] }}@if(!empty($edu['field_of_study'])), {{ $edu['field_of_study'] }}@endif
                </p>
                <p class="job-meta">
                    {{ $edu['institution_name'] }}
                    @if(!empty($edu['start_year']) || !empty($edu['end_year']))
                        · {{ collect([$edu['start_year'] ?? null, $edu['end_year'] ?? null])->filter()->implode(' – ') }}
                    @endif
                </p>
                @if(!empty($edu['grade']))
                    <p class="job-meta">{{ $edu['grade'] }}</p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
