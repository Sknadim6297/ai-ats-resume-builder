const MESSAGES = {
    'master-profile-saved': 'Master profile saved.',
    'skill-created': 'Skill added.',
    'skill-updated': 'Skill updated.',
    'skill-deleted': 'Skill removed.',
    'skill-toggled': 'Skill status updated.',
    'project-created': 'Project created.',
    'project-updated': 'Project updated.',
    'project-deleted': 'Project deleted.',
    'experience-created': 'Work experience added.',
    'experience-updated': 'Work experience updated.',
    'experience-deleted': 'Work experience deleted.',
    'education-created': 'Education record added.',
    'education-updated': 'Education record updated.',
    'education-deleted': 'Education record deleted.',
    'account-updated': 'Account details updated successfully.',
    'job-analysis-completed': 'Job analysis completed.',
    'skill-decision-saved': 'Skill decision saved.',
    'project-selection-saved': 'Project selection saved.',
    'project-selection-reset': 'Project selection reset to recommendations.',
    'resume-draft-generated': 'Resume draft generated.',
    'resume-draft-saved': 'Resume edits saved as a new version.',
    'resume-version-restored': 'Earlier version restored as the current resume.',
    'profile-created': 'Professional profile created.',
    'profile-updated': 'Professional profile updated.',
    'profile-duplicated': 'Profile duplicated.',
    'profile-default-set': 'Default profile updated.',
    'profile-switched': 'Switched active profile.',
    'profile-archived': 'Profile archived.',
    'profile-restored': 'Profile restored.',
    'resume-import-uploaded': 'Resume uploaded.',
    'resume-import-extracted': 'Resume text extracted.',
    'resume-import-parsed': 'Resume parsed — review before saving.',
    'resume-import-review-saved': 'Import review saved.',
    'resume-import-completed': 'Resume import saved to profile.',
    'resume-import-cancelled': 'Import cancelled.',
};

export default function StatusFlash({ status }) {
    if (!status || !MESSAGES[status]) {
        return null;
    }

    return (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {MESSAGES[status]}
        </div>
    );
}
