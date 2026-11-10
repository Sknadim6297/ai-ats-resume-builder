<?php

namespace App\Services\Resume;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class ResumeDocxExporter
{
    public function __construct(
        private readonly ResumeDocumentNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function renderToPath(array $content, string $absolutePath): void
    {
        $document = $this->normalizer->normalize($content);
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
            'marginRight' => 720,
        ]);

        $contact = $document['contact'];
        $section->addText($contact['full_name'], ['bold' => true, 'size' => 18], ['alignment' => Jc::CENTER]);
        if (! empty($contact['headline'])) {
            $section->addText((string) $contact['headline'], ['size' => 11], ['alignment' => Jc::CENTER]);
        }

        $meta = array_values(array_filter([
            $contact['email'] ?? null,
            $contact['phone'] ?? null,
            $contact['location'] ?? null,
        ]));
        if ($meta !== []) {
            $section->addText(implode(' · ', $meta), ['size' => 9], ['alignment' => Jc::CENTER]);
        }

        $links = array_values(array_filter([
            $contact['linkedin_url'] ?? null,
            $contact['github_url'] ?? null,
            $contact['portfolio_url'] ?? null,
        ]));
        if ($links !== []) {
            $section->addText(implode(' · ', $links), ['size' => 9], ['alignment' => Jc::CENTER]);
        }

        $this->heading($section, 'Professional Summary');
        $section->addText($document['professional_summary'], ['size' => 11]);

        $this->heading($section, 'Technical Skills');
        if (! empty($document['skill_groups'])) {
            foreach ($document['skill_groups'] as $group) {
                $section->addText(
                    $group['category'].': '.implode(', ', $group['skills']),
                    ['size' => 10]
                );
            }
        } else {
            $section->addText(implode(', ', $document['technical_skills']), ['size' => 10]);
        }

        if ($document['experience'] !== []) {
            $this->heading($section, 'Professional Experience');
            foreach ($document['experience'] as $job) {
                $dates = $this->formatRange($job['start_date'] ?? null, $job['end_date'] ?? null, (bool) $job['is_current']);
                $section->addText(
                    $job['company_name'].' — '.$job['job_title'],
                    ['bold' => true, 'size' => 11]
                );
                $line = trim(implode(' | ', array_filter([
                    $job['location'] ?? null,
                    $dates,
                    $job['employment_type'] ?? null,
                ])));
                if ($line !== '') {
                    $section->addText($line, ['size' => 9, 'italic' => true]);
                }
                foreach ($job['bullets'] as $bullet) {
                    $section->addListItem($bullet, 0, ['size' => 10]);
                }
            }
        }

        if ($document['projects'] !== []) {
            $this->heading($section, 'Selected Projects');
            foreach ($document['projects'] as $project) {
                $title = $project['name'];
                if (! empty($project['project_type'])) {
                    $title .= ' ('.$project['project_type'].')';
                }
                $section->addText($title, ['bold' => true, 'size' => 11]);
                if ($project['technologies'] !== []) {
                    $section->addText('Tech Stack: '.implode(', ', $project['technologies']), ['size' => 9]);
                }
                foreach ($project['bullets'] as $bullet) {
                    $section->addListItem($bullet, 0, ['size' => 10]);
                }
            }
        }

        if ($document['education'] !== []) {
            $this->heading($section, 'Education');
            foreach ($document['education'] as $edu) {
                $years = trim(implode(' – ', array_filter([$edu['start_year'] ?? null, $edu['end_year'] ?? null])));
                $institution = $edu['institution_name'].($years !== '' ? ' | '.$years : '');
                $section->addText($institution, ['bold' => true, 'size' => 11]);
                $degree = $edu['degree'];
                if (! empty($edu['field_of_study'])) {
                    $degree .= ', '.$edu['field_of_study'];
                }
                $section->addText($degree, ['size' => 10]);
                if (! empty($edu['grade'])) {
                    $section->addText((string) $edu['grade'], ['size' => 9]);
                }
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($absolutePath);
    }

    private function heading($section, string $title): void
    {
        $section->addTextBreak(1);
        $section->addText(mb_strtoupper($title), ['bold' => true, 'size' => 12]);
        $section->addLine(['weight' => 1, 'width' => 450, 'height' => 0]);
    }

    private function formatRange(?string $start, ?string $end, bool $isCurrent): string
    {
        $startLabel = $start ? substr($start, 0, 7) : '';
        $endLabel = $isCurrent ? 'Present' : ($end ? substr($end, 0, 7) : '');

        return trim($startLabel.($startLabel !== '' || $endLabel !== '' ? ' – ' : '').$endLabel);
    }
}
