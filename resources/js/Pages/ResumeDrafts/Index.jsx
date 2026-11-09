import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function changeLabel(type) {
    switch (type) {
        case 'ai_generated':
            return 'AI Generated';
        case 'manually_edited':
            return 'Manually Edited';
        case 'restored':
            return 'Restored';
        default:
            return type || '—';
    }
}

export default function Index({ resumes, filters, status }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [sort, setSort] = useState(filters?.sort || 'updated_desc');

    const applyFilters = (next = {}) => {
        router.get(route('resumes.index'), {
            search: next.search ?? search,
            sort: next.sort ?? sort,
        }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-lg font-semibold text-slate-900">My Resumes</h1>
                    <p className="text-sm text-slate-500">Preview, edit, and download ATS resume versions</p>
                </div>
            }
        >
            <Head title="Resume History" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card className="flex flex-wrap items-end gap-3">
                        <div className="min-w-[12rem] flex-1">
                            <label className="text-xs font-medium text-slate-600">Search</label>
                            <TextInput
                                className="mt-1 w-full"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyFilters({ search });
                                    }
                                }}
                                placeholder="Job title or company"
                            />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-slate-600">Sort</label>
                            <select
                                className="mt-1 rounded-md border-slate-300 text-sm"
                                value={sort}
                                onChange={(e) => {
                                    setSort(e.target.value);
                                    applyFilters({ sort: e.target.value });
                                }}
                            >
                                <option value="updated_desc">Newest updated</option>
                                <option value="updated_asc">Oldest updated</option>
                                <option value="title_asc">Job title A–Z</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            onClick={() => applyFilters()}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white"
                        >
                            Apply
                        </button>
                    </Card>

                    {!resumes?.data?.length ? (
                        <EmptyState
                            title="No resumes yet"
                            description="Analyze a job description, select projects, then generate an ATS resume."
                        />
                    ) : (
                        <div className="space-y-3">
                            {resumes.data.map((item) => (
                                <Card key={item.id} className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-slate-900">{item.job_title}</p>
                                        <p className="text-sm text-slate-500">{item.company_name || 'Company not set'}</p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Badge variant="info">v{item.version_number || 1}</Badge>
                                            <Badge>{changeLabel(item.change_type)}</Badge>
                                            <Badge variant={item.status === 'completed' ? 'success' : 'warning'}>{item.status}</Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-slate-400">Updated {item.updated_at}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Link href={route('resume-drafts.show', item.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700">Preview</Link>
                                        <Link href={route('resume-drafts.edit', item.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700">Edit</Link>
                                        <a href={route('resume-drafts.download.pdf', item.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700">PDF</a>
                                        <a href={route('resume-drafts.download.docx', item.id)} className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700">DOCX</a>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}

                    {resumes?.links?.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {resumes.links.map((link, index) => (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url || '#'}
                                    className={`rounded-md px-3 py-1 text-xs ${link.active ? 'bg-indigo-600 text-white' : 'border border-slate-200 text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
