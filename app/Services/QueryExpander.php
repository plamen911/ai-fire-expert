<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

use function Laravel\Ai\{agent};

class QueryExpander
{
    /**
     * Expand a search query with related Bulgarian fire investigation terms.
     * Returns the original query plus expanded terms, separated by spaces.
     */
    public function expand(string $query): string
    {
        try {
            $response = agent(
                instructions: 'You are a Bulgarian fire investigation terminology expert. Given a search query, add 3-5 related Bulgarian terms, synonyms, and technical keywords that would help find relevant documents. Return ONLY the expanded query as a single line, no explanations.',
            )->prompt(
                "Разшири следната заявка за търсене с релевантни термини от пожаро-техническата експертиза: {$query}",
                provider: 'openai',
                model: 'gpt-4o-mini',
            );

            $expanded = trim((string) $response);

            if ($expanded !== '' && mb_strlen($expanded) < 500) {
                return $expanded;
            }
        } catch (\Throwable $e) {
            Log::debug('Query expansion failed, using original query', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }

        return $query;
    }
}
