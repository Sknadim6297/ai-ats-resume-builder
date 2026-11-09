import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import StatusFlash from '@/Components/StatusFlash';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function SkillList({ title, items, emptyLabel = 'Not specified in JD', variant = 'default' }) {
    return (
        <div>
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {items?.length ? (
                <div className="mt-2 flex flex-wrap gap-2">
                    {items.map((item) => (
                        <Badge key={item} variant={variant}>{item}</Badge>
                    ))}
                </div>
            ) : (
                <p className="mt-2 text-sm text-slate-500">{emptyLabel}</p>
            )}
        </div>
    );
}

function BulletList({ title, items }) {
    return (
        <div>
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {items?.length ? (
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                    {items.map((item) => <li key={item}>{item}</li>)}
                </ul>
            ) : (
                <p className="mt-2 text-sm text-slate-500">Not specified in JD</p>
            )}
        </div>
    );
}

function decisionLabel(decision) {
    switch (decision) {
        case 'add_to_profile':
            return 'Added to Master Profile';
        case 'resume_only':
            return 'Resume only';
        case 'ignore':
            return 'Ignored';
        default:
            return 'Pending';
    }
}

function decisionVariant(decision) {
    switch (decision) {
        case 'add_to_profile':
            return 'success';
        case 'resume_only':
            return 'info';
        case 'ignore':
            return 'default';
        default:
            return 'warning';
    }
}

function MatchedSkillsSection({ title, items }) {
    return (
        <div>
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {items?.length ? (
                <div className="mt-2 flex flex-wrap gap-2">
                    {items.map((item) => (
                        <Badge key={`${item.requirement_type}-${item.normalized_key}`} variant="success">
                            {item.name}
                        </Badge>
                    ))}
                </div>
            ) : (
                <p className="mt-2 text-sm text-slate-500">None matched in Master Profile</p>
            )}
        </div>
    );
}

function MissingSkillRow({ analysisId, item, busyKey, setBusyKey }) {
    const [confirmed, setConfirmed] = useState(false);
    const errors = usePage().props.errors || {};
    const rowKey = `${item.requirement_type}:${item.normalized_key}`;
    const busy = busyKey === rowKey;

    const submit = (decision) => {
        const needsConfirm = decision === 'add_to_profile' || decision === 'resume_only';
        if (needsConfirm && !confirmed) {
            return;
        }

        setBusyKey(rowKey);
        router.post(
            route('job-analyzer.skill-decisions.store', analysisId),
            {
                skill_name: item.name,
                requirement_type: item.requirement_type,
                decision,
                confirmed: needsConfirm ? true : false,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyKey(null);
                    if (decision === 'pending' || decision === 'ignore') {
                        setConfirmed(false);
                    }
                },
            },
        );
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate font-medium text-slate-900">{item.name}</p>
                    <div className="mt-1 flex flex-wrap gap-2">
                        <Badge variant={item.requirement_type === 'required' ? 'warning' : 'default'}>
                            {item.requirement_type}
                        </Badge>
                        <Badge variant={decisionVariant(item.decision)}>
                            {decisionLabel(item.decision)}
                        </Badge>
                    </div>
                </div>
            </div>

            <label className="mt-3 flex items-start gap-2 text-xs text-slate-600">
                <input
                    type="checkbox"
                    className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    checked={confirmed}
                    disabled={busy}
                    onChange={(e) => setConfirmed(e.target.checked)}
                />
                <span>
                    I confirm that I genuinely possess this skill and can discuss or demonstrate it.
                    This is a user confirmation, not independently verified evidence.
                </span>
            </label>

            {(errors.confirmed || errors.skill_name || errors.decision) && busyKey === null && (
                <p className="mt-2 text-xs text-rose-600">
                    {errors.confirmed || errors.skill_name || errors.decision}
                </p>
            )}

            <div className="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    disabled={busy || !confirmed}
                    onClick={() => submit('add_to_profile')}
                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Add to Master Profile
                </button>
                <button
                    type="button"
                    disabled={busy || !confirmed}
                    onClick={() => submit('resume_only')}
                    className="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Resume Only
                </button>
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => submit('ignore')}
                    className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 disabled:opacity-50"
                >
                    Ignore
                </button>
                <button
                    type="button"
                    disabled={busy || item.decision === 'pending'}
                    onClick={() => submit('pending')}
                    className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 disabled:opacity-50"
                >
                    Keep Pending
                </button>
            </div>
        </div>
    );
}

