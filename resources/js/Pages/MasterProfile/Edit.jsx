import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ profile, status }) {
    const { data, setData, put, processing, errors } = useForm({
        full_name: profile.full_name || '',
        headline: profile.headline || '',
        email: profile.email || '',
        phone: profile.phone || '',
        location: profile.location || '',
        linkedin_url: profile.linkedin_url || '',
        github_url: profile.github_url || '',
        portfolio_url: profile.portfolio_url || '',
        professional_summary: profile.professional_summary || '',
        years_of_experience: profile.years_of_experience ?? '',
        additional_links: profile.additional_links?.length
            ? profile.additional_links
            : [{ label: '', url: '' }],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('master-profile.update'));
    };

    const updateLink = (index, key, value) => {
        const links = data.additional_links.map((link, i) =>
            i === index ? { ...link, [key]: value } : link,
        );
        setData('additional_links', links);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">Master Profile</h1>
                    <p className="truncate text-sm text-slate-500">
                        Verified personal details reused for future resume generation
                    </p>
                </div>
            }
        >
            <Head title="Master Profile" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-3 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    <Card>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <InputLabel htmlFor="full_name" value="Full Name" />
                                    <TextInput id="full_name" className="mt-1 block w-full" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} />
                                    <InputError className="mt-2" message={errors.full_name} />
                                </div>
                                <div className="md:col-span-2">
                                    <InputLabel htmlFor="headline" value="Professional Headline" />
                                    <TextInput id="headline" className="mt-1 block w-full" value={data.headline} onChange={(e) => setData('headline', e.target.value)} />
                                    <InputError className="mt-2" message={errors.headline} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="email" value="Email" />
                                    <TextInput id="email" type="email" className="mt-1 block w-full" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                    <InputError className="mt-2" message={errors.email} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="phone" value="Phone Number" />
                                    <TextInput id="phone" className="mt-1 block w-full" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                    <InputError className="mt-2" message={errors.phone} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="location" value="City / Location" />
                                    <TextInput id="location" className="mt-1 block w-full" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                                    <InputError className="mt-2" message={errors.location} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="years_of_experience" value="Years of Experience" />
                                    <TextInput id="years_of_experience" type="number" min="0" className="mt-1 block w-full" value={data.years_of_experience} onChange={(e) => setData('years_of_experience', e.target.value)} />
                                    <InputError className="mt-2" message={errors.years_of_experience} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="linkedin_url" value="LinkedIn URL" />
                                    <TextInput id="linkedin_url" className="mt-1 block w-full" value={data.linkedin_url} onChange={(e) => setData('linkedin_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.linkedin_url} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="github_url" value="GitHub URL" />
                                    <TextInput id="github_url" className="mt-1 block w-full" value={data.github_url} onChange={(e) => setData('github_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.github_url} />
                                </div>
                                <div className="md:col-span-2">
                                    <InputLabel htmlFor="portfolio_url" value="Portfolio URL" />
                                    <TextInput id="portfolio_url" className="mt-1 block w-full" value={data.portfolio_url} onChange={(e) => setData('portfolio_url', e.target.value)} />
                                    <InputError className="mt-2" message={errors.portfolio_url} />
                                </div>
                                <div className="md:col-span-2">
                                    <InputLabel htmlFor="professional_summary" value="Professional Summary" />
                                    <textarea
                                        id="professional_summary"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={5}
                                        value={data.professional_summary}
                                        onChange={(e) => setData('professional_summary', e.target.value)}
                                    />
                                    <InputError className="mt-2" message={errors.professional_summary} />
                                </div>
                            </div>

                            <div>
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <InputLabel value="Additional Links" />
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        onClick={() => setData('additional_links', [...data.additional_links, { label: '', url: '' }])}
                                    >
                                        Add link
                                    </button>
                                </div>
                                <div className="space-y-3">
                                    {data.additional_links.map((link, index) => (
                                        <div key={index} className="grid grid-cols-1 gap-3 md:grid-cols-5">
                                            <div className="md:col-span-2">
                                                <TextInput
                                                    className="block w-full"
                                                    placeholder="Label"
                                                    value={link.label}
                                                    onChange={(e) => updateLink(index, 'label', e.target.value)}
                                                />
                                            </div>
                                            <div className="md:col-span-3 flex gap-2">
                                                <TextInput
                                                    className="block w-full"
                                                    placeholder="https://"
                                                    value={link.url}
                                                    onChange={(e) => updateLink(index, 'url', e.target.value)}
                                                />
                                                <button
                                                    type="button"
                                                    className="rounded-md border border-slate-200 px-3 text-sm text-slate-500"
                                                    onClick={() =>
                                                        setData(
                                                            'additional_links',
                                                            data.additional_links.filter((_, i) => i !== index),
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Save Master Profile'}
                            </PrimaryButton>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
