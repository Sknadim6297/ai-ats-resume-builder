<?php

namespace App\Services\Resume;

use Dompdf\Dompdf;
use Dompdf\Options;

class ResumePdfExporter
{
    public function __construct(
        private readonly ResumeDocumentNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function render(array $content): string
    {
        $document = $this->normalizer->normalize($content);
        $html = view('resumes.ats-universal-pdf', ['document' => $document])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
