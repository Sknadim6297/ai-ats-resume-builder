import Badge from '@/Components/Badge';
import { Link, usePage } from '@inertiajs/react';

const ICONS = {
    dashboard: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
    ),
    profile: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    ),
    skills: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
    ),
    projects: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
    ),
    experience: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    ),
    education: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0112 21.5a12.083 12.083 0 01-6.16-10.922L12 14z" />
    ),
    analyzer: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    ),
    builder: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    ),
    history: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    ),
    settings: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
    ),
};

const NAV_ITEMS = [
    { name: 'Dashboard', route: 'dashboard', icon: 'dashboard', available: true },
    { name: 'My Profiles', route: 'profiles.index', icon: 'profile', available: true },
    { name: 'Import Resume', route: 'resume-imports.create', icon: 'builder', available: true },
    { name: 'Master Profile', route: 'master-profile.edit', icon: 'profile', available: true },
    { name: 'Skills', route: 'skills.index', icon: 'skills', available: true },
    { name: 'Projects', route: 'projects.index', icon: 'projects', available: true },
    { name: 'Work Experience', route: 'experience.index', icon: 'experience', available: true },
    { name: 'Education', route: 'education.index', icon: 'education', available: true },
    { name: 'Job Analyzer', route: 'job-analyzer.index', icon: 'analyzer', available: true, active: ['job-analyzer.*'] },
    { name: 'Resume Builder', route: 'resume-builder', icon: 'builder', available: true, active: ['resume-drafts.*'] },
    { name: 'Resume History', route: 'resumes.index', icon: 'history', available: true, active: ['resumes.index'] },
    { name: 'Settings', route: 'settings.edit', icon: 'settings', available: true },
];

function Icon({ name }) {
    return (
        <svg className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            {ICONS[name]}
        </svg>
    );
}

export default function SidebarNav({ collapsed = false, onNavigate }) {
    const page = usePage();

    return (
        <nav className="sidebar-scroll flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden px-2 py-3">
            <div className="space-y-0.5">
                {NAV_ITEMS.map((item) => {
                    if (!item.available) {
                        return (
                            <div
                                key={item.name}
                                className={
                                    'flex h-10 items-center gap-3 rounded-xl px-2 text-sm text-slate-500 ' +
                                    (collapsed ? 'justify-center' : 'justify-between')
                                }
                                title={`${item.name} — Coming Soon`}
                                aria-disabled="true"
                            >
                                <span className="flex min-w-0 items-center gap-3">
                                    <Icon name={item.icon} />
                                    {!collapsed && <span className="truncate">{item.name}</span>}
                                </span>
                                {!collapsed && <Badge>Soon</Badge>}
                            </div>
                        );
                    }

                    const patterns = item.active || [
                        item.route,
                        ...(item.route === 'settings.edit' ? ['profile.edit'] : []),
                        ...(item.route === 'projects.index' ? ['projects.*'] : []),
                        ...(item.route === 'skills.index' ? ['skills.*'] : []),
                        ...(item.route === 'profiles.index' ? ['profiles.*'] : []),
                        ...(item.route === 'resume-imports.create' ? ['resume-imports.*'] : []),
                    ];
                    const active = patterns.some((pattern) => route().current(pattern));

                    return (
                        <Link
                            key={item.name}
                            href={route(item.route)}
                            onClick={onNavigate}
                            title={collapsed ? item.name : undefined}
                            className={
                                'flex h-10 items-center gap-3 rounded-xl px-2 text-sm font-medium leading-none transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 ' +
                                (collapsed ? 'justify-center ' : '') +
                                (active
                                    ? 'bg-white/10 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]'
                                    : 'text-slate-300 hover:bg-white/5 hover:text-white')
                            }
                        >
                            <span className="flex h-5 w-5 shrink-0 items-center justify-center">
                                <Icon name={item.icon} />
                            </span>
                            {!collapsed && <span className="truncate">{item.name}</span>}
                        </Link>
                    );
                })}
            </div>

            {!collapsed && (
                <div className="mt-3 shrink-0 px-2">
                    <p className="truncate text-[11px] text-slate-500">
                        {page.props.auth.user.email}
                    </p>
                </div>
            )}
        </nav>
    );
}
