import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function BulletEditor({ bullets, onChange, error }) {
    return (
        <div className="space-y-2">
            {(bullets || []).map((bullet, index) => (
                <div key={index} className="flex gap-2">
                    <textarea
                        className="w-full rounded-md border-slate-300 text-sm"
                        rows={2}
                        value={bullet}
                        onChange={(e) => {
                            const next = [...bullets];
                            next[index] = e.target.value;
                            onChange(next);
                        }}
                    />
                    <button
                        type="button"
                        className="rounded border border-slate-200 px-2 text-xs text-slate-600"
                        onClick={() => onChange(bullets.filter((_, i) => i !== index))}
                    >
                        Remove
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="text-xs font-medium text-indigo-600"
                onClick={() => onChange([...(bullets || []), ''])}
            >
                Add bullet
            </button>
            <InputError message={error} />
        </div>
    );
}

export default function Edit({ draft, status }) {
    const content = draft.content || {};
    const { data, setData, put, processing, errors, isDirty, reset } = useForm({
        contact: {
            full_name: content.contact?.full_name || '',
            headline: content.contact?.headline || '',
            email: content.contact?.email || '',
            phone: content.contact?.phone || '',
            location: content.contact?.location || '',
            linkedin_url: content.contact?.linkedin_url || '',
            github_url: content.contact?.github_url || '',
            portfolio_url: content.contact?.portfolio_url || '',
        },
        professional_summary: content.professional_summary || '',
        technical_skills: content.technical_skills || [],
        experience: (content.experience || []).map((item) => ({ ...item, bullets: item.bullets || [] })),
        projects: (content.projects || []).map((item) => ({ ...item, bullets: item.bullets || [], technologies: item.technologies || [] })),
        education: content.education || [],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('resume-drafts.update', draft.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-lg font-semibold text-slate-900">Edit Resume</h1>
                    <p className="text-sm text-slate-500">
                        Improve wording only. Factual fields stay grounded to Master Profile sources.
                    </p>
                </div>
            }
        >
            <Head title="Edit Resume" />

            <div className="py-6 sm:py-8">
                <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card className="space-y-3 text-sm text-slate-600">
                        <p>{draft.source_snapshot?.note}</p>
                        <p>
                            To change employers, dates, degrees, or available skills, update Master Profile / Skills / Experience,
                            then regenerate the resume from Job Analyzer.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('resume-drafts.show', draft.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium">Preview</Link>
                            <Link href={route('master-profile.edit')} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium">Master Profile</Link>
                        </div>
                    </Card>

                    <Card className="space-y-4">
                        <h2 className="font-semibold text-slate-900">Contact</h2>
                        <p className="text-xs text-slate-500">Contact values are locked to the Master Profile snapshot on save.</p>
                        {['full_name', 'headline', 'email', 'phone', 'location', 'linkedin_url', 'github_url', 'portfolio_url'].map((field) => (
                            <div key={field}>
                                <InputLabel value={field.replaceAll('_', ' ')} />
                                <TextInput
                                    className="mt-1 w-full"
                                    value={data.contact[field] || ''}
                                    onChange={(e) => setData('contact', { ...data.contact, [field]: e.target.value })}
                                    readOnly={['full_name', 'email', 'phone', 'location', 'linkedin_url', 'github_url', 'portfolio_url', 'headline'].includes(field)}
                                />
                                <InputError message={errors[`contact.${field}`]} />
                            </div>
                        ))}
                    </Card>

                    <Card className="space-y-3">
                        <h2 className="font-semibold text-slate-900">Professional Summary</h2>
                        <textarea
                            className="w-full rounded-md border-slate-300 text-sm"
                            rows={5}
                            value={data.professional_summary}
                            onChange={(e) => setData('professional_summary', e.target.value)}
                        />
                        <InputError message={errors.professional_summary} />
                    </Card>

                    <Card className="space-y-3">
                        <h2 className="font-semibold text-slate-900">Technical Skills</h2>
                        <p className="text-xs text-slate-500">Comma-separated. Only approved skills are kept on save.</p>
                        <textarea
                            className="w-full rounded-md border-slate-300 text-sm"
                            rows={3}
                            value={(data.technical_skills || []).join(', ')}
                            onChange={(e) => setData(
                                'technical_skills',
                                e.target.value.split(',').map((item) => item.trim()).filter(Boolean),
                            )}
                        />
                        <InputError message={errors.technical_skills} />
                    </Card>

                    {(data.experience || []).map((job, index) => (
                        <Card key={job.source_id || index} className="space-y-3">
                            <h2 className="font-semibold text-slate-900">Experience: {job.job_title} — {job.company_name}</h2>
                            <p className="text-xs text-slate-500">Company, title, and dates are locked. Edit bullets only.</p>
                            <BulletEditor
                                bullets={job.bullets || []}
                                onChange={(bullets) => {
                                    const next = [...data.experience];
                                    next[index] = { ...next[index], bullets };
                                    setData('experience', next);
                                }}
                                error={errors[`experience.${index}.bullets`]}
                            />
                        </Card>
                    ))}

                    {(data.projects || []).map((project, index) => (
                        <Card key={project.source_id || index} className="space-y-3">
                            <h2 className="font-semibold text-slate-900">Project: {project.name}</h2>
                            <p className="text-xs text-slate-500">Project name/type stay grounded. Technologies must remain within selected project tech.</p>
                            <BulletEditor
                                bullets={project.bullets || []}
                                onChange={(bullets) => {
                                    const next = [...data.projects];
                                    next[index] = { ...next[index], bullets };
                                    setData('projects', next);
                                }}
                                error={errors[`projects.${index}.bullets`]}
                            />
                        </Card>
                    ))}

                    {(data.education || []).map((edu) => (
                        <Card key={edu.source_id} className="space-y-1 text-sm text-slate-700">
                            <h2 className="font-semibold text-slate-900">Education</h2>
                            <p>{edu.degree}{edu.field_of_study ? `, ${edu.field_of_study}` : ''}</p>
                            <p>{edu.institution_name}</p>
                            <p className="text-xs text-slate-500">Education facts are locked to source records.</p>
                        </Card>
                    ))}

                    <InputError message={errors.resume} />

                    <div className="flex flex-wrap gap-3">
                        <PrimaryButton disabled={processing || !isDirty}>
                            {processing ? 'Saving…' : 'Save as new version'}
                        </PrimaryButton>
                        <button
                            type="button"
                            disabled={processing || !isDirty}
                            onClick={() => reset()}
                            className="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 disabled:opacity-50"
                        >
                            Reset unsaved changes
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
