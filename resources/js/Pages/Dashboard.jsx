import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PrimaryButton from '@/Components/PrimaryButton';
import StatCard from '@/Components/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const WORKFLOW = [
    'Master Profile',
    'Job Description',
    'AI Analysis',
    'Skill Review',
    'Resume Generation',
    'Download',
];

export default function Dashboard({ stats, completeness, userName }) {
    const hasActivity = (stats?.recent_activity?.length ?? 0) > 0;
    const missing = (completeness?.sections || []).filter((section) => !section.complete);

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">
                        Dashboard
                    </h1>
                    <p className="truncate text-sm text-slate-500">
                        Welcome back{userName ? `, ${userName}` : ''}
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <section className="ai-panel p-6 sm:p-8">
                        <div className="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">
                                    AI workspace
                                </p>
                                <h2 className="mt-2 font-display text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                                    Build a grounded resume that feels designed.
                                </h2>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                                    Your profile stays the source of truth. Analyze a job, review skills, and export an ATS resume with categorized skills.
                                </p>
                            </div>
                            <div className="flex shrink-0 flex-wrap gap-2">
                                <Link href={route('job-analyzer.index')}>
                                    <PrimaryButton>Analyze a job</PrimaryButton>
                                </Link>
                                <Link
                                    href={route('resume-imports.create')}
                                    className="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:border-indigo-200 hover:text-indigo-700"
                                >
                                    Import resume
                                </Link>
                            </div>
                        </div>
                    </section>

                    <Card>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 className="font-semibold text-slate-900">Profile completeness</h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Calculated from saved data only — never hardcoded.
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="ai-gradient-text text-3xl font-semibold">
                                    {completeness?.percentage ?? 0}%
                                </p>
                                <p className="text-xs text-slate-500">
                                    {completeness?.completed ?? 0}/{completeness?.total ?? 0} sections
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-sky-400 transition-all duration-700"
                                style={{ width: `${completeness?.percentage ?? 0}%` }}
                            />
                        </div>
                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                            {(completeness?.sections || []).map((section) => (
                                <Link
                                    key={section.key}
                                    href={route(section.route)}
                                    className={
                                        'rounded-xl border px-3 py-3 transition hover:border-indigo-200 hover:bg-indigo-50/40 ' +
                                        (section.complete
                                            ? 'border-emerald-200 bg-emerald-50/40'
                                            : 'border-amber-200 bg-amber-50/40')
                                    }
                                >
                                    <p className="text-sm font-medium text-slate-900">{section.label}</p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {section.complete ? `Ready · ${section.count}` : 'Incomplete'}
                                    </p>
                                </Link>
                            ))}
                        </div>
                        {missing.length > 0 && (
                            <p className="mt-4 text-sm text-amber-700">
                                Missing: {missing.map((section) => section.label).join(', ')}
                            </p>
                        )}
                    </Card>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Resumes Generated"
                            value={stats.resumes_generated}
                            hint="Available after resume generation ships"
                        />
                        <StatCard
                            label="Job Descriptions Analyzed"
                            value={stats.job_analyses}
                            hint="Available after Job Analyzer ships"
                        />
                        <StatCard
                            label="Saved Skills"
                            value={stats.skills}
                            hint="Active skills on your master profile"
                        />
                        <StatCard
                            label="Projects"
                            value={stats.projects}
                            hint="Active projects you saved"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                        <Card className="lg:col-span-3">
                            <div className="mb-4">
                                <h3 className="font-semibold text-slate-900">Planned workflow</h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Master Profile is available now. Later steps stay informational.
                                </p>
                            </div>
                            <ol className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {WORKFLOW.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
                                    >
                                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-slate-800">{step}</p>
                                            <p className="text-xs text-slate-500">
                                                {index === 0 ? 'Available now' : 'Coming later'}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </Card>

                        <Card className="lg:col-span-2" padding={false}>
                            <div className="border-b border-slate-200 px-5 py-4 sm:px-6">
                                <h3 className="font-semibold text-slate-900">Recent resume activity</h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Live history will appear here once resumes are generated.
                                </p>
                            </div>
                            <div className="p-5 sm:p-6">
                                {hasActivity ? (
                                    <ul className="divide-y divide-slate-100">
                                        {stats.recent_activity.map((item) => (
                                            <li key={item.id} className="py-3 first:pt-0 last:pb-0">
                                                <p className="text-sm font-medium text-slate-900">{item.title}</p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {item.status} · {item.created_at}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <EmptyState
                                        title="No resume activity yet"
                                        description="Resume history stays empty until generation ships in a later phase."
                                    />
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
