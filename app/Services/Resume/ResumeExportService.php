<?php

namespace App\Services\Resume;

use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeExportService
{
    public function __construct(
        private readonly ResumeVersionService $versions,
        private readonly ResumePdfExporter $pdf,
        private readonly ResumeDocxExporter $docx,
        private readonly ResumeDocumentNormalizer $normalizer,
    ) {}

    public function downloadPdf(User $user, ResumeVersion $version): StreamedResponse
    {
        $owned = $this->versions->findOwned($user, $version->id);
        $this->ensureExportNotRateLimited($user);

        $binary = $this->pdf->render($owned->structured_content ?? []);
        $filename = $this->safeFilename($owned, 'pdf');

        RateLimiter::hit($this->exportKey($user), 60);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadDocx(User $user, ResumeVersion $version): StreamedResponse
    {
        $owned = $this->versions->findOwned($user, $version->id);
        $this->ensureExportNotRateLimited($user);

        $tmp = tempnam(sys_get_temp_dir(), 'resume_docx_');
        if ($tmp === false) {
            throw ValidationException::withMessages([
                'export' => 'Unable to create a temporary export file.',
            ]);
        }

        $path = $tmp.'.docx';
        @unlink($tmp);

        try {
            $this->docx->renderToPath($owned->structured_content ?? [], $path);
            $binary = file_get_contents($path);
            if ($binary === false) {
                throw ValidationException::withMessages([
                    'export' => 'DOCX export failed.',
                ]);
            }
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        RateLimiter::hit($this->exportKey($user), 60);
        $filename = $this->safeFilename($owned, 'docx');

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function currentVersionForDraft(User $user, ResumeDraft $draft): ResumeVersion
    {
        if ($draft->user_id !== $user->id) {
            abort(403);
        }

        if ($draft->current_version_id) {
            return $this->versions->findOwned($user, (int) $draft->current_version_id);
        }

        $version = ResumeVersion::query()
            ->where('user_id', $user->id)
            ->where('resume_draft_id', $draft->id)
            ->where('is_current', true)
            ->first();

        if (! $version) {
            throw ValidationException::withMessages([
                'export' => 'No saved resume version is available to download.',
            ]);
        }

        return $version;
    }

    private function safeFilename(ResumeVersion $version, string $extension): string
    {
        $name = data_get($version->structured_content, 'contact.full_name', 'resume');
        $slug = Str::slug((string) $name) ?: 'resume';

        return sprintf('%s-v%d.%s', $slug, $version->version_number, $extension);
    }

    private function ensureExportNotRateLimited(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->exportKey($user), 20)) {
            $seconds = RateLimiter::availableIn($this->exportKey($user));
            throw ValidationException::withMessages([
                'export' => "Too many export requests. Try again in {$seconds} seconds.",
            ]);
        }
    }

    private function exportKey(User $user): string
    {
        return 'resume-export:'.$user->id;
    }
}
