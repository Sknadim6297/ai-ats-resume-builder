import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusFlash from '@/Components/StatusFlash';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ profiles, filters, current_profile_id, status }) {
    const rows = profiles.data || [];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-900">My Profiles</h2>}
        >
            <Head title="My Profiles" />
            <div className="py-6">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-600">
                            Manage independent professional profiles for different target roles.
                        </p>
                        <div className="flex gap-2">
                            <Link
                                href={route('resume-imports.create')}
                                className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700"
                            >
                                Import Resume
                            </Link>
                            <Link
                                href={route('profiles.create')}
                                className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white"
                            >
                                Create Profile
                            </Link>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {rows.map((profile) => (
                            <div key={profile.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h3 className="truncate font-semibold text-slate-900">{profile.name}</h3>
                                        <p className="truncate text-sm text-slate-500">{profile.headline || 'No headline'}</p>
                                    </div>
                                    {profile.is_default && (
                                        <span className="rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                                            Default
                                        </span>
                                    )}
                                </div>
                                <p className="mt-3 text-xs text-slate-500">
                                    {profile.skills_count} skills · {profile.projects_count} projects
                                </p>
                                <p className="text-xs text-slate-400">Updated {profile.updated_at || '—'}</p>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Link href={route('profiles.edit', profile.id)} className="rounded border border-slate-200 px-2 py-1 text-xs">
                                        Edit
                                    </Link>
                                    {profile.id !== current_profile_id && (
                                        <button
                                            type="button"
                                            className="rounded border border-slate-200 px-2 py-1 text-xs"
                                            onClick={() => router.post(route('profiles.switch', profile.id))}
                                        >
                                            Switch
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        className="rounded border border-slate-200 px-2 py-1 text-xs"
                                        onClick={() => router.post(route('profiles.duplicate', profile.id))}
                                    >
                                        Duplicate
                                    </button>
                                    {!profile.is_default && (
                                        <button
                                            type="button"
                                            className="rounded border border-slate-200 px-2 py-1 text-xs"
                                            onClick={() => router.post(route('profiles.set-default', profile.id))}
                                        >
                                            Set Default
                                        </button>
                                    )}
                                    {!profile.is_default && (
                                        <button
                                            type="button"
                                            className="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700"
                                            onClick={() => router.post(route('profiles.archive', profile.id))}
                                        >
                                            Archive
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {rows.length === 0 && (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                            No profiles yet. Create one or import a resume.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