function MissingSkillsSection({ title, items, analysisId, busyKey, setBusyKey }) {
    return (
        <div className="space-y-3">
            <div>
                <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
                <p className="mt-1 text-xs text-slate-500">
                    Not found in your saved Master Profile. That does not prove you lack the skill.
                </p>
            </div>
            {items?.length ? (
                items.map((item) => (
                    <MissingSkillRow
                        key={`${item.requirement_type}-${item.normalized_key}`}
                        analysisId={analysisId}
                        item={item}
                        busyKey={busyKey}
                        setBusyKey={setBusyKey}
                    />
                ))
            ) : (
                <p className="text-sm text-slate-500">None</p>
            )}
        </div>
    );
}

function moveItem(list, fromIndex, toIndex) {
    if (toIndex < 0 || toIndex >= list.length) {
        return list;
    }
    const next = [...list];
    const [item] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, item);
    return next;
}

function ProjectSelectionPanel({ analysisId, selection }) {
    const maxSelected = selection?.max_selected || 5;
    const errors = usePage().props.errors || {};
    const initialIds = useMemo(
        () => (selection?.selected || []).map((item) => item.id),
        [selection],
    );
    const [selectedIds, setSelectedIds] = useState(initialIds);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setSelectedIds(initialIds);
    }, [initialIds]);

    const available = selection?.available || [];
    const byId = useMemo(() => {
        const map = {};
        available.forEach((item) => {
            map[item.id] = item;
        });
        return map;
    }, [available]);

    const selectedProjects = selectedIds.map((id) => byId[id]).filter(Boolean);

    const toggle = (projectId) => {
        setSelectedIds((current) => {
            if (current.includes(projectId)) {
                return current.filter((id) => id !== projectId);
            }
            if (current.length >= maxSelected) {
                return current;
            }
            return [...current, projectId];
        });
    };

    const save = () => {
        setSaving(true);
        router.put(
            route('job-analyzer.project-selection.update', analysisId),
            { project_ids: selectedIds },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const reset = () => {
        setSaving(true);
        router.post(
            route('job-analyzer.project-selection.reset', analysisId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    if (!selection) {
        return null;
    }

    if (selection.empty_reason) {
        return (
            <Card>
                <h3 className="font-semibold text-slate-900">Project selection</h3>
                <EmptyState
                    title="No eligible projects"
                    description={selection.empty_reason}
                />
                <Link href={route('projects.index')} className="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:underline">
                    Manage projects
                </Link>
            </Card>
        );
    }

    return (
        <Card className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="font-semibold text-slate-900">Intelligent project selection</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Deterministic ranking from your saved projects and this JD. No Grok call for ranking.
                        Manual selections are preserved until you save or reset.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        disabled={saving}
                        onClick={reset}
                        className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 disabled:opacity-50"
                    >
                        Reset to recommendations
                    </button>
                    <button
                        type="button"
                        disabled={saving}
                        onClick={save}
                        className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                    >
                        {saving ? 'Saving…' : 'Save selection'}
                    </button>
                </div>
            </div>

            {selection.low_confidence && (
                <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Low confidence: few strong technology overlaps were found. Review recommendations carefully.
                </p>
            )}

            {errors.project_ids && (
                <p className="text-sm text-rose-600">{errors.project_ids}</p>
            )}

            <div>
                <h4 className="text-sm font-semibold text-slate-900">Selected for future resume ({selectedProjects.length}/{maxSelected})</h4>
                {selectedProjects.length ? (
                    <div className="mt-3 space-y-2">
                        {selectedProjects.map((project, index) => (
                            <div key={project.id} className="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-medium text-slate-900">{index + 1}. {project.name}</p>
                                        <div className="mt-1 flex flex-wrap gap-2">
                                            <Badge variant="info">{project.relevance_score}/100</Badge>
                                            <Badge variant={project.selection_source === 'manual' || !project.is_recommended ? 'warning' : 'success'}>
                                                {project.is_recommended && selectedIds.indexOf(project.id) < (selection.recommendation_count || 3)
                                                    ? 'Recommended'
                                                    : 'Manual'}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        <button
                                            type="button"
                                            disabled={saving || index === 0}
                                            onClick={() => setSelectedIds((ids) => moveItem(ids, index, index - 1))}
                                            className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 disabled:opacity-40"
                                        >
                                            Up
                                        </button>
                                        <button
                                            type="button"
                                            disabled={saving || index === selectedProjects.length - 1}
                                            onClick={() => setSelectedIds((ids) => moveItem(ids, index, index + 1))}
                                            className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 disabled:opacity-40"
                                        >
                                            Down
                                        </button>
                                        <button
                                            type="button"
                                            disabled={saving}
                                            onClick={() => toggle(project.id)}
                                            className="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="mt-2 text-sm text-slate-500">No projects selected yet.</p>
                )}
            </div>

            <div>
                <h4 className="text-sm font-semibold text-slate-900">Ranked eligible projects</h4>
                <div className="mt-3 space-y-3">
                    {available.map((project) => {
                        const checked = selectedIds.includes(project.id);
                        return (
                            <div key={project.id} className="rounded-xl border border-slate-200 p-3 sm:p-4">
                                <div className="flex flex-wrap items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        checked={checked}
                                        disabled={saving || (!checked && selectedIds.length >= maxSelected)}
                                        onChange={() => toggle(project.id)}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-slate-900">{project.name}</p>
                                            <Badge variant="info">{project.relevance_score}/100</Badge>
                                            {project.is_recommended && <Badge variant="success">Recommended</Badge>}
                                            {project.project_type && <Badge>{project.project_type}</Badge>}
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">{project.explanation}</p>
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {(project.matching_technologies || []).slice(0, 8).map((tech) => (
                                                <Badge key={tech} variant="info">{tech}</Badge>
                                            ))}
                                        </div>
                                        {(project.relevant_features?.length > 0 || project.relevant_responsibilities?.length > 0) && (
                                            <div className="mt-2 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                                <div>
                                                    <p className="font-medium text-slate-700">Relevant features</p>
                                                    <ul className="mt-1 list-disc pl-4">
                                                        {(project.relevant_features || []).slice(0, 3).map((item) => (
                                                            <li key={item}>{item}</li>
                                                        ))}
                                                    </ul>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-slate-700">Relevant responsibilities</p>
                                                    <ul className="mt-1 list-disc pl-4">
                                                        {(project.relevant_responsibilities || []).slice(0, 3).map((item) => (
                                                            <li key={item}>{item}</li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </Card>
    );
}

function ResumeGenerationPanel({ analysis }) {
    const [generating, setGenerating] = useState(false);
    const errors = usePage().props.errors || {};
    const draft = analysis.resume_draft;

    const generate = (confirmReplace = false) => {
        if (draft?.is_completed && !confirmReplace) {
            const ok = window.confirm(
                'A resume draft already exists for this analysis. Replace it with a new draft?',
            );
            if (!ok) {
                return;
            }
            confirmReplace = true;
        }

        setGenerating(true);
        router.post(
            route('job-analyzer.resume-draft.generate', analysis.id),
            { confirm_replace: confirmReplace },
            {
                preserveScroll: true,
                onFinish: () => setGenerating(false),
            },
        );
    };

    return (
        <Card className="space-y-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-slate-900">ATS resume draft</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Uses Master Profile, approved skills, selected projects, experience, and education.
                        Ignored/pending skills and unselected projects are excluded. No PDF export in this phase.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {draft?.is_completed && (
                        <Link
                            href={route('resume-drafts.show', draft.id)}
                            className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600"
                        >
                            View draft
                        </Link>
                    )}
                    <button
                        type="button"
                        disabled={generating || analysis.status !== 'completed'}
                        onClick={() => generate(false)}
                        className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                    >
                        {generating ? 'Generating…' : (draft?.is_completed ? 'Regenerate draft' : 'Generate resume')}
                    </button>
                </div>
            </div>
            {errors.resume && <p className="text-sm text-rose-600">{errors.resume}</p>}
            {errors.confirm_replace && <p className="text-sm text-rose-600">{errors.confirm_replace}</p>}
            {draft && (
                <p className="text-xs text-slate-500">
                    Current draft: {draft.generation_status}
                    {draft.updated_at ? ` · updated ${draft.updated_at}` : ''}
                </p>
            )}
        </Card>
    );
}

export default function Show({ analysis, status }) {
    const result = analysis.analysis || {};
    const review = analysis.skill_review;
    const context = analysis.resume_skill_context;
    const projectSelection = analysis.project_selection;
    const [busyKey, setBusyKey] = useState(null);

    const missingCount = useMemo(() => (
        (review?.missing_required?.length || 0) + (review?.missing_preferred?.length || 0)
    ), [review]);

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">
                        {result.job_title || analysis.job_title || 'Job Analysis'}
                    </h1>
                    <p className="truncate text-sm text-slate-500">
                        Structured extraction · skills review · project ranking
                    </p>
                </div>
            }
        >
            <Head title="Job Analysis Result" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <div className="flex flex-wrap gap-3">
                        <Link href={route('job-analyzer.index')} className="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600">
                            Back to Analyzer
                        </Link>
                        <Badge variant={analysis.status === 'completed' ? 'success' : 'warning'}>
                            {analysis.status}
                        </Badge>
                    </div>

                    {analysis.status === 'failed' && (
                        <Card className="border-amber-200 bg-amber-50">
                            <p className="text-sm text-amber-800">{analysis.error_message || 'Analysis failed.'}</p>
                        </Card>
                    )}

                    {analysis.status === 'completed' && (
                        <>
                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                <Card className="lg:col-span-2 space-y-5">
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-slate-400">Detected role</p>
                                            <p className="mt-1 font-medium text-slate-900">{result.job_title || 'Not specified'}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-slate-400">Category</p>
                                            <p className="mt-1 font-medium text-slate-900">{result.job_category || 'Not specified'}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-slate-400">Work arrangement</p>
                                            <p className="mt-1 font-medium text-slate-900">{result.work_arrangement || 'Not specified'}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-slate-400">Location</p>
                                            <p className="mt-1 font-medium text-slate-900">{result.location || analysis.job_location || 'Not specified'}</p>
                                        </div>
                                    </div>

                                    <SkillList title="Required skills (from JD)" items={result.required_skills} variant="info" />
                                    <SkillList title="Preferred skills (from JD)" items={result.preferred_skills} variant="default" />
                                    <SkillList title="Frameworks & libraries" items={result.frameworks_libraries} />
                                    <SkillList title="Databases" items={result.databases} />
                                    <SkillList title="Tools & platforms" items={result.tools_platforms} />
                                    <SkillList title="Technical keywords" items={result.technical_keywords} />
                                    <BulletList title="Responsibilities" items={result.responsibilities} />
                                    <BulletList title="Education requirements" items={result.education_requirements} />

                                    <div>
                                        <h3 className="text-sm font-semibold text-slate-900">Experience</h3>
                                        <p className="mt-2 text-sm text-slate-600">
                                            {result.experience?.summary
                                                || (
                                                    result.experience?.minimum_years != null
                                                        ? `${result.experience.minimum_years}${result.experience.maximum_years != null ? `–${result.experience.maximum_years}` : '+'} years`
                                                        : 'Not specified in JD'
                                                )}
                                        </p>
                                    </div>
                                </Card>

                                <Card className="space-y-5">
                                    <div>
                                        <h3 className="font-semibold text-slate-900">Skill review</h3>
                                        <p className="mt-1 text-xs text-slate-500">
                                            Original JD requirements stay unchanged. Approvals never call Grok again.
                                        </p>
                                    </div>

                                    <MatchedSkillsSection
                                        title="A. Matched required skills"
                                        items={review?.matched_required}
                                    />
                                    <MissingSkillsSection
                                        title="B. Missing required skills"
                                        items={review?.missing_required}
                                        analysisId={analysis.id}
                                        busyKey={busyKey}
                                        setBusyKey={setBusyKey}
                                    />
                                    <MatchedSkillsSection
                                        title="C. Matched preferred skills"
                                        items={review?.matched_preferred}
                                    />
                                    <MissingSkillsSection
                                        title="D. Missing preferred skills"
                                        items={review?.missing_preferred}
                                        analysisId={analysis.id}
                                        busyKey={busyKey}
                                        setBusyKey={setBusyKey}
                                    />

                                    <p className="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                                        {review?.disclaimer}
                                    </p>

                                    {context && (
                                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
                                            <p className="font-medium text-slate-800">Resume context preview</p>
                                            <p>Master profile skills: {context.verified_master_profile_skills?.length || 0}</p>
                                            <p>Resume-only approvals: {context.approved_resume_only_skills?.length || 0}</p>
                                            <p>Ignored: {context.ignored_skills?.length || 0}</p>
                                            <p>Still pending: {context.pending_skills?.length || 0}</p>
                                            <p className="text-slate-400">Missing rows on this page: {missingCount}</p>
                                        </div>
                                    )}

                                    <Link href={route('skills.index')} className="inline-flex text-sm font-medium text-indigo-600 hover:underline">
                                        Review Master Profile skills
                                    </Link>
                                </Card>
                            </div>

                            <ProjectSelectionPanel
                                analysisId={analysis.id}
                                selection={projectSelection}
                            />

                            <ResumeGenerationPanel analysis={analysis} />

                            <Card>
                                <h3 className="text-sm font-semibold text-slate-900">Original job description</h3>
                                <pre className="mt-3 whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                                    {analysis.job_description}
                                </pre>
                            </Card>
                        </>
                    )}

                    {analysis.status === 'pending' && (
                        <EmptyState title="Analysis pending" description="Refresh shortly if this stays pending." />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
