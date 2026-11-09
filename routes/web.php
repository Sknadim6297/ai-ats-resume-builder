<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\JobAnalysisController;
use App\Http\Controllers\MasterProfileController;
use App\Http\Controllers\ProfessionalProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResumeDraftController;
use App\Http\Controllers\ResumeImportController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\WorkExperienceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/master-profile', [MasterProfileController::class, 'edit'])->name('master-profile.edit');
    Route::put('/master-profile', [MasterProfileController::class, 'update'])->name('master-profile.update');

    Route::get('/profiles', [ProfessionalProfileController::class, 'index'])->name('profiles.index');
    Route::get('/profiles/create', [ProfessionalProfileController::class, 'create'])->name('profiles.create');
    Route::post('/profiles', [ProfessionalProfileController::class, 'store'])->name('profiles.store');
    Route::get('/profiles/{profile}/edit', [ProfessionalProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('/profiles/{profile}', [ProfessionalProfileController::class, 'update'])->name('profiles.update');
    Route::post('/profiles/{profile}/duplicate', [ProfessionalProfileController::class, 'duplicate'])->name('profiles.duplicate');
    Route::post('/profiles/{profile}/default', [ProfessionalProfileController::class, 'setDefault'])->name('profiles.set-default');
    Route::post('/profiles/{profile}/switch', [ProfessionalProfileController::class, 'switch'])->name('profiles.switch');
    Route::post('/profiles/{profile}/archive', [ProfessionalProfileController::class, 'archive'])->name('profiles.archive');
    Route::post('/profiles/{profile}/restore', [ProfessionalProfileController::class, 'restore'])->name('profiles.restore');

    Route::get('/resume-imports', [ResumeImportController::class, 'create'])->name('resume-imports.create');
    Route::post('/resume-imports', [ResumeImportController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('resume-imports.store');
    Route::get('/resume-imports/{resumeImport}', [ResumeImportController::class, 'show'])->name('resume-imports.show');
    Route::post('/resume-imports/{resumeImport}/extract', [ResumeImportController::class, 'extract'])
        ->middleware('throttle:10,1')
        ->name('resume-imports.extract');
    Route::post('/resume-imports/{resumeImport}/parse', [ResumeImportController::class, 'parse'])
        ->middleware('throttle:5,1')
        ->name('resume-imports.parse');
    Route::put('/resume-imports/{resumeImport}/review', [ResumeImportController::class, 'updateReview'])
        ->middleware('throttle:20,1')
        ->name('resume-imports.review');
    Route::post('/resume-imports/{resumeImport}/commit', [ResumeImportController::class, 'commit'])
        ->middleware('throttle:5,1')
        ->name('resume-imports.commit');
    Route::post('/resume-imports/{resumeImport}/cancel', [ResumeImportController::class, 'cancel'])
        ->name('resume-imports.cancel');

    Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
    Route::post('/skills', [SkillController::class, 'store'])->name('skills.store');
    Route::put('/skills/{skill}', [SkillController::class, 'update'])->name('skills.update');
    Route::delete('/skills/{skill}', [SkillController::class, 'destroy'])->name('skills.destroy');
    Route::patch('/skills/{skill}/toggle', [SkillController::class, 'toggle'])->name('skills.toggle');

    Route::resource('projects', ProjectController::class);

    Route::get('/experience', [WorkExperienceController::class, 'index'])->name('experience.index');
    Route::post('/experience', [WorkExperienceController::class, 'store'])->name('experience.store');
    Route::put('/experience/{experience}', [WorkExperienceController::class, 'update'])->name('experience.update');
    Route::delete('/experience/{experience}', [WorkExperienceController::class, 'destroy'])->name('experience.destroy');

    Route::get('/education', [EducationController::class, 'index'])->name('education.index');
    Route::post('/education', [EducationController::class, 'store'])->name('education.store');
    Route::put('/education/{education}', [EducationController::class, 'update'])->name('education.update');
    Route::delete('/education/{education}', [EducationController::class, 'destroy'])->name('education.destroy');

    Route::get('/job-analyzer', [JobAnalysisController::class, 'index'])->name('job-analyzer.index');
    Route::post('/job-analyzer', [JobAnalysisController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('job-analyzer.store');
    Route::get('/job-analyzer/{jobAnalysis}', [JobAnalysisController::class, 'show'])->name('job-analyzer.show');
    Route::post('/job-analyzer/{jobAnalysis}/skill-decisions', [JobAnalysisController::class, 'decideSkill'])
        ->middleware('throttle:30,1')
        ->name('job-analyzer.skill-decisions.store');
    Route::put('/job-analyzer/{jobAnalysis}/project-selection', [JobAnalysisController::class, 'saveProjectSelection'])
        ->middleware('throttle:30,1')
        ->name('job-analyzer.project-selection.update');
    Route::post('/job-analyzer/{jobAnalysis}/project-selection/reset', [JobAnalysisController::class, 'resetProjectSelection'])
        ->middleware('throttle:30,1')
        ->name('job-analyzer.project-selection.reset');
    Route::post('/job-analyzer/{jobAnalysis}/resume-draft', [ResumeDraftController::class, 'generate'])
        ->middleware('throttle:3,1')
        ->name('job-analyzer.resume-draft.generate');

    Route::get('/resume-builder', [ResumeDraftController::class, 'builder'])->name('resume-builder');
    Route::get('/resumes', [ResumeDraftController::class, 'index'])->name('resumes.index');
    Route::get('/resume-drafts/{resumeDraft}', [ResumeDraftController::class, 'show'])->name('resume-drafts.show');
    Route::get('/resume-drafts/{resumeDraft}/edit', [ResumeDraftController::class, 'edit'])->name('resume-drafts.edit');
    Route::put('/resume-drafts/{resumeDraft}', [ResumeDraftController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('resume-drafts.update');
    Route::get('/resume-drafts/{resumeDraft}/versions/{resumeVersion}', [ResumeDraftController::class, 'showVersion'])
        ->name('resume-drafts.versions.show');
    Route::post('/resume-drafts/{resumeDraft}/versions/{resumeVersion}/restore', [ResumeDraftController::class, 'restore'])
        ->middleware('throttle:20,1')
        ->name('resume-drafts.versions.restore');
    Route::get('/resume-drafts/{resumeDraft}/download/pdf/{resumeVersion?}', [ResumeDraftController::class, 'downloadPdf'])
        ->middleware('throttle:20,1')
        ->name('resume-drafts.download.pdf');
    Route::get('/resume-drafts/{resumeDraft}/download/docx/{resumeVersion?}', [ResumeDraftController::class, 'downloadDocx'])
        ->middleware('throttle:20,1')
        ->name('resume-drafts.download.docx');
});

Route::middleware('auth')->group(function () {
    Route::get('/settings', [ProfileController::class, 'edit'])->name('settings.edit');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
