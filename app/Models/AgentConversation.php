<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\{agent};

class AgentConversation extends Model
{
    /** @use HasFactory<\Database\Factories\AgentConversationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'status',
    ];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function needsTitleGeneration(): bool
    {
        return $this->title === ''
            || ! str_contains($this->title, '_');
    }

    /**
     * Generate and persist an auto-title from the first user/assistant exchange.
     */
    public function generateTitle(string $userMessage, string $assistantResponse): void
    {
        try {
            $context = mb_substr($userMessage, 0, 1000);
            $aiResponse = mb_substr($assistantResponse, 0, 500);

            $prompt = <<<PROMPT
            Анализирай следното съобщение и отговор от разговор за пожаро-техническа експертиза.

            Съобщение от потребителя:
            {$context}

            Отговор от асистента:
            {$aiResponse}

            Извлечи следната информация и я транслитерирай на латиница:
            - Обект (тип сграда, напр. Kashta, Sklad, Garazh, Apartament, Avtoservis)
            - Локация (населено място, напр. Slavyanovo, Sofia, Plovdiv)
            - Дата (YYYY-MM-DD формат)
            - Причина (напр. Nebrezhnost, KasoSaedinenie, Palezh, Malnia, Samozapalvane)

            Върни САМО текст във формат: Obekt_Lokaciya_YYYY-MM-DD_Prichina
            Ако нямаш достатъчно информация за някое поле, използвай "Neizvestno".
            НЕ добавяй нищо друго - само текста във формата.
            PROMPT;

            $response = agent(
                instructions: 'You extract structured data from Bulgarian fire investigation conversations. Always respond with ONLY the requested format, nothing else.',
            )->prompt($prompt, provider: 'openai', model: 'gpt-4o-mini');

            $title = trim((string) $response);
            $title = preg_replace('/[^a-zA-Z0-9_\-]/', '', $title);

            $isAllUnknown = preg_match('/^(Neizvestno_?)+$/', $title);

            if ($title !== '' && str_contains($title, '_') && ! $isAllUnknown) {
                $this->update(['title' => $title]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to generate auto-title for conversation', [
                'conversation_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
