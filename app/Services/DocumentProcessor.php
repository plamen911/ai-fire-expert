<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\FailoverableException;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentProcessor
{
    /**
     * Extract plain text from a .docx file.
     */
    public function extractText(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractElementText($element, true);
            }
        }

        return trim($text);
    }

    /**
     * Extract plain text from a PDF file.
     *
     * @throws \Exception
     */
    public function extractTextFromPdf(string $filePath): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($filePath);

        return trim($pdf->getText());
    }

    /**
     * Split text into chunks using recursive structure-aware splitting.
     * For markdown: splits on headings first, then paragraphs, then sentences.
     * For plain text: splits on double newlines, single newlines, then sentences.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, int $chunkSize = 2400, int $overlap = 400): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $isMarkdown = (bool) preg_match('/^#{1,3}\s/m', $text);

        $separators = $isMarkdown
            ? ["\n## ", "\n### ", "\n\n", "\n", 'sentence']
            : ["\n\n", "\n", 'sentence'];

        $rawChunks = $this->chunkRecursive($text, $separators, $chunkSize);

        if ($isMarkdown) {
            $rawChunks = $this->prependHeadingContext($text, $rawChunks);
        }

        return $this->addOverlap($rawChunks, $overlap, $chunkSize);
    }

    /**
     * Generate embeddings for an array of text chunks.
     *
     * @param  array<int, string>  $chunks
     * @return array<int, array<float>>
     *
     * @throws FailoverableException
     */
    public function generateEmbeddings(array $chunks): array
    {
        $response = Embeddings::for($chunks)
            ->dimensions(1536)
            ->generate('openai', 'text-embedding-3-small');

        return $response->embeddings;
    }

    /**
     * Create a Document record from Markdown content and save the file to storage.
     * Returns null if a document with the same content already exists (deduplication via file_hash).
     */
    public function createFromMarkdown(string $markdownContent, string $filename, int $uploadedBy): ?Document
    {
        $fileHash = hash('sha256', $markdownContent);

        if (Document::where('file_hash', $fileHash)->exists()) {
            return null;
        }

        $filePath = 'generated/'.$filename;
        Storage::disk('local')->put($filePath, $markdownContent);

        return Document::create([
            'original_filename' => $filename,
            'file_hash' => $fileHash,
            'file_path' => $filePath,
            'status' => DocumentStatus::Pending,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Process a document: extract text, chunk, embed, and store.
     *
     * @throws \Exception
     * @throws FailoverableException
     */
    public function process(Document $document): void
    {
        $document->update(['status' => DocumentStatus::Processing]);

        $fullPath = storage_path('app/private/'.$document->file_path);

        if (str_ends_with($document->file_path, '.md') || str_ends_with($document->file_path, '.txt')) {
            $text = file_get_contents($fullPath);
        } elseif (str_ends_with($document->file_path, '.pdf')) {
            $text = $this->extractTextFromPdf($fullPath);
        } else {
            $text = $this->extractText($fullPath);
        }

        $chunks = $this->chunk($text);

        if (empty($chunks)) {
            $document->update([
                'status' => DocumentStatus::Failed,
                'error_message' => 'No text could be extracted from the document.',
            ]);

            return;
        }

        $embeddings = $this->generateEmbeddings($chunks);

        $records = [];
        foreach ($chunks as $index => $content) {
            $records[] = [
                'document_id' => $document->id,
                'chunk_index' => $index,
                'content' => $content,
                'embedding' => json_encode($embeddings[$index]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DocumentChunk::insert($records);

        $document->update(['status' => DocumentStatus::Completed]);
    }

    /**
     * Recursively extract text from PhpWord elements.
     * Top-level paragraph elements are separated by double newlines to preserve structure.
     */
    private function extractElementText(mixed $element, bool $isTopLevel = false): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                $text .= $elementText.' ';
            } elseif (is_object($elementText) && method_exists($elementText, 'getText')) {
                $text .= $elementText->getText().' ';
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractElementText($childElement);
            }
        }

        if ($element instanceof TextBreak) {
            $text .= "\n";
        }

        if ($isTopLevel && trim($text) !== '') {
            $text = rtrim($text)."\n\n";
        }

        return $text;
    }

    /**
     * Recursively split text by a hierarchy of separators.
     *
     * @param  array<int, string>  $separators
     * @return array<int, string>
     */
    private function chunkRecursive(string $text, array $separators, int $chunkSize): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $chunkSize) {
            return $text === '' ? [] : [$text];
        }

        if (empty($separators)) {
            return [$text];
        }

        $separator = array_shift($separators);

        if ($separator === 'sentence') {
            return $this->splitBySentences($text, $chunkSize);
        }

        $pieces = explode($separator, $text);

        if (count($pieces) === 1) {
            return $this->chunkRecursive($text, $separators, $chunkSize);
        }

        $chunks = [];
        $currentPiece = '';

        foreach ($pieces as $i => $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }

            // Re-attach the separator prefix (e.g. "\n## ") for heading-based splits
            if ($i > 0 && str_starts_with($separator, "\n#")) {
                $piece = trim($separator).' '.$piece;
            }

            if ($currentPiece === '') {
                $currentPiece = $piece;

                continue;
            }

            $candidate = $currentPiece."\n\n".$piece;

            if (mb_strlen($candidate) <= $chunkSize) {
                $currentPiece = $candidate;
            } else {
                if (mb_strlen($currentPiece) > $chunkSize) {
                    $chunks = array_merge($chunks, $this->chunkRecursive($currentPiece, $separators, $chunkSize));
                } else {
                    $chunks[] = $currentPiece;
                }
                $currentPiece = $piece;
            }
        }

        if ($currentPiece !== '') {
            if (mb_strlen($currentPiece) > $chunkSize) {
                $chunks = array_merge($chunks, $this->chunkRecursive($currentPiece, $separators, $chunkSize));
            } else {
                $chunks[] = $currentPiece;
            }
        }

        return $chunks;
    }

    /**
     * Split text into sentence-based chunks that fit within chunkSize.
     *
     * @return array<int, string>
     */
    private function splitBySentences(string $text, int $chunkSize): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return [trim($text)];
        }

        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if ($currentChunk === '') {
                $currentChunk = $sentence;

                continue;
            }

            $candidate = $currentChunk.' '.$sentence;

            if (mb_strlen($candidate) <= $chunkSize) {
                $currentChunk = $candidate;
            } else {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * For markdown content, prepend the nearest heading context to each chunk.
     *
     * @param  array<int, string>  $chunks
     * @return array<int, string>
     */
    private function prependHeadingContext(string $fullText, array $chunks): array
    {
        $result = [];

        foreach ($chunks as $chunk) {
            if (preg_match('/^#{1,3}\s/m', $chunk)) {
                $result[] = $chunk;

                continue;
            }

            $pos = mb_strpos($fullText, mb_substr($chunk, 0, 100));

            if ($pos === false) {
                $result[] = $chunk;

                continue;
            }

            $textBefore = mb_substr($fullText, 0, $pos);
            $heading = $this->findLastHeading($textBefore);

            if ($heading !== null) {
                $result[] = $heading."\n\n".$chunk;
            } else {
                $result[] = $chunk;
            }
        }

        return $result;
    }

    /**
     * Find the last markdown heading in a block of text.
     */
    private function findLastHeading(string $text): ?string
    {
        if (preg_match_all('/^(#{1,3}\s.+)$/m', $text, $matches)) {
            return end($matches[1]);
        }

        return null;
    }

    /**
     * Add overlap between consecutive chunks for context continuity.
     *
     * @param  array<int, string>  $chunks
     * @return array<int, string>
     */
    private function addOverlap(array $chunks, int $overlap, int $chunkSize): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $result = [$chunks[0]];

        for ($i = 1; $i < count($chunks); $i++) {
            $overlapText = $this->buildOverlap($chunks[$i - 1], $overlap);
            $candidate = $overlapText.' '.$chunks[$i];

            if (mb_strlen($candidate) <= $chunkSize + $overlap) {
                $result[] = $candidate;
            } else {
                $result[] = $chunks[$i];
            }
        }

        return $result;
    }

    /**
     * Get approximately $targetLength characters from the end of the text, respecting word boundaries.
     */
    private function buildOverlap(string $text, int $targetLength): string
    {
        if (mb_strlen($text) <= $targetLength) {
            return $text;
        }

        $tail = mb_substr($text, -$targetLength);
        $spacePos = mb_strpos($tail, ' ');

        if ($spacePos !== false) {
            return mb_substr($tail, $spacePos + 1);
        }

        return $tail;
    }
}
