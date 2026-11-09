import AiAtmosphere from '@/Components/AiAtmosphere';
import AiMark from '@/Components/AiMark';
import Dropdown from '@/Components/Dropdown';
import ProfileSelector from '@/Components/ProfileSelector';
import SidebarNav from '@/Components/SidebarNav';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    useEffect(() => {
        const stored = window.localStorage.getItem('sidebar-collapsed');
        if (stored === '1') {
            setCollapsed(true);
        }
    }, []);

    const toggleCollapsed = () => {
        setCollapsed((prev) => {
            const next = !prev;
            window.localStorage.setItem('sidebar-collapsed', next ? '1' : '0');
            return next;
        });
    };

    const sidebarWidth = collapsed ? 'lg:w-20' : 'lg:w-72';
    const contentPad = collapsed ? 'lg:pl-20' : 'lg:pl-72';

    const brand = (
        <div className={'flex h-16 shrink-0 items-center border-b border-white/10 px-4 ' + (collapsed ? 'justify-center' : '')}>
            <Link href={route('dashboard')} className="flex min-w-0 items-center gap-3">
                <AiMark />
                {!collapsed && (
                    <div className="min-w-0 leading-tight">
                        <p className="truncate font-display text-sm font-semibold text-white">
                            AI ATS Resume
                        </p>
                        <p className="truncate text-[11px] text-indigo-200/80">Live workspace</p>
                    </div>
                )}
            </Link>
        </div>
    );

    return (
        <div className="relative min-h-screen bg-slate-100">
            <AiAtmosphere className="opacity-40" />
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        className="absolute inset-0 bg-slate-900/40"
                        aria-label="Close sidebar"
                        onClick={() => setMobileOpen(false)}
                    />
                    <aside className="relative z-50 flex h-full w-72 max-w-[85vw] flex-col overflow-hidden border-r border-white/10 bg-slate-950 text-slate-100 shadow-2xl">
                        {brand}
                        <SidebarNav onNavigate={() => setMobileOpen(false)} />
                        <p className="shrink-0 border-t border-white/10 px-4 py-3 text-center text-[11px] text-slate-500">
                            Made by Nadeem
                        </p>
                    </aside>
                </div>
            )}

            <aside
                className={
                    'fixed inset-y-0 left-0 z-30 hidden h-screen flex-col overflow-hidden border-r border-white/10 bg-slate-950 text-slate-100 shadow-2xl shadow-indigo-950/40 transition-all duration-200 lg:flex ' +
                    sidebarWidth
                }
            >
                {brand}
                <SidebarNav collapsed={collapsed} />
                <div className="shrink-0 border-t border-white/10 px-2 py-2">
                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        className="flex h-10 w-full items-center justify-center gap-3 rounded-xl px-2 text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        <svg className={'h-5 w-5 transition ' + (collapsed ? 'rotate-180' : '')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                        {!collapsed && <span>Collapse</span>}
                    </button>
                    {!collapsed && (
                        <p className="mt-1 truncate px-2 pb-1 text-center text-[11px] text-slate-500">
                            Made by Nadeem
                        </p>
                    )}
                </div>
            </aside>

            <div className={'relative z-10 transition-all duration-200 ' + contentPad}>
                <header className="sticky top-0 z-20 border-b border-white/60 bg-white/75 backdrop-blur-xl">
                    <div className="flex flex-col gap-2 px-3 py-2 sm:px-6 lg:px-8 lg:py-0">
                        <div className="flex h-12 items-center justify-between gap-2 sm:h-16 sm:gap-3">
                            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden sm:gap-3">
                                <button
                                    type="button"
                                    className="inline-flex shrink-0 items-center justify-center rounded-md p-2 text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 lg:hidden"
                                    onClick={() => setMobileOpen(true)}
                                >
                                    <span className="sr-only">Open sidebar</span>
                                    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                                <div className="min-w-0 flex-1 overflow-hidden">{header}</div>
                            </div>

                            <div className="flex shrink-0 items-center gap-1.5 sm:gap-3">
                                <div className="hidden min-w-0 sm:block">
                                    <ProfileSelector />
                                </div>
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex max-w-[6.5rem] items-center truncate rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 sm:max-w-[10rem] sm:px-3"
                                            >
                                                <span className="truncate">{user.name}</span>
                                                <svg className="-me-0.5 ms-1.5 h-4 w-4 shrink-0 sm:ms-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('settings.edit')}>
                                            Settings
                                        </Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="min-w-0 pb-1 sm:hidden">
                            <ProfileSelector />
                        </div>
                    </div>
                </header>

                <main className="pb-10">{children}</main>
            </div>
        </div>
    );
}
