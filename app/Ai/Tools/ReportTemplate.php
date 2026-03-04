<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReportTemplate implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Извлича шаблона за Експертно Пожаро-Техническо Заключение (ЕПТЗ). Използвай този инструмент когато потребителят потвърди, че иска генериране на ЕПТЗ.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return file_get_contents(resource_path('prompts/forensic-fire-expert-template.md'));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'confirm' => $schema->boolean()->description('Потвърждение за генериране на ЕПТЗ.')->required(),
        ];
    }
}
