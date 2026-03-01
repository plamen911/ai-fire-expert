<?php

declare(strict_types=1);

namespace App\Services;

class ReportParser
{
    /**
     * Parse report content and filename from AI response text.
     *
     * @return array{content: string, filename: string}|null
     */
    public function parse(string $text): ?array
    {
        if (! preg_match('/<!-- REPORT_START -->(.*?)<!-- REPORT_END -->/s', $text, $matches)) {
            return null;
        }

        $content = trim($matches[1]);
        $filename = 'Ekspertiza.md';

        if (preg_match('/<!-- REPORT_FILENAME:(.*?) -->/', $content, $fnMatch)) {
            $filename = trim($fnMatch[1]);
            $content = preg_replace('/<!-- REPORT_FILENAME:.*? -->\n?/', '', $content);
        }

        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }
}
