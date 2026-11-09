import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Index({ analyses, limits, status }) {
    const { currentProfile, profileOptions = [] } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        profile_id: currentProfile?.id || '',
        job_title: '',
        company_name: '',
        job_location: '',
        job_url: '',
        job_description: '',
    });

    const length = data.job_description.length;

    const submit = (e) => {
        e.preventDefault();
        post(route('job-analyzer.store'), {
            onSuccess: () => reset('job_description'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Job Analyzer</h1>
                    <p className="truncate text-sm text-slate-500">
                        Paste a JD for structured Grok analysis — profile data is never auto-updated
                    </p>
                </div>
            }
        >
            <Head title="Job Analyzer" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card>
                        <form onSubmit={submit} className="space-y-4">
                            {profileOptions.length > 0 && (
                                <div>
                                    <InputLabel value="Professional profile for this analysis" />
                                    <select
                                        className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.profile_id}
                                        onChange={(e) => setData('profile_id', e.target.value)}
                                    >
                                        {profileOptions.map((profile) => (
                                            <option key={profile.id} value={profile.id}>
                                                {profile.name}
                                                {profile.is_default ? ' (Default)' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Matching and resume generation use this profile, even if you switch later.
                                    </p>
                                    <InputError className="mt-2" message={errors.profile_id} />
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel value="Job Title (optional)" />
                                    <TextInput className="mt-1 block w-full" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                                    <InputError className="mt-2" message={errors.job_title} />
                                </div>
                                <div>
                                    <InputLabel value="Company Name (optional)" />
                                    <TextInput className="mt-1 block w-full" value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} />
                                    <InputError className="mt-2" message={errors.company_name} />
                                </div>
                                <div>
                                    <InputLabel value="Job Location (optional)" />
                                    <TextInput className="mt-1 block w-full" value={data.job_location} onChange={(e) => setData('job_location', e.target.value)} />
                                    <InputError className="mt-2" message={errors.job_location} />
                                </div>
                                <div>
                                    <InputLabel value="Job URL (optional)" />
                                    <TextInput className="mt-1 block w-full" value={data.job_url} onChange={(e) => setData('job_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.job_url} />
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between gap-3">
                                    <InputLabel value="Job Description" />
                                    <span className={'text-xs ' + (length > limits.max ? 'text-red-600' : 'text-slate-500')}>
                                        {length.toLocaleString()} / {limits.max.toLocaleString()}
                                    </span>
                                </div>
                                <textarea
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    rows={12}
                                    value={data.job_description}
                                    onChange={(e) => setData('job_description', e.target.value)}
                                    placeholder="Paste the full job description here…"
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    Minimum {limits.min} characters. The JD is treated as data, not instructions.
                                </p>
                                <InputError className="mt-2" message={errors.job_description} />
                            </div>

                            <PrimaryButton disabled={processing || length < limits.min || length > limits.max}>
                                {processing ? 'Analyzing…' : 'Analyze Job'}
                            </PrimaryButton>
                        </form>
                    </Card>

                    <Card>
                        <h2 className="text-base font-semibold text-slate-900">Recent analyses</h2>
                        {analyses.data.length === 0 ? (
                            <div className="mt-4">
                                <EmptyState title="No analyses yet" description="Your saved JD analyses will appear here." />
                            </div>
                        ) : (
                            <ul className="mt-4 divide-y divide-slate-100">
                                {analyses.data.map((item) => (
                                    <li key={item.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-slate-900">
                                                {item.job_title || 'Untitled job'}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {item.company_name || 'Unknown company'}
                                                {item.profile_name ? ` · ${item.profile_name}` : ''}
                                                {' · '}
                                                {item.created_at}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Badge variant={item.status === 'completed' ? 'success' : item.status === 'failed' ? 'warning' : 'default'}>
                                                {item.status}
                                            </Badge>
                                            <Link href={route('job-analyzer.show', item.id)} className="text-sm text-indigo-600 hover:underline">
                                                View
                                            </Link>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
