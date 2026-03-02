<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportKnowledgeBase extends Command
{
    protected $signature = 'import:knowledge-base';

    protected $description = 'Import knowledge base .md files from resources/knowledge/ into the document store';

    public function handle(DocumentProcessor $processor): int
    {
        $knowledgePath = resource_path('knowledge');

        if (! File::isDirectory($knowledgePath)) {
            $this->error("Directory not found: {$knowledgePath}");

            return self::FAILURE;
        }

        $files = File::glob($knowledgePath.'/*.md');

        if (empty($files)) {
            $this->warn('No .md files found in resources/knowledge/');

            return self::SUCCESS;
        }

        $admin = User::role('admin')->first();

        if (! $admin) {
            $this->error('No admin user found in the database to use as uploader.');

            return self::FAILURE;
        }

        $this->info('Importing knowledge base files...');

        $imported = 0;
        $skipped = 0;

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $content = File::get($filePath);

            $document = $processor->createFromMarkdown($content, $filename, $admin->id);

            if (! $document) {
                $this->line("  Skipped (already exists): {$filename}");
                $skipped++;

                continue;
            }

            $processor->process($document);
            $this->info("  Imported: {$filename} ({$document->chunks()->count()} chunks)");
            $imported++;
        }

        $this->newLine();
        $this->info("Done. Imported: {$imported}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
