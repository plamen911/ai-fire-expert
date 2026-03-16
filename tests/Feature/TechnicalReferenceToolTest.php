<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Tools\TechnicalReference;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class TechnicalReferenceToolTest extends TestCase
{
    private TechnicalReference $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tool = new TechnicalReference;
    }

    public function test_description_is_in_bulgarian(): void
    {
        $this->assertStringContainsString('техническа справочна информация', $this->tool->description());
    }

    public function test_schema_defines_required_category_with_enum(): void
    {
        $schema = $this->tool->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('category', $schema);

        $schemaArray = $schema['category']->toArray();
        $this->assertContains('electrical', $schemaArray['enum']);
        $this->assertContains('vehicle', $schemaArray['enum']);
        $this->assertContains('materials', $schemaArray['enum']);
        $this->assertContains('fire_origin', $schemaArray['enum']);
        $this->assertContains('general_causes', $schemaArray['enum']);
    }

    public function test_electrical_category_returns_electrical_content(): void
    {
        $request = new Request(['category' => 'electrical']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('electrical-fire-technical-base.md', $result);
        $this->assertStringNotContainsString('Невалидна категория', $result);
    }

    public function test_vehicle_category_returns_vehicle_content(): void
    {
        $request = new Request(['category' => 'vehicle']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('mps-technical-base.md', $result);
    }

    public function test_materials_category_returns_materials_content(): void
    {
        $request = new Request(['category' => 'materials']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('material-behavior-temperatures.md', $result);
    }

    public function test_fire_origin_category_returns_fire_origin_content(): void
    {
        $request = new Request(['category' => 'fire_origin']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('fire-origin-technical-base.md', $result);
    }

    public function test_general_causes_category_returns_general_causes_content(): void
    {
        $request = new Request(['category' => 'general_causes']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('general-fire-cause-technical-base.md', $result);
    }

    public function test_invalid_category_returns_error_message(): void
    {
        $request = new Request(['category' => 'nonexistent']);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('Невалидна категория', $result);
        $this->assertStringContainsString('electrical', $result);
        $this->assertStringContainsString('vehicle', $result);
    }

    public function test_missing_category_returns_error_message(): void
    {
        $request = new Request;

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('Невалидна категория', $result);
    }

    public function test_each_category_returns_non_empty_content(): void
    {
        $categories = ['electrical', 'vehicle', 'materials', 'fire_origin', 'general_causes'];

        foreach ($categories as $category) {
            $request = new Request(['category' => $category]);
            $result = (string) $this->tool->handle($request);

            $this->assertStringContainsString('Техническа справка:', $result, "Category '{$category}' should return technical reference header.");
            // Content should be longer than just the header
            $this->assertGreaterThan(100, mb_strlen($result), "Category '{$category}' should return substantial content.");
        }
    }
}
