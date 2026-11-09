import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyForm = {
    name: '',
    category: 'Tools & Other',
    proficiency: 3,
    years_experience: '',
    last_used_year: '',
    is_primary: false,
    is_active: true,
};

export default function Index({ skills, filters, categories, status }) {
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const applyFilters = (overrides = {}) => {
        router.get(route('skills.index'), { ...filters, ...overrides }, {
            preserveState: true,
            replace: true,
        });
    };

    const startEdit = (skill) => {
        setEditingId(skill.id);
        form.setData({
            name: skill.name,
            category: skill.category || 'Tools & Other',
            proficiency: skill.proficiency || 3,
            years_experience: skill.years_experience ?? '',
            last_used_year: skill.last_used_year ?? '',
            is_primary: !!skill.is_primary,
            is_active: !!skill.is_active,
        });
        form.clearErrors();
    };

    const resetForm = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (editingId) {
            form.put(route('skills.update', editingId), { onSuccess: () => resetForm() });
        } else {
            form.post(route('skills.store'), { onSuccess: () => resetForm() });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Skills</h1>
                    <p className="truncate text-sm text-slate-500">Manage technical skills for future JD matching</p>
                </div>
            }
        >
            <Head title="Skills" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card>
                        <h2 className="text-base font-semibold text-slate-900">
                            {editingId ? 'Edit Skill' : 'Add Skill'}
                        </h2>
                        <form onSubmit={submit} className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel value="Skill Name" />
                                <TextInput className="mt-1 block w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.name} />
                            </div>
                            <div>
                                <InputLabel value="Category" />
                                <select
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={form.data.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                >
                                    {categories.map((category) => (
                                        <option key={category} value={category}>{category}</option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={form.errors.category} />
                            </div>
                            <div>
                                <InputLabel value="Proficiency (1-5)" />
                                <TextInput type="number" min="1" max="5" className="mt-1 block w-full" value={form.data.proficiency} onChange={(e) => form.setData('proficiency', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.proficiency} />
                            </div>
                            <div>
                                <InputLabel value="Years of Experience" />
                                <TextInput type="number" min="0" className="mt-1 block w-full" value={form.data.years_experience} onChange={(e) => form.setData('years_experience', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.years_experience} />
                            </div>
                            <div>
                                <InputLabel value="Last Used Year" />
                                <TextInput type="number" className="mt-1 block w-full" value={form.data.last_used_year} onChange={(e) => form.setData('last_used_year', e.target.value)} />
                                <InputError className="mt-2" message={form.errors.last_used_year} />
                            </div>
                            <div className="flex items-end gap-4">
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" checked={form.data.is_primary} onChange={(e) => form.setData('is_primary', e.target.checked)} />
                                    Primary
                                </label>
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                    Active
                                </label>
                            </div>
                            <div className="md:col-span-2 flex flex-wrap gap-3">
                                <PrimaryButton disabled={form.processing}>
                                    {editingId ? 'Update Skill' : 'Add Skill'}
                                </PrimaryButton>
                                {editingId && (
                                    <button type="button" onClick={resetForm} className="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600">
                                        Cancel
                                    </button>
                                )}
                            </div>
                        </form>
                    </Card>

                    <Card>
                        <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                            <TextInput
                                placeholder="Search skills"
                                value={filters.search}
                                onChange={(e) => applyFilters({ search: e.target.value })}
                            />
                            <select
                                className="rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={filters.category}
                                onChange={(e) => applyFilters({ category: e.target.value })}
                            >
                                <option value="">All categories</option>
                                {categories.map((category) => (
                                    <option key={category} value={category}>{category}</option>
                                ))}
                            </select>
                            <select
                                className="rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={filters.status}
                                onChange={(e) => applyFilters({ status: e.target.value })}
                            >
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        {skills.data.length === 0 ? (
                            <EmptyState title="No skills yet" description="Add verified skills you actually use. Nothing is invented for you." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead>
                                        <tr className="text-left text-slate-500">
                                            <th className="py-2 pr-4">Skill</th>
                                            <th className="py-2 pr-4">Category</th>
                                            <th className="py-2 pr-4">Level</th>
                                            <th className="py-2 pr-4">Status</th>
                                            <th className="py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {skills.data.map((skill) => (
                                            <tr key={skill.id}>
                                                <td className="py-3 pr-4 font-medium text-slate-900">{skill.name}</td>
                                                <td className="py-3 pr-4 text-slate-600">{skill.category}</td>
                                                <td className="py-3 pr-4 text-slate-600">{skill.proficiency}/5</td>
                                                <td className="py-3 pr-4">
                                                    <Badge variant={skill.is_active ? 'success' : 'default'}>
                                                        {skill.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="py-3">
                                                    <div className="flex flex-wrap gap-2">
                                                        <button type="button" className="text-indigo-600 hover:underline" onClick={() => startEdit(skill)}>Edit</button>
                                                        <button type="button" className="text-slate-600 hover:underline" onClick={() => router.patch(route('skills.toggle', skill.id))}>Toggle</button>
                                                        <button
                                                            type="button"
                                                            className="text-red-600 hover:underline"
                                                            onClick={() => {
                                                                if (window.confirm(`Delete skill "${skill.name}"?`)) {
                                                                    router.delete(route('skills.destroy', skill.id));
                                                                }
                                                            }}
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {skills.links?.length > 3 && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {skills.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={
                                            'rounded-md px-3 py-1 text-sm ' +
                                            (link.active ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600') +
                                            (!link.url ? ' pointer-events-none opacity-40' : '')
                                        }
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
