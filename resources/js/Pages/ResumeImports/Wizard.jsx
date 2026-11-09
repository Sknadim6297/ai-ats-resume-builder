import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusFlash from '@/Components/StatusFlash';
import { Head, router, useForm } from '@inertiajs/react';

export default function Wizard({ import: importRecord, mergeProfiles = [], status }) {
    const uploadForm = useForm({ resume: null });
    const reviewForm = useForm({
        review_payload: importRecord?.review_payload || {
            personal: {},
            skills: [],
            work_experiences: [],
            projects: [],
            education: [],
            certifications: [],
            uncertain_fields: [],
        },
    });
    const commitForm = useForm({
        merge_strategy: 'new_profile',
        profile_id: mergeProfiles[0]?.id || '',
        new_profile_name: '',
    });

    const personal = reviewForm.data.review_payload?.personal || {};
    const skills = reviewForm.data.review_payload?.skills || [];

    const setPersonal = (key, value) => {
        reviewForm.setData('review_payload', {
            ...reviewForm.data.review_payload,
            personal: { ...personal, [key]: value },
        });
    };

    const onUpload = (event) => {
        event.preventDefault();
        uploadForm.post(route('resume-imports.store'), { forceFormData: true });
    };

    const onSaveReview = (event) => {
        event.preventDefault();
        reviewForm.put(route('resume-imports.review', importRecord.id));
    };

    const onCommit = (event) => {
        event.preventDefault();
        commitForm.post(route('resume-imports.commit', importRecord.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-900">Import Resume</h2>}>
            <Head title="Import Resume" />
            <div className="py-6">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <StatusFlash status={status} />

                    {!importRecord && (
                        <form onSubmit={onUpload} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                            <h3 className="font-semibold text-slate-900">Step 1 — Upload Resume</h3>
                            <p className="text-sm text-slate-600">
                                PDF or DOCX, max 5MB. Text-based files only. Scanned/image PDFs need OCR (not enabled).
                            </p>
                            <input
                                type="file"
                                accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                onChange={(e) => uploadForm.setData('resume', e.target.files?.[0] || null)}
                            />
                            <InputError message={uploadForm.errors.resume} />
                            <PrimaryButton disabled={uploadForm.processing || !uploadForm.data.resume}>
                                Upload
                            </PrimaryButton>
                        </form>
                    )}

                    {importRecord && (
                        <div className="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                            <p>
                                <span className="font-medium text-slate-900">{importRecord.original_filename}</span>
                                {' · '}
                                Status: {importRecord.status}
                                {importRecord.extraction_method ? ` · ${importRecord.extraction_method}` : ''}
                            </p>
                            {importRecord.safe_error && (
                                <p className="mt-2 text-rose-700">{importRecord.safe_error}</p>
                            )}
                        </div>
                    )}

                    {importRecord?.status === 'uploaded' && (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                            <h3 className="font-semibold text-slate-900">Step 2 — Extract Information</h3>
                            <PrimaryButton
                                onClick={() => router.post(route('resume-imports.extract', importRecord.id))}
                            >
                                Extract Text
                            </PrimaryButton>
                        </div>
                    )}

                    {importRecord?.status === 'extracted' && (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-6">
                            <h3 className="font-semibold text-slate-900">Step 2b — AI Structured Parsing</h3>
                            <p className="text-sm text-slate-600">Nothing is saved to a profile until you confirm.</p>
                            <PrimaryButton
                                onClick={() => router.post(route('resume-imports.parse', importRecord.id))}
                            >
                                Parse with AI
                            </PrimaryButton>
                        </div>
                    )}

                    {importRecord?.status === 'review' && (
                        <>
                            <form onSubmit={onSaveReview} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                                <h3 className="font-semibold text-slate-900">Step 3 — Review and Correct</h3>
                                {(reviewForm.data.review_payload?.uncertain_fields || []).length > 0 && (
                                    <div className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                        Uncertain fields:{' '}
                                        {(reviewForm.data.review_payload.uncertain_fields || []).join(', ')}
                                    </div>
                                )}
                                {[
                                    ['full_name', 'Full name'],
                                    ['headline', 'Headline'],
                                    ['email', 'Email'],
                                    ['phone', 'Phone'],
                                    ['location', 'Location'],
                                ].map(([key, label]) => (
                                    <label key={key} className="block text-sm">
                                        {label}
                                        <input
                                            className="mt-1 w-full rounded-md border-slate-300"
                                            value={personal[key] || ''}
                                            onChange={(e) => setPersonal(key, e.target.value)}
                                        />
                                    </label>
                                ))}
                                <label className="block text-sm">
                                    Professional summary
                                    <textarea
                                        className="mt-1 w-full rounded-md border-slate-300"
                                        rows={4}
                                        value={personal.professional_summary || ''}
                                        onChange={(e) => setPersonal('professional_summary', e.target.value)}
                                    />
                                </label>
                                <label className="block text-sm">
                                    Skills (comma-separated)
                                    <input
                                        className="mt-1 w-full rounded-md border-slate-300"
                                        value={skills.join(', ')}
                                        onChange={(e) =>
                                            reviewForm.setData('review_payload', {
                                                ...reviewForm.data.review_payload,
                                                skills: e.target.value
                                                    .split(',')
                                                    .map((s) => s.trim())
                                                    .filter(Boolean),
                                            })
                                        }
                                    />
                                </label>
                                <p className="text-xs text-slate-500">
                                    Imported skills stay unverified until you confirm them on the Skills page.
                                </p>
                                <InputError message={reviewForm.errors.review_payload} />
                                <PrimaryButton disabled={reviewForm.processing}>Save Review Draft</PrimaryButton>
                            </form>

                            <form onSubmit={onCommit} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
                                <h3 className="font-semibold text-slate-900">Step 4–5 — Choose Profile & Confirm</h3>
                                <select
                                    className="w-full rounded-md border-slate-300"
                                    value={commitForm.data.merge_strategy}
                                    onChange={(e) => commitForm.setData('merge_strategy', e.target.value)}
                                >
                                    <option value="new_profile">Import as new profile (safe default)</option>
                                    <option value="merge">Merge into existing profile</option>
                                </select>
                                {commitForm.data.merge_strategy === 'new_profile' ? (
                                    <input
                                        className="w-full rounded-md border-slate-300"
                                        placeholder="New profile name"
                                        value={commitForm.data.new_profile_name}
                                        onChange={(e) => commitForm.setData('new_profile_name', e.target.value)}
                                    />
                                ) : (
                                    <select
                                        className="w-full rounded-md border-slate-300"
                                        value={commitForm.data.profile_id}
                                        onChange={(e) => commitForm.setData('profile_id', e.target.value)}
                                    >
                                        {mergeProfiles.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <InputError
                                    message={
                                        commitForm.errors.merge_strategy ||
                                        commitForm.errors.profile_id ||
                                        commitForm.errors.resume
                                    }
                                />
                                <div className="flex flex-wrap gap-2">
                                    <PrimaryButton disabled={commitForm.processing}>Confirm Import</PrimaryButton>
                                    <button
                                        type="button"
                                        className="rounded-md border border-slate-200 px-3 py-2 text-sm"
                                        onClick={() => router.post(route('resume-imports.cancel', importRecord.id))}
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </>
                    )}

                    {importRecord && ['failed', 'cancelled'].includes(importRecord.status) && (
                        <a href={route('resume-imports.create')} className="text-sm text-indigo-600 hover:underline">
                            Start a new import
                        </a>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
