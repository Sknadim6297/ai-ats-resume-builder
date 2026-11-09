import Card from '@/Components/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status, user }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-slate-900">
                        Settings
                    </h1>
                    <p className="truncate text-sm text-slate-500">
                        Manage account details and password
                    </p>
                </div>
            }
        >
            <Head title="Settings" />

            <div className="py-6 sm:py-8">
                <div className="mx-auto max-w-3xl space-y-6 px-3 sm:px-6 lg:px-8">
                    {status === 'account-updated' && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            Account details updated successfully.
                        </div>
                    )}

                    <Card>
                        <div className="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Signed-in account
                            </p>
                            <p className="mt-1 text-sm font-medium text-slate-900">
                                {user?.name}
                            </p>
                            <p className="break-all text-sm text-slate-500">{user?.email}</p>
                        </div>

                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </Card>

                    <Card>
                        <UpdatePasswordForm className="max-w-xl" />
                    </Card>

                    <Card>
                        <DeleteUserForm className="max-w-xl" />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
