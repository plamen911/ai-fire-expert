<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\DocumentChunk;
use App\Services\QueryExpander;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use App\Ai\Tools\ReportTemplate;
use Laravel\Ai\Tools\SimilaritySearch;

#[Provider(Lab::Groq)]
#[Model('qwen/qwen3-32b')]
#[MaxTokens(8000)]
#[MaxSteps(4)]
#[Timeout(120)]
class ForensicFireExpert implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        $user = $this->conversationParticipant();

        return str_replace(
            ['{{EXPERT_NAME}}', '{{EXPERT_POSITION}}'],
            [$user?->name ?? 'Непознат', $user?->position ?? ''],
            file_get_contents(resource_path('prompts/forensic-fire-expert.md'))
        );
    }

    protected function maxConversationMessages(): int
    {
        return 4;
    }

    public function tools(): iterable
    {
        return [
            new SimilaritySearch(using: function (string $query) {
                // Expand the query with related terms for keyword search
                $expandedQuery = app(QueryExpander::class)->expand($query);

                // Semantic search (uses the original query — embeddings handle semantics)
                $semanticResults = DocumentChunk::query()
                    ->with('document:id,original_filename')
                    ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.55)
                    ->limit(10)
                    ->get();

                // Keyword search with the expanded query (uses tsvector on PostgreSQL, LIKE fallback on SQLite)
                $keywordResults = DocumentChunk::query()
                    ->with('document:id,original_filename')
                    ->keywordSearch($expandedQuery)
                    ->limit(5)
                    ->get();

                // Merge and deduplicate, semantic results take priority
                $merged = $semanticResults
                    ->merge($keywordResults)
                    ->unique('id')
                    ->values();

                try {
                    $results = $merged->rerank('content', $query, limit: 4);
                } catch (\Throwable) {
                    $results = $merged->take(4);
                }

                return $results->map(fn (DocumentChunk $chunk) => [
                    'content' => $chunk->content,
                    'source' => $chunk->document?->original_filename,
                ]);
            }),
            new ReportTemplate,
        ];
    }
}
