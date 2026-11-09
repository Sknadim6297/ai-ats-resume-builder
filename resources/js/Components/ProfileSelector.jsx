import { router, usePage } from '@inertiajs/react';

export default function ProfileSelector() {
    const { currentProfile, profileSelector = [] } = usePage().props;

    if (!currentProfile || profileSelector.length === 0) {
        return null;
    }

    return (
        <label className="flex w-full min-w-0 max-w-full items-center gap-2 text-xs text-slate-500 sm:max-w-[12rem] md:max-w-xs">
            <span className="hidden shrink-0 md:inline">Profile</span>
            <select
                className="min-w-0 w-full truncate rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                value={currentProfile.id}
                onChange={(event) => {
                    const profileId = Number(event.target.value);
                    router.post(route('profiles.switch', profileId), {}, { preserveScroll: true });
                }}
                aria-label="Switch professional profile"
            >
                {profileSelector.map((profile) => (
                    <option key={profile.id} value={profile.id}>
                        {profile.name}
                        {profile.is_default ? ' (Default)' : ''}
                    </option>
                ))}
            </select>
        </label>
    );
}
