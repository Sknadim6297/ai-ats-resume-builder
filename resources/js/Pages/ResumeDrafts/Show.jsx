import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import StatusFlash from '@/Components/StatusFlash';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatDate(value, isCurrent) {
    if (isCurrent) {
        return 'Present';
    }
    if (!value) {
        return '';
    }
    if (/^\d{4}-\d{2}/.test(value)) {
        const [year, month] = value.split('-');
        return `${month}/${year}`;
    }
    return value;
}

function groupSkills(skills = []) {
    const order = [
        'Frontend',
        'Backend',
        'Laravel',
        'Mobile',
        'Database',
        'APIs & Integrations',
        'Development',
        'Deployment & DevOps',
        'Tools',
        'Other',
    ];

    const rules = {
        Frontend: ['react', 'javascript', 'typescript', 'html', 'css', 'tailwind', 'jquery', 'ajax', 'next', 'vue', 'inertia'],
        Backend: ['php', 'laravel', 'codeigniter', 'node', 'express', 'python', 'fastapi', 'django'],
        Laravel: ['eloquent', 'migration', 'middleware', 'rbac', 'queue', 'mvc', 'validation', 'authentication', 'authorization', 'routing', 'controller'],
        Mobile: ['react native', 'flutter', 'expo', 'android', 'ios'],
        Database: ['mysql', 'mongodb', 'postgres', 'sqlite', 'redis', 'sql', 'database', 'indexing', 'query'],
        'APIs & Integrations': ['rest', 'api', 'graphql', 'razorpay', 'stripe', 'paypal', 'whatsapp', 'pusher', 'llm', 'ocr'],
        Development: ['oop', 'crud', 'debugging', 'security', 'performance', 'caching', 'testing', 'workflow'],
        'Deployment & DevOps': ['aws', 'docker', 'linux', 'deploy', 'ci/cd', 'nginx', 'container'],
        Tools: ['git', 'github', 'gitlab', 'postman', 'cursor', 'vs code', 'figma'],
    };

    const buckets = {};
    skills.forEach((skill) => {
        const value = String(skill || '').toLowerCase();
        let category = 'Other';
        for (const name of ['Laravel', 'Mobile', 'Frontend', 'Database', 'APIs & Integrations', 'Deployment & DevOps', 'Tools', 'Development', 'Backend']) {
            if ((rules[name] || []).some((needle) => value.includes(needle))) {
                category = name;
                break;
            }
        }
        buckets[category] = buckets[category] || [];
        if (!buckets[category].includes(skill)) {
            buckets[category].push(skill);
        }
    });

    return order
        .filter((category) => (buckets[category] || []).length > 0)
        .map((category) => ({ category, skills: buckets[category] }));
}

