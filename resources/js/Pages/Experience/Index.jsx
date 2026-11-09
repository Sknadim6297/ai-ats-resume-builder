import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TagListInput from '@/Components/TagListInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyForm = {
    company_name: '',
    job_title: '',
    employment_type: 'Full-time',
    location: '',
    start_date: '',
    end_date: '',
    is_current: false,
    responsibilities: [],
    achievements: [],
    technologies: [],
};

export default function Index({ experiences, status, employmentTypes }) {
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const resetForm = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
    };

    const startEdit = (item) => {
        setEditingId(item.id);
        form.setData({
            company_name: item.company_name,
            job_title: item.job_title,
            employment_type: item.employment_type,
            location: item.location || '',
            start_date: item.start_date || '',
            end_date: item.end_date || '',
            is_current: !!item.is_current,
            responsibilities: item.responsibilities || [],
            achievements: item.achievements || [],
            technologies: item.technologies || [],
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (editingId) {
            form.put(route('experience.update', editingId), { onSuccess: () => resetForm() });
        } else {
            form.post(route('experience.store'), { onSuccess: () => resetForm() });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Work Experience</h1>
                    <p className="truncate text-sm text-slate-500">Chronological roles with structured details</p>
                </div>
            }
        >
            <Head title="Work Experience" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-5xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card>
                        <h2 className="text-base font-semibold text-slate-900">{editingId ? 'Edit Experience' : 'Add Experience'}</h2>
                        <form onSubmit={submit} className="mt-4 space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel value="Company Name" />
                                    <TextInput className="mt-1 block w-full" value={form.data.company_name} onChange={(e) => form.setData('company_name', e.target.value)} />
                                    <InputError className="mt-2" message={form.errors.company_name} />
                                </div>
                                <div>
                                    <InputLabel value="Job Title" />
                                    <TextInput className="mt-1 block w-full" value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} />
                                    <InputError className="mt-2" message={form.errors.job_title} />
                                </div>
                                <div>
                                    <InputLabel value="Employment Type" />
                                    <select className="mt-1 block w-full rounded-md border-slate-300" value={form.data.employment_type} onChange={(e) => form.setData('employment_type', e.target.value)}>
                                        {employmentTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Location" />
                                    <TextInput className="mt-1 block w-full" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                                </div>
                                <div>
                                    <InputLabel value="Start Date" />
                                    <TextInput type="date" className="mt-1 block w-full" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                                    <InputError className="mt-2" message={form.errors.start_date} />
                                </div>
                                <div>
                                    <InputLabel value="End Date" />
                                    <TextInput type="date" className="mt-1 block w-full" disabled={form.data.is_current} value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                                    <InputError className="mt-2" message={form.errors.end_date} />
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" checked={form.data.is_current} onChange={(e) => form.setData('is_current', e.target.checked)} />
                                Currently working here
                            </label>
                            <TagListInput label="Responsibilities" values={form.data.responsibilities} onChange={(values) => form.setData('responsibilities', values)} />
                            <TagListInput label="Achievements" values={form.data.achievements} onChange={(values) => form.setData('achievements', values)} />
                            <TagListInput label="Technologies Used" values={form.data.technologies} onChange={(values) => form.setData('technologies', values)} />
                            <div className="flex flex-wrap gap-3">
                                <PrimaryButton disabled={form.processing}>{editingId ? 'Update' : 'Add Experience'}</PrimaryButton>
                                {editingId && <button type="button" onClick={resetForm} className="rounded-md border border-slate-200 px-4 py-2 text-sm">Cancel</button>}
                            </div>
                        </form>
                    </Card>

                    {experiences.length === 0 ? (
                        <Card>
                            <EmptyState title="No work experience yet" description="Add only real roles. Dates and achievements are never invented." />
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {experiences.map((item) => (
                                <Card key={item.id}>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 className="font-semibold text-slate-900">{item.job_title}</h3>
                                            <p className="text-sm text-slate-600">{item.company_name} · {item.employment_type}</p>
                                            <p className="text-xs text-slate-500">
                                                {item.start_date} — {item.is_current ? 'Present' : item.end_date || 'N/A'}
                                                {item.location ? ` · ${item.location}` : ''}
                                            </p>
                                        </div>
                                        <div className="flex gap-3 text-sm">
                                            <button type="button" className="text-indigo-600" onClick={() => startEdit(item)}>Edit</button>
                                            <button
                                                type="button"
                                                className="text-red-600"
                                                onClick={() => {
                                                    if (window.confirm('Delete this work experience?')) {
                                                        router.delete(route('experience.destroy', item.id));
                                                    }
                                                }}
                                            >
                                                Delete
                                            </button>
                                        </div>
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
