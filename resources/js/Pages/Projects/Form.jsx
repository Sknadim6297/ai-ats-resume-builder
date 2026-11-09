import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TagListInput from '@/Components/TagListInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Form({ project, projectTypes }) {
    const editing = !!project;
    const { data, setData, post, put, processing, errors } = useForm({
        name: project?.name || '',
        short_description: project?.short_description || '',
        detailed_description: project?.detailed_description || '',
        project_type: project?.project_type || projectTypes[0],
        github_url: project?.github_url || '',
        live_url: project?.live_url || '',
        start_date: project?.start_date || '',
        end_date: project?.end_date || '',
        is_ongoing: project?.is_ongoing || false,
        is_active: project?.is_active ?? true,
        include_in_resume: project?.include_in_resume ?? true,
        technologies: project?.technologies || [],
        features: project?.features || [],
        responsibilities: project?.responsibilities || [],
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(route('projects.update', project.id));
        } else {
            post(route('projects.store'));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">
                        {editing ? 'Edit Project' : 'Create Project'}
                    </h1>
                </div>
            }
        >
            <Head title={editing ? 'Edit Project' : 'Create Project'} />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <Card>
                        <form onSubmit={submit} className="space-y-5">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <InputLabel value="Project Name" />
                                    <TextInput className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                    <InputError className="mt-2" message={errors.name} />
                                </div>
                                <div className="md:col-span-2">
                                    <InputLabel value="Short Description" />
                                    <TextInput className="mt-1 block w-full" value={data.short_description} onChange={(e) => setData('short_description', e.target.value)} />
                                    <InputError className="mt-2" message={errors.short_description} />
                                </div>
                                <div className="md:col-span-2">
                                    <InputLabel value="Detailed Description" />
                                    <textarea className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows={5} value={data.detailed_description} onChange={(e) => setData('detailed_description', e.target.value)} />
                                    <InputError className="mt-2" message={errors.detailed_description} />
                                </div>
                                <div>
                                    <InputLabel value="Project Type" />
                                    <select className="mt-1 block w-full rounded-md border-slate-300" value={data.project_type} onChange={(e) => setData('project_type', e.target.value)}>
                                        {projectTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                                    </select>
                                    <InputError className="mt-2" message={errors.project_type} />
                                </div>
                                <div>
                                    <InputLabel value="GitHub URL" />
                                    <TextInput className="mt-1 block w-full" value={data.github_url} onChange={(e) => setData('github_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.github_url} />
                                </div>
                                <div>
                                    <InputLabel value="Live URL" />
                                    <TextInput className="mt-1 block w-full" value={data.live_url} onChange={(e) => setData('live_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.live_url} />
                                </div>
                                <div>
                                    <InputLabel value="Start Date" />
                                    <TextInput type="date" className="mt-1 block w-full" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                                    <InputError className="mt-2" message={errors.start_date} />
                                </div>
                                <div>
                                    <InputLabel value="End Date" />
                                    <TextInput type="date" className="mt-1 block w-full" disabled={data.is_ongoing} value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                                    <InputError className="mt-2" message={errors.end_date} />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-4 text-sm text-slate-600">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.is_ongoing} onChange={(e) => setData('is_ongoing', e.target.checked)} /> Ongoing</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Active</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={data.include_in_resume} onChange={(e) => setData('include_in_resume', e.target.checked)} /> Include in resume eligibility</label>
                            </div>

                            <TagListInput label="Tech Stack" values={data.technologies} onChange={(values) => setData('technologies', values)} error={errors.technologies} />
                            <TagListInput label="Features" values={data.features} onChange={(values) => setData('features', values)} error={errors.features} />
                            <TagListInput label="Responsibilities" values={data.responsibilities} onChange={(values) => setData('responsibilities', values)} error={errors.responsibilities} />

                            <div className="flex flex-wrap gap-3">
                                <PrimaryButton disabled={processing}>{editing ? 'Update Project' : 'Create Project'}</PrimaryButton>
                                <Link href={route('projects.index')} className="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600">Cancel</Link>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