function AtsResumeDocument({ content }) {
    if (!content) {
        return <p className="text-sm text-slate-500">No resume content available.</p>;
    }

    const contact = content.contact || {};
    const links = [contact.linkedin_url, contact.github_url, contact.portfolio_url].filter(Boolean);
    const skillGroups =
        Array.isArray(content.skill_groups) && content.skill_groups.length > 0
            ? content.skill_groups
            : groupSkills(content.technical_skills || []);

    return (
        <article className="ats-resume mx-auto w-full max-w-[210mm] bg-white text-slate-900 shadow-sm print:shadow-none">
            <div className="box-border min-h-[297mm] px-8 py-10 sm:px-12 sm:py-12">
                <header className="border-b border-slate-300 pb-4 text-center">
                    <h1 className="text-2xl font-bold uppercase tracking-tight text-slate-900 sm:text-3xl">
                        {contact.full_name}
                    </h1>
                    {contact.headline && (
                        <p className="mt-1 text-sm font-medium text-slate-700">{contact.headline}</p>
                    )}
                    <p className="mt-2 text-xs text-slate-600 sm:text-sm">
                        {[contact.location, contact.email, contact.phone].filter(Boolean).join('  |  ')}
                    </p>
                    {links.length > 0 && (
                        <p className="mt-1 break-all text-xs text-slate-600">{links.join('  |  ')}</p>
                    )}
                </header>

                <section className="mt-5">
                    <h2 className="border-b border-slate-200 pb-1 text-sm font-bold uppercase tracking-wide text-slate-900">
                        Professional Summary
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap">
                        {content.professional_summary}
                    </p>
                </section>

                <section className="mt-5">
                    <h2 className="border-b border-slate-200 pb-1 text-sm font-bold uppercase tracking-wide text-slate-900">
                        Technical Skills
                    </h2>
                    <div className="mt-2 space-y-1 text-sm leading-relaxed text-slate-800">
                        {skillGroups.length > 0 ? (
                            skillGroups.map((group) => (
                                <p key={group.category}>
                                    <span className="font-semibold">{group.category}:</span>{' '}
                                    {(group.skills || []).join(', ')}
                                </p>
                            ))
                        ) : (
                            <p>{(content.technical_skills || []).join(', ')}</p>
                        )}
                    </div>
                </section>

                {(content.experience || []).length > 0 && (
                    <section className="mt-5">
                        <h2 className="border-b border-slate-200 pb-1 text-sm font-bold uppercase tracking-wide text-slate-900">
                            Professional Experience
                        </h2>
                        <div className="mt-3 space-y-4">
                            {content.experience.map((job) => (
                                <div key={job.source_id}>
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <p className="text-sm font-semibold text-slate-900">
                                            {job.company_name} — {job.job_title}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            {formatDate(job.start_date)} – {formatDate(job.end_date, job.is_current)}
                                        </p>
                                    </div>
                                    {(job.location || job.employment_type) && (
                                        <p className="text-xs text-slate-600">
                                            {[job.location, job.employment_type].filter(Boolean).join(' · ')}
                                        </p>
                                    )}
                                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-800">
                                        {(job.bullets || []).map((bullet) => (
                                            <li key={bullet}>{bullet}</li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {(content.projects || []).length > 0 && (
                    <section className="mt-5">
                        <h2 className="border-b border-slate-200 pb-1 text-sm font-bold uppercase tracking-wide text-slate-900">
                            Selected Projects
                        </h2>
                        <div className="mt-3 space-y-4">
                            {content.projects.map((project) => (
                                <div key={project.source_id}>
                                    <p className="text-sm font-semibold text-slate-900">
                                        {project.name}
                                        {project.project_type ? ` (${project.project_type})` : ''}
                                    </p>
                                    {(project.technologies || []).length > 0 && (
                                        <p className="mt-1 text-xs text-slate-600">
                                            Tech Stack: {project.technologies.join(', ')}
                                        </p>
                                    )}
                                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-800">
                                        {(project.bullets || []).map((bullet) => (
                                            <li key={bullet}>{bullet}</li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {(content.education || []).length > 0 && (
                    <section className="mt-5">
                        <h2 className="border-b border-slate-200 pb-1 text-sm font-bold uppercase tracking-wide text-slate-900">
                            Education
                        </h2>
                        <div className="mt-3 space-y-3">
                            {content.education.map((edu) => (
                                <div key={edu.source_id}>
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <p className="text-sm font-semibold text-slate-900">
                                            {edu.degree}{edu.field_of_study ? `, ${edu.field_of_study}` : ''}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            {[edu.start_year, edu.end_year].filter(Boolean).join(' – ')}
                                        </p>
                                    </div>
                                    <p className="text-sm text-slate-700">{edu.institution_name}</p>
                                    {edu.grade && <p className="text-xs text-slate-600">{edu.grade}</p>}
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </article>
    );
}

function changeLabel(type) {
    switch (type) {
        case 'ai_generated':
            return 'AI Generated';
        case 'manually_edited':
            return 'Manually Edited';
        case 'restored':
            return 'Restored';
        default:
            return type || 'Version';
    }
}

export default function Show({ draft, status }) {
    const [regenerating, setRegenerating] = useState(false);
    const [restoringId, setRestoringId] = useState(null);

    const regenerate = () => {
        const confirmed = window.confirm(
            'Generate a new AI version? Previous versions are kept in history.',
        );
        if (!confirmed) {
            return;
        }
        setRegenerating(true);
        router.post(
            route('job-analyzer.resume-draft.generate', draft.job_analysis_id),
            { confirm_replace: true },
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(false),
            },
        );
    };

    const restore = (versionId) => {
        if (!window.confirm('Restore this version as a new current version?')) {
            return;
        }
        setRestoringId(versionId);
        router.post(
            route('resume-drafts.versions.restore', [draft.id, versionId]),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRestoringId(null),
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">ATS Resume Preview</h1>
                    <p className="truncate text-sm text-slate-500">
                        Universal single-column template · v{draft.current_version_number || 1}
                        {draft.viewing_historical ? ' · historical view' : ''}
                    </p>
                </div>
            }
        >
            <Head title="Resume Preview" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-5xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card className="space-y-3">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Target role (not printed on resume)</p>
                                <p className="mt-1 font-medium text-slate-900">
                                    {draft.target?.job_title || 'Job analysis'}
                                    {draft.target?.company_name ? ` · ${draft.target.company_name}` : ''}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <Badge variant={draft.generation_status === 'completed' ? 'success' : 'warning'}>
                                        {draft.generation_status}
                                    </Badge>
                                    <Badge>{draft.template_version}</Badge>
                                    <Badge variant="info">v{draft.current_version_number || 1}</Badge>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Link href={route('resumes.index')} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                                    All resumes
                                </Link>
                                <Link href={route('job-analyzer.show', draft.job_analysis_id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                                    Back to analysis
                                </Link>
                                <Link href={route('resume-drafts.edit', draft.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                                    Edit
                                </Link>
                                <a href={route('resume-drafts.download.pdf', draft.current_version_id ? [draft.id, draft.current_version_id] : draft.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                                    PDF
                                </a>
                                <a href={route('resume-drafts.download.docx', draft.current_version_id ? [draft.id, draft.current_version_id] : draft.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
                                    DOCX
                                </a>
                                <button
                                    type="button"
                                    disabled={regenerating}
                                    onClick={regenerate}
                                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                                >
                                    {regenerating ? 'Regenerating…' : 'Regenerate'}
                                </button>
                            </div>
                        </div>
                        {draft.safe_error && (
                            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                {draft.safe_error}
                            </p>
                        )}
                    </Card>

                    {(draft.versions || []).length > 0 && (
                        <Card className="space-y-3">
                            <h3 className="font-semibold text-slate-900">Version history</h3>
                            <div className="space-y-2">
                                {draft.versions.map((version) => (
                                    <div key={version.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <Badge variant={version.is_current ? 'success' : 'default'}>v{version.version_number}</Badge>
                                            <Badge>{changeLabel(version.change_type)}</Badge>
                                            <span className="text-xs text-slate-500">{version.created_at}</span>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Link
                                                href={route('resume-drafts.versions.show', [draft.id, version.id])}
                                                className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600"
                                            >
                                                Preview
                                            </Link>
                                            {!version.is_current && (
                                                <button
                                                    type="button"
                                                    disabled={restoringId === version.id}
                                                    onClick={() => restore(version.id)}
                                                    className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 disabled:opacity-50"
                                                >
                                                    {restoringId === version.id ? 'Restoring…' : 'Restore'}
                                                </button>
                                            )}
                                            <a href={route('resume-drafts.download.pdf', [draft.id, version.id])} className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600">PDF</a>
                                            <a href={route('resume-drafts.download.docx', [draft.id, version.id])} className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600">DOCX</a>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}

                    <div className="resume-stage overflow-x-auto">
                        <AtsResumeDocument content={draft.content} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
