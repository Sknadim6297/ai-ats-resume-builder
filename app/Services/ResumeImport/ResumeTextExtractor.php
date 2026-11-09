<?php

namespace App\Services\ResumeImport;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class ResumeTextExtractor
{
    private const MAX_CHARS = 80000;

    /**
     * @return array{text:string,method:string}
     */
    public function extract(string $absolutePath, ?string $mimeType, ?string $originalName = null): array
    {
        $name = $originalName ?: basename($absolutePath);
        $ext = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = Str::lower((string) $mimeType);

        if ($ext === 'pdf' || str_contains($mime, 'pdf')) {
            return ['text' => $this->extractPdf($absolutePath), 'method' => 'pdf_parser'];
        }

        if ($ext === 'docx' || str_contains($mime, 'wordprocessingml') || $ext === 'doc') {
            return ['text' => $this->extractDocx($absolutePath), 'method' => 'phpword'];
        }

        throw ValidationException::withMessages([
            'file' => 'Unsupported file type. Upload PDF or DOCX only.',
        ]);
    }

    private function extractPdf(string $path): string
    {
        if (! class_exists(Parser::class)) {
            throw ValidationException::withMessages([
                'file' => 'PDF extraction library is not available.',
            ]);
        }

        try {
            $parser = new Parser;
            $text = $parser->parseFile($path)->getText();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'Could not read this PDF. Try a text-based PDF or DOCX.',
            ]);
        }

        return $this->normalizeText($text, 'pdf');
    }

    private function extractDocx(string $path): string
    {
        try {
            $phpWord = IOFactory::load($path);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText()."\n";
                    }
                }
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'Could not read this DOCX file.',
            ]);
        }

        return $this->normalizeText($text, 'docx');
    }

    private function normalizeText(string $text, string $format): string
    {
        $clean = trim(preg_replace("/\r\n|\r/", "\n", $text) ?? '');
        $clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;

        if ($clean === '') {
            throw ValidationException::withMessages([
                'file' => 'No readable text was found. Scanned/image PDFs require OCR and are not supported yet.',
            ]);
        }

        if (mb_strlen($clean) > self::MAX_CHARS) {
            $clean = mb_substr($clean, 0, self::MAX_CHARS);
        }

        if (mb_strlen($clean) < 80) {
            throw ValidationException::withMessages([
                'file' => 'Extracted text is too short to import. Please upload a fuller resume.',
            ]);
        }

        return $clean;
    }
}
