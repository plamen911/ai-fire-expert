<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\ReportTemplate;
use App\Models\DocumentChunk;
use App\Services\QueryExpander;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
use Laravel\Ai\Tools\SimilaritySearch;

#[Provider(Lab::OpenAI)]
#[Model('gpt-5-mini')]
#[MaxTokens(16000)]
#[MaxSteps(4)]
#[Timeout(120)]
class ForensicFireExpert implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        $basePrompt = file_get_contents(resource_path('prompts/forensic-fire-expert.md'));

        $summary = $this->buildConversationSummary();

        if ($summary !== '') {
            $basePrompt .= "\n\n".$summary;
        }

        return $basePrompt;
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

    private function buildConversationSummary(): string
    {
        if (! $this->conversationId) {
            return '';
        }

        $windowIds = DB::table('agent_conversation_messages')
            ->where('conversation_id', $this->conversationId)
            ->orderByDesc('id')
            ->limit($this->maxConversationMessages())
            ->pluck('id');

        $olderUserMessages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $this->conversationId)
            ->whereNotIn('id', $windowIds)
            ->where('role', 'user')
            ->orderBy('id')
            ->pluck('content')
            ->filter(fn (string $c) => ! empty(trim($c)));

        if ($olderUserMessages->isEmpty()) {
            return '';
        }

        $lines = $olderUserMessages
            ->map(fn (string $content) => '- '.Str::limit(trim($content), 500))
            ->implode("\n");

        return "## Вече събрана информация от потребителя\n"
            .'По-долу са отговорите на потребителя от по-ранните етапи на разговора. '
            .'НЕ задавай отново въпроси, на които вече е отговорено. '
            ."Използвай тази информация като вече установени факти по случая.\n"
            ."НАПОМНЯНЕ: Бъди КРАТЪК — максимум 2-3 изречения, по 1-2 въпроса наведнъж.\n\n"
            .$lines;
    }
}
