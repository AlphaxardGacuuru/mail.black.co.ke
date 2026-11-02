<?php

namespace App\Http\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * The text of an uploaded cover letter (PDF, Word or plain text). Only the
 * text is kept; the user can correct it in the form afterwards.
 */
class CoverLetterService
{
    public const MAX_CHARACTERS = 5000;

    public function text(UploadedFile $file): string
    {
        try {
            $raw = match (strtolower($file->getClientOriginalExtension())) {
                'pdf' => (new Parser)
                    ->parseFile($file->getRealPath())
                    ->getText(),
                'docx' => $this->wordText($file->getRealPath()),
                default => (string) file_get_contents($file->getRealPath()),
            };
        } catch (Throwable) {
            $raw = '';
        }

        $text = Str::of(mb_convert_encoding($raw, 'UTF-8', 'UTF-8'))
            ->replace("\r\n", "\n")
            ->replaceMatches("/[ \t\x{00A0}]+/u", ' ')
            ->replaceMatches("/ *\n */", "\n")
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim();

        if ($text->isEmpty()) {
            throw new RuntimeException("Couldn't read any text from that file. Paste your cover letter instead.");
        }

        if ($text->length() > self::MAX_CHARACTERS) {
            throw new RuntimeException('That cover letter is too long. Keep it under '.number_format(self::MAX_CHARACTERS).' characters.');
        }

        return $text->toString();
    }

    /**
     * A .docx is a zip; the text is in word/document.xml, one <w:p> per
     * paragraph.
     */
    protected function wordText(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return Str::of($xml)
            ->replaceMatches('#<w:tab/>#', "\t")
            ->replaceMatches('#<w:br[^>]*/>#', "\n")
            ->replaceMatches('#</w:p>#', "\n")
            ->stripTags()
            ->pipe(fn ($text) => html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8'))
            ->toString();
    }
}
