import ApplicationLogo from '@/Components/ApplicationLogo';
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
        <div className={'flex h-16 items-center gap-2 border-b border-slate-200 px-3 ' + (collapsed ? 'justify-center' : '')}>
            <Link href={route('dashboard')} className="flex min-w-0 items-center gap-2">
                <ApplicationLogo className="h-8 w-8 shrink-0 fill-current text-indigo-600" />
                {!collapsed && (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-slate-900">
                            AI ATS Resume Builder
                        </p>
                        <p className="truncate text-[11px] text-slate-500">Admin Dashboard</p>
                    </div>
                )}
            </Link>
        </div>
    );

    return (
        <div className="min-h-screen bg-slate-100">
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        className="absolute inset-0 bg-slate-900/40"
                        aria-label="Close sidebar"
                        onClick={() => setMobileOpen(false)}
                    />
                    <aside className="relative z-50 flex h-full w-72 max-w-[85vw] flex-col border-r border-slate-200 bg-white shadow-xl">
                        {brand}
                        <SidebarNav onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <aside
                className={
                    'fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-slate-200 bg-white transition-all duration-200 lg:flex ' +
                    sidebarWidth
                }
            >
                {brand}
                <SidebarNav collapsed={collapsed} />
                <div className="border-t border-slate-200 p-3">
                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        className="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        <svg className={'h-5 w-5 transition ' + (collapsed ? 'rotate-180' : '')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                        {!collapsed && <span>Collapse</span>}
                    </button>
                </div>
            </aside>

            <div className={'transition-all duration-200 ' + contentPad}>
                <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div className="flex h-16 items-center justify-between gap-3 px-3 sm:px-6 lg:px-8">
                        <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                className="inline-flex items-center justify-center rounded-md p-2 text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 lg:hidden"
                                onClick={() => setMobileOpen(true)}
                            >
                                <span className="sr-only">Open sidebar</span>
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div className="min-w-0">{header}</div>
                        </div>

                        <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                            <ProfileSelector />
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <span className="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            className="inline-flex max-w-[10rem] items-center truncate rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 sm:max-w-xs"
                                        >
                                            <span className="truncate">{user.name}</span>
                                            <svg className="-me-0.5 ms-2 h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                </header>

                <main className="pb-10">{children}</main>
            </div>
        </div>
    );
}
