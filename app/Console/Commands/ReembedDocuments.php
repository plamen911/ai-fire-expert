<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Console\Command;

class ReembedDocuments extends Command
{
    protected $signature = 'documents:reembed
                            {--all : Re-embed all completed documents}
                            {--document= : Re-embed a specific document by ID}';

    protected $description = 'Re-process and re-embed documents (useful after changing chunk/embedding settings)';

    public function handle(DocumentProcessor $processor): int
    {
        $documentId = $this->option('document');

        if (! $this->option('all') && ! $documentId) {
            $this->error('Please specify --all or --document={id}');

            return self::FAILURE;
        }

        $query = Document::query();

        if ($documentId) {
            $query->where('id', $documentId);
        } else {
            $query->where('status', DocumentStatus::Completed);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->warn('No documents found to re-embed.');

            return self::SUCCESS;
        }

        $this->info("Re-embedding {$documents->count()} document(s)...");

        $success = 0;
        $failed = 0;

        foreach ($documents as $document) {
            $this->line("  Processing: {$document->original_filename}");

            $document->chunks()->delete();

            try {
                $processor->process($document);
                $this->info("  Done: {$document->original_filename} ({$document->chunks()->count()} chunks)");
                $success++;
            } catch (\Throwable $e) {
                $this->error("  Failed: {$document->original_filename} - {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Success: {$success}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
