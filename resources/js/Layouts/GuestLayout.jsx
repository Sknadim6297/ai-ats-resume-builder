import AiAtmosphere from '@/Components/AiAtmosphere';
import AiMark from '@/Components/AiMark';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, title = 'Sign in', subtitle = 'AI ATS Resume Builder' }) {
    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-slate-950 text-slate-100">
            <AiAtmosphere />
            <div className="relative z-10 mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-10 sm:px-6">
                <div className="mb-8 text-center">
                    <Link href="/" className="inline-flex flex-col items-center gap-4">
                        <AiMark className="scale-125" />
                        <div>
                            <p className="font-display text-2xl font-semibold tracking-tight text-white">{subtitle}</p>
                            <p className="mt-1 text-sm text-slate-300">{title}</p>
                        </div>
                    </Link>
                </div>

                <div className="ai-panel px-6 py-6 text-slate-900 sm:px-8">
                    {children}
                </div>
                <p className="mt-6 text-center text-xs text-slate-400">
                    Grounded resumes. No invented experience.
                </p>
                <p className="mt-2 text-center text-[11px] text-slate-500">
                    Made by Nadeem
                </p>
            </div>
        </div>
    );
}
