import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ projects, filters, projectTypes, status }) {
    const applyFilters = (overrides = {}) => {
        router.get(route('projects.index'), { ...filters, ...overrides }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Projects</h1>
                    <p className="truncate text-sm text-slate-500">Structured projects for future AI ranking</p>
                </div>
            }
        >
            <Head title="Projects" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="grid w-full grid-cols-1 gap-3 md:grid-cols-4">
                            <TextInput placeholder="Search" value={filters.search} onChange={(e) => applyFilters({ search: e.target.value })} />
                            <select className="rounded-md border-slate-300" value={filters.type} onChange={(e) => applyFilters({ type: e.target.value })}>
                                <option value="">All types</option>
                                {projectTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                            </select>
                            <TextInput placeholder="Technology" value={filters.technology} onChange={(e) => applyFilters({ technology: e.target.value })} />
                            <select className="rounded-md border-slate-300" value={filters.status} onChange={(e) => applyFilters({ status: e.target.value })}>
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <Link href={route('projects.create')} className="shrink-0">
                            <PrimaryButton>New Project</PrimaryButton>
                        </Link>
                    </div>

                    {projects.data.length === 0 ? (
                        <Card>
                            <EmptyState title="No projects yet" description="Add projects with technologies, features, and responsibilities." />
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {projects.data.map((project) => (
                                <Card key={project.id}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="truncate text-base font-semibold text-slate-900">{project.name}</h2>
                                            <p className="mt-1 text-sm text-slate-500">{project.project_type}</p>
                                        </div>
                                        <Badge variant={project.is_active ? 'success' : 'default'}>
                                            {project.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600">{project.short_description}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {project.technologies.slice(0, 6).map((tech) => (
                                            <span key={tech} className="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{tech}</span>
                                        ))}
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                        <Link href={route('projects.show', project.id)} className="text-indigo-600 hover:underline">View</Link>
                                        <Link href={route('projects.edit', project.id)} className="text-slate-700 hover:underline">Edit</Link>
                                        <button
                                            type="button"
                                            className="text-red-600 hover:underline"
                                            onClick={() => {
                                                if (window.confirm(`Delete project "${project.name}"?`)) {
                                                    router.delete(route('projects.destroy', project.id));
                                                }
                                            }}
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
