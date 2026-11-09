import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function Section({ title, items }) {
    if (!items?.length) {
        return null;
    }

    return (
        <div>
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                {items.map((item) => <li key={item}>{item}</li>)}
            </ul>
        </div>
    );
}

export default function Show({ project }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">{project.name}</h1>
                    <p className="truncate text-sm text-slate-500">{project.project_type}</p>
                </div>
            }
        >
            <Head title={project.name} />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap gap-3">
                        <Link href={route('projects.edit', project.id)} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Edit</Link>
                        <Link href={route('projects.index')} className="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600">Back</Link>
                    </div>

                    <Card className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant={project.is_active ? 'success' : 'default'}>{project.is_active ? 'Active' : 'Inactive'}</Badge>
                            {project.include_in_resume && <Badge variant="info">Resume eligible</Badge>}
                            {project.is_ongoing && <Badge variant="warning">Ongoing</Badge>}
                        </div>
                        <p className="text-sm text-slate-700">{project.short_description}</p>
                        {project.detailed_description && (
                            <p className="whitespace-pre-wrap text-sm text-slate-600">{project.detailed_description}</p>
                        )}
                        <Section title="Technologies" items={project.technologies} />
                        <Section title="Features" items={project.features} />
                        <Section title="Responsibilities" items={project.responsibilities} />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
