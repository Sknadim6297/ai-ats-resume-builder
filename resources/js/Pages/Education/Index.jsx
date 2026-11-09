import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyForm = {
    institution_name: '',
    degree: '',
    field_of_study: '',
    start_year: '',
    end_year: '',
    grade: '',
    description: '',
};

export default function Index({ educations, status }) {
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
            institution_name: item.institution_name,
            degree: item.degree,
            field_of_study: item.field_of_study || '',
            start_year: item.start_year || '',
            end_year: item.end_year || '',
            grade: item.grade || '',
            description: item.description || '',
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (editingId) {
            form.put(route('education.update', editingId), { onSuccess: () => resetForm() });
        } else {
            form.post(route('education.store'), { onSuccess: () => resetForm() });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Education</h1>
                    <p className="truncate text-sm text-slate-500">Degrees and academic history</p>
                </div>
            }
        >
            <Head title="Education" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-5xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card>
                        <h2 className="text-base font-semibold text-slate-900">{editingId ? 'Edit Education' : 'Add Education'}</h2>
                        <form onSubmit={submit} className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel value="Institution Name" />
                                <TextInput className="mt-1 block w-full" value={form.data.institution_name} onChange={(e) => form.setData('institution_name', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.institution_name} />
                            </div>
                            <div>
                                <InputLabel value="Degree" />
                                <TextInput className="mt-1 block w-full" value={form.data.degree} onChange={(e) => form.setData('degree', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.degree} />
                            </div>
                            <div>
                                <InputLabel value="Field of Study" />
                                <TextInput className="mt-1 block w-full" value={form.data.field_of_study} onChange={(e) => form.setData('field_of_study', e.target.value)} />
                            </div>
                            <div>
                                <InputLabel value="Grade / CGPA" />
                                <TextInput className="mt-1 block w-full" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} />
                            </div>
                            <div>
                                <InputLabel value="Start Year" />
                                <TextInput type="number" className="mt-1 block w-full" value={form.data.start_year} onChange={(e) => form.setData('start_year', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.start_year} />
                            </div>
                            <div>
                                <InputLabel value="End Year" />
                                <TextInput type="number" className="mt-1 block w-full" value={form.data.end_year} onChange={(e) => form.setData('end_year', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.end_year} />
                            </div>
                            <div className="md:col-span-2">
                                <InputLabel value="Description" />
                                <textarea className="mt-1 block w-full rounded-md border-slate-300" rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </div>
                            <div className="md:col-span-2 flex flex-wrap gap-3">
                                <PrimaryButton disabled={form.processing}>{editingId ? 'Update' : 'Add Education'}</PrimaryButton>
                                {editingId && <button type="button" onClick={resetForm} className="rounded-md border border-slate-200 px-4 py-2 text-sm">Cancel</button>}
                            </div>
                        </form>
                    </Card>

                    {educations.length === 0 ? (
                        <Card>
                            <EmptyState title="No education records yet" description="Add institutions and degrees you actually completed." />
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {educations.map((item) => (
                                <Card key={item.id}>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 className="font-semibold text-slate-900">{item.degree}</h3>
                                            <p className="text-sm text-slate-600">{item.institution_name}</p>
                                            <p className="text-xs text-slate-500">
                                                {item.start_year} — {item.end_year || 'Present'}
                                                {item.field_of_study ? ` · ${item.field_of_study}` : ''}
                                                {item.grade ? ` · ${item.grade}` : ''}
                                            </p>
                                        </div>
                                        <div className="flex gap-3 text-sm">
                                            <button type="button" className="text-indigo-600" onClick={() => startEdit(item)}>Edit</button>
                                            <button
                                                type="button"
                                                className="text-red-600"
                                                onClick={() => {
                                                    if (window.confirm('Delete this education record?')) {
                                                        router.delete(route('education.destroy', item.id));
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
