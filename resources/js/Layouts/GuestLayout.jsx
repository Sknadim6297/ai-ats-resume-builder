import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, title = 'Sign in', subtitle = 'AI ATS Resume Builder' }) {
    return (
        <div className="flex min-h-screen flex-col bg-slate-100">
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-10 sm:px-6">
                <div className="mb-8 text-center">
                    <Link href="/" className="inline-flex flex-col items-center gap-3">
                        <ApplicationLogo className="h-12 w-12 fill-current text-indigo-600" />
                        <div>
                            <p className="text-lg font-semibold text-slate-900">{subtitle}</p>
                            <p className="text-sm text-slate-500">{title}</p>
                        </div>
                    </Link>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
