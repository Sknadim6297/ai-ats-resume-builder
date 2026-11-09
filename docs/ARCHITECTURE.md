# AI ATS Resume Builder — Architecture (Phase 1)

## Purpose

Standalone web app for maintaining one verified master profile and (in later phases)
generating job-specific ATS-friendly resumes with Grok AI.

This project is independent of HireFlow / `ai-hr-platform`.

## Stack

- Laravel 12 + PHP 8.2+
- React + Inertia.js + Tailwind CSS
- MySQL (preferred production DB; local default may use SQLite during scaffolding)
- Laravel session authentication (Breeze)
- Server-side xAI/Grok integration (prepared, not live in Phase 1)
- PHPWord installed for future DOCX export

## Module map

| Module | Phase 1 status |
|---|---|
| Authentication | Implemented |
| Admin shell / navigation | Implemented |
| Master Profile CRUD | Schema + models only |
| Skills | Schema + models only |
| Projects | Documented for Phase 2 |
| Work Experience | Documented for Phase 2 |
| Education | Documented for Phase 2 |
| Job Description Analysis | AI contracts prepared |
| Skill Matching | AI contracts prepared |
| Resume Generation | Template planned; no generation |
| Resume Templates / History | Documented for later phases |

## Backend layout

```
app/
  Http/Controllers/     # Auth + account profile (Breeze)
  Models/               # User, Profile, Skill
  Services/AI/          # Provider abstraction
  Providers/            # App + Ai service providers
```

Business logic for AI and future resume pipelines must remain in `app/Services`
so a later mobile API can reuse the same services without duplicating rules.

## Security baseline

- Session auth with CSRF protection
- Password hashing via Laravel casts (`hashed`)
- Session regeneration on login (Breeze)
- AI API keys only in server env (`XAI_API_KEY`)
- No secrets in frontend / Vite env for AI
- Future feature routes must remain behind `auth` middleware
