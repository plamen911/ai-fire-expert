<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TechnicalReference implements Tool
{
    /**
     * Mapping of category identifiers to knowledge base filenames.
     *
     * @var array<string, string>
     */
    private const array CATEGORY_FILES = [
        'electrical' => 'electrical-fire-technical-base.md',
        'vehicle' => 'mps-technical-base.md',
        'materials' => 'material-behavior-temperatures.md',
        'fire_origin' => 'fire-origin-technical-base.md',
        'general_causes' => 'general-fire-cause-technical-base.md',
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Извлича пълна техническа справочна информация по категория от базата знания. Използвай вместо SimilaritySearch когато ти трябва цялата техническа справка за определена категория (електрически пожари, МПС, материали, огнище, общи причини).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $category = $request['category'] ?? null;

        if (! is_string($category) || ! isset(self::CATEGORY_FILES[$category])) {
            $validCategories = implode(', ', array_keys(self::CATEGORY_FILES));

            return "Невалидна категория. Валидни категории: {$validCategories}";
        }

        $filename = self::CATEGORY_FILES[$category];
        $path = resource_path('knowledge/'.$filename);

        if (! file_exists($path)) {
            return "Файлът за категория '{$category}' не е намерен.";
        }

        $content = file_get_contents($path);

        return "## Техническа справка: {$filename}\n\n".$content;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->description('Категория на техническата справка: electrical (електрически пожари), vehicle (МПС), materials (поведение на материали при температура), fire_origin (огнище на пожара), general_causes (общи причини за пожари).')
                ->enum(array_keys(self::CATEGORY_FILES))
                ->required(),
        ];
    }
}
