<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\DocumentChunk;
use Laravel\Ai\Attributes\MaxSteps;
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

#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5-20251001')]
#[MaxSteps(6)]
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

    public function tools(): iterable
    {
        return [
            SimilaritySearch::usingModel(
                DocumentChunk::class,
                'embedding',
                minSimilarity: 0.65,
                limit: 8
            )->withDescription('Търси подобни документи в базата знания за пожаро-технически експертизи'),
        ];
    }
}
