import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import StatusFlash from '@/Components/StatusFlash';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ profile, status }) {
    const editing = Boolean(profile?.id);
    const { data, setData, post, put, processing, errors } = useForm({
        name: profile?.name || '',
        full_name: profile?.full_name || '',
        headline: profile?.headline || '',
        email: profile?.email || '',
        phone: profile?.phone || '',
        location: profile?.location || '',
        linkedin_url: profile?.linkedin_url || '',
        github_url: profile?.github_url || '',
        portfolio_url: profile?.portfolio_url || '',
        professional_summary: profile?.professional_summary || '',
        years_of_experience: profile?.years_of_experience || '',
        is_default: false,
    });

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            put(route('profiles.update', profile.id));
        } else {
            post(route('profiles.store'));
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-900">{editing ? 'Edit Profile' : 'Create Profile'}</h2>}
        >
            <Head title={editing ? 'Edit Profile' : 'Create Profile'} />
            <div className="py-6">
                <div className="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        {[
                            ['name', 'Profile name'],
                            ['full_name', 'Full name'],
                            ['headline', 'Headline / target role'],
                            ['email', 'Email'],
                            ['phone', 'Phone'],
                            ['location', 'Location'],
                            ['linkedin_url', 'LinkedIn URL'],
                            ['github_url', 'GitHub URL'],
                            ['portfolio_url', 'Portfolio URL'],
                        ].map(([key, label]) => (
                            <div key={key}>
                                <InputLabel htmlFor={key} value={label} />
                                <TextInput
                                    id={key}
                                    className="mt-1 block w-full"
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                />
                                <InputError message={errors[key]} className="mt-1" />
                            </div>
                        ))}
                        <div>
                            <InputLabel htmlFor="professional_summary" value="Professional summary" />
                            <textarea
                                id="professional_summary"
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows={5}
                                value={data.professional_summary}
                                onChange={(e) => setData('professional_summary', e.target.value)}
                            />
                            <InputError message={errors.professional_summary} className="mt-1" />
                        </div>
                        <PrimaryButton disabled={processing}>{editing ? 'Save Profile' : 'Create Profile'}</PrimaryButton>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
