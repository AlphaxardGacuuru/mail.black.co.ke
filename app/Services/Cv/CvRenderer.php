<?php

namespace App\Services\Cv;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Tab;

/**
 * Draws a CV (CvRevamp::chosen(), or a tailored copy of it) as a PDF to
 * send to employers and a Word file for the user to edit. One column,
 * plain text and standard headings, which application tracking systems
 * read best.
 */
class CvRenderer
{
    /**
     * @param  array{name: string, headline: string, contact: array<int, string>, sections: array<int, array{heading: string, entry: array<string, mixed>}>}  $cv
     */
    public function pdf(array $cv): string
    {
        return Pdf::loadView('cv.document', ['cv' => $cv, 'groups' => $this->groups($cv)])
            ->setPaper('a4')
            // Only the letters used, which keeps an attachment small.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * @param  array{name: string, headline: string, contact: array<int, string>, sections: array<int, array{heading: string, entry: array<string, mixed>}>}  $cv
     */
    public function docx(array $cv): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10.5);

        $page = $word->addSection(['marginTop' => 1000, 'marginBottom' => 900, 'marginLeft' => 1000, 'marginRight' => 1000]);
        // The period sits on the right edge of the line with the title.
        $titleLine = ['tabs' => [new Tab('right', 9900)], 'spaceAfter' => 0];

        $page->addText(e($cv['name']), ['bold' => true, 'size' => 20]);

        collect([$cv['headline'], implode('  |  ', $cv['contact'])])
            ->filter()
            ->each(fn (string $line) => $page->addText(e($line), ['color' => '4B5563']));

        $this->groups($cv)->each(function (Collection $entries, string $heading) use ($page, $titleLine) {
            $page->addText(e(mb_strtoupper($heading)), ['bold' => true, 'size' => 11], [
                'spaceBefore' => 240,
                'spaceAfter' => 80,
                'borderBottomSize' => 6,
                'borderBottomColor' => '9CA3AF',
            ]);

            $entries->each(function (array $entry) use ($page, $titleLine) {
                if ($entry['title'] !== '' || $entry['period'] !== '') {
                    $line = $page->addTextRun($titleLine);
                    $line->addText(e($entry['title']), ['bold' => true]);
                    $line->addText("\t".e($entry['period']), ['color' => '4B5563']);
                }

                if ($entry['subtitle'] !== '') {
                    $page->addText(e($entry['subtitle']), ['color' => '4B5563'], ['spaceAfter' => 40]);
                }

                if ($entry['text'] !== '') {
                    $page->addText(e($entry['text']), [], ['spaceAfter' => 60, 'alignment' => Jc::START]);
                }

                collect($entry['bullets'])
                    ->each(fn (string $bullet) => $page->addListItem(e($bullet), 0, [], null, ['spaceAfter' => 30]));

                $page->addTextBreak(1, ['size' => 4]);
            });
        });

        $path = tempnam(sys_get_temp_dir(), 'cv');

        try {
            IOFactory::createWriter($word, 'Word2007')
                ->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Sections in order, those with the same heading under one heading.
     *
     * @return Collection<string, Collection<int, array{title: string, subtitle: string, period: string, text: string, bullets: array<int, string>}>>
     */
    protected function groups(array $cv): Collection
    {
        return collect($cv['sections'])
            ->groupBy('heading')
            ->map(fn (Collection $sections) => $sections
                ->map(fn (array $section) => [
                    'title' => (string) ($section['entry']['title'] ?? ''),
                    'subtitle' => (string) ($section['entry']['subtitle'] ?? ''),
                    'period' => (string) ($section['entry']['period'] ?? ''),
                    'text' => (string) ($section['entry']['text'] ?? ''),
                    'bullets' => array_values(array_filter($section['entry']['bullets'] ?? [])),
                ]));
    }
}
