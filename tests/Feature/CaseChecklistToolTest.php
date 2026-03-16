<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Tools\CaseChecklist;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CaseChecklistToolTest extends TestCase
{
    private CaseChecklist $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tool = new CaseChecklist;
    }

    public function test_description_is_in_bulgarian(): void
    {
        $this->assertStringContainsString('Валидира', $this->tool->description());
    }

    public function test_schema_defines_all_six_fields(): void
    {
        $schema = $this->tool->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('date', $schema);
        $this->assertArrayHasKey('location', $schema);
        $this->assertArrayHasKey('object_type', $schema);
        $this->assertArrayHasKey('circumstances', $schema);
        $this->assertArrayHasKey('suspected_cause', $schema);
        $this->assertArrayHasKey('expert_questions', $schema);
    }

    public function test_all_fields_empty_shows_all_missing(): void
    {
        $result = (string) $this->tool->handle(new Request);

        $this->assertStringContainsString('❌ Дата и час на пожара', $result);
        $this->assertStringContainsString('❌ Местоположение', $result);
        $this->assertStringContainsString('❌ Обект на пожара', $result);
        $this->assertStringContainsString('❌ Обстоятелства по делото', $result);
        $this->assertStringContainsString('❌ Предполагаема причина', $result);
        $this->assertStringContainsString('❌ Поставени въпроси', $result);
        $this->assertStringContainsString('0/6', $result);
    }

    public function test_filled_fields_are_marked_as_complete(): void
    {
        $request = new Request([
            'date' => '15.03.2026 14:30',
            'location' => 'гр. Плевен, ул. Дойран 5',
            'object_type' => 'жилищна сграда',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('✅ Дата и час на пожара', $result);
        $this->assertStringContainsString('✅ Местоположение', $result);
        $this->assertStringContainsString('✅ Обект на пожара', $result);
        $this->assertStringContainsString('❌ Обстоятелства по делото', $result);
        $this->assertStringContainsString('3/6', $result);
    }

    public function test_arson_cause_returns_arson_specific_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'палеж',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('ускорители', $result);
        $this->assertStringContainsString('изолирани огнища', $result);
    }

    public function test_short_circuit_cause_returns_electrical_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'късо съединение',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('електрическата инсталация', $result);
        $this->assertStringContainsString('предпазителите', $result);
    }

    public function test_negligence_cause_returns_negligence_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'небрежност',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('открит огън', $result);
        $this->assertStringContainsString('отоплителни уреди', $result);
    }

    public function test_vehicle_object_type_adds_vehicle_questions(): void
    {
        $request = new Request([
            'object_type' => 'лек автомобил',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('Допълнителни въпроси за МПС', $result);
        $this->assertStringContainsString('моторен отсек', $result);
    }

    public function test_non_vehicle_object_does_not_show_vehicle_questions(): void
    {
        $request = new Request([
            'object_type' => 'жилищна сграда',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringNotContainsString('Допълнителни въпроси за МПС', $result);
    }

    public function test_ready_to_proceed_when_four_or_more_fields_filled(): void
    {
        $request = new Request([
            'date' => '15.03.2026',
            'location' => 'гр. Плевен',
            'object_type' => 'склад',
            'circumstances' => 'Пожарът е забелязан от съседи.',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('4/6', $result);
        $this->assertStringContainsString('Можеш да преминеш към Фаза 2', $result);
    }

    public function test_not_ready_when_fewer_than_four_fields_filled(): void
    {
        $request = new Request([
            'date' => '15.03.2026',
            'location' => 'гр. Плевен',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('2/6', $result);
        $this->assertStringContainsString('Продължи със събирането', $result);
    }

    public function test_empty_string_values_are_treated_as_missing(): void
    {
        $request = new Request([
            'date' => '',
            'location' => '   ',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('0/6', $result);
        $this->assertStringNotContainsString('✅', $result);
    }

    public function test_unknown_cause_returns_no_cause_specific_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'неизвестна причина',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringNotContainsString('Препоръчителни допълнителни въпроси', $result);
    }

    public function test_lightning_cause_returns_lightning_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'мълния',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('метеоданните', $result);
        $this->assertStringContainsString('мълниезащитна', $result);
    }

    public function test_self_ignition_cause_returns_self_ignition_questions(): void
    {
        $request = new Request([
            'suspected_cause' => 'самозапалване',
        ]);

        $result = (string) $this->tool->handle($request);

        $this->assertStringContainsString('топлинно, химическо, микробиологично', $result);
        $this->assertStringContainsString('масла, мазнини', $result);
    }
}
