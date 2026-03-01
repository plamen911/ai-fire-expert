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
                $text .= $this->extractElementText($element);
            }
        }

        return trim($text);
    }

    /**
     * Split text into chunks of ~2400 chars with ~400 char overlap, splitting on sentence boundaries.
     *
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return [trim($text)];
        }

        $chunks = [];
        $currentChunk = '';
        $chunkSize = 2400;
        $overlap = 400;

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

                // Build overlap from the end of current chunk
                $overlapText = $this->buildOverlap($currentChunk, $overlap);
                $currentChunk = $overlapText.' '.$sentence;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
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
     * Create a Document record from markdown content and save the file to storage.
     * Returns null if a document with the same content already exists (deduplication via file_hash).
     */
    public function createFromMarkdown(string $markdownContent, string $filename, int $uploadedBy): ?Document
    {
        $fileHash = hash('sha256', $markdownContent);

        if (Document::where('file_hash', $fileHash)->exists()) {
            return null;
        }

        $filePath = 'generated/'.$filename;
        Storage::disk('local')->put('private/'.$filePath, $markdownContent);

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
     */
    public function process(Document $document): void
    {
        $document->update(['status' => DocumentStatus::Processing]);

        $fullPath = storage_path('app/private/'.$document->file_path);

        if (str_ends_with($document->file_path, '.md')) {
            $text = file_get_contents($fullPath);
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
     */
    private function extractElementText(mixed $element): string
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

        return $text;
    }

    /**
     * Get approximately $targetLength characters from the end of the text, respecting sentence boundaries.
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
