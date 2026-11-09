# Database Architecture

## Phase 1 implemented tables

### users (Laravel default)

- `id`, `name`, `email` (unique), `password`, `remember_token`, timestamps
- Auth owner of exactly one master profile (Phase 1 assumption)

### profiles

- `user_id` (unique FK → users, cascade delete)
- Contact + headline + professional summary
- `is_verified` boolean for “verified master profile” workflow later

Indexes:
- unique(`user_id`)

### skills

- Canonical skill catalog (`name`, unique `slug`, optional `category`)

Indexes:
- unique(`name`), unique(`slug`), index(`category`)

### profile_skills

- Pivot: profile ↔ skill
- `proficiency` (1–5), `years_experience`, `is_primary`, `is_verified`

Indexes:
- unique(`profile_id`, `skill_id`)
- index(`profile_id`, `is_primary`)

## Planned tables (not migrated in Phase 1)

### projects / project_technologies

- User projects with tech tags for ranking against JD requirements

### work_experiences

- Employment history bullets for ATS experience section

### educations

- Degrees / certifications

### job_analyses

- Stored JD text, extracted requirements JSON, provider metadata

### skill_approvals

- Human approval for AI-suggested skills before they enter the master profile

### resume_templates

- Template definitions (single-column ATS first)

### resume_versions

- Generated resume content snapshots tied to a job analysis

### generated_resume_files

- File metadata for PDF/DOCX artifacts (paths, mime, checksum)

## Relationship overview

```
users 1──1 profiles
profiles N──M skills (via profile_skills)
profiles 1──N projects | work_experiences | educations   (planned)
users 1──N job_analyses → resume_versions → generated_resume_files (planned)
```

## Migration policy

- Phase 1 migrations exist under `database/migrations/`
- Do **not** run them against production or shared existing databases without review
- Prefer a dedicated MySQL database named e.g. `ai_ats_resume_builder`
