<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CaseChecklist implements Tool
{
    /**
     * The checklist fields and their labels.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'date' => 'Дата и час на пожара',
        'location' => 'Местоположение (адрес, населено място)',
        'object_type' => 'Обект на пожара (жилищна сграда, склад, автомобил и т.н.)',
        'circumstances' => 'Обстоятелства по делото',
        'suspected_cause' => 'Предполагаема причина за пожара',
        'expert_questions' => 'Поставени въпроси пред експерта',
    ];

    /**
     * Cause-specific follow-up questions.
     *
     * @var array<string, list<string>>
     */
    private const array CAUSE_QUESTIONS = [
        'палеж' => [
            'Има ли следи от ускорители (бензин, нафта, разтворители)?',
            'Установени ли са множество изолирани огнища?',
            'Има ли запалителни устройства или петна тип „мастилено петно"?',
            'Взети ли са проби за химическа експертиза?',
            'Има ли косвени признаци: блокирани врати/прозорци, следи от взлом, липсващи вещи?',
        ],
        'късо съединение' => [
            'Какво е състоянието на електрическата инсталация (алуминиева/медна, възраст)?',
            'Извършвани ли са скорошни ремонти на ел. инсталацията?',
            'Има ли данни за претоварване — брой консуматори, разклонители?',
            'Какъв е типът на проводниците и предпазителите?',
            'Установени ли са разтопявания по проводници — локални (първично к.с.) или разпределени (вторично)?',
            'Какво е положението на защитните устройства (вкл/изкл)?',
        ],
        'мълния' => [
            'Какви са метеоданните за деня — има ли регистрирани мълнии в района?',
            'Има ли мълниезащитна инсталация на обекта?',
            'Какви са следите от мълнията по сградата/обекта?',
        ],
        'самозапалване' => [
            'Какъв вид самозапалване се предполага (топлинно, химическо, микробиологично)?',
            'Има ли близки топлинни източници до горимите материали?',
            'Съхранявани ли са масла, мазнини или органични материали?',
            'Какви са условията на съхранение — влажност, размер на купите, време?',
        ],
        'небрежност' => [
            'Какви дейности с открит огън са извършвани?',
            'Има ли отоплителни уреди — какъв тип и в какво състояние?',
            'Какъв е конкретният източник (свещ, цигара, огневи работи, нагрято олио)?',
        ],
        'техническа неизправност' => [
            'Какъв е видът на уреда и режимът на работа?',
            'Кога е извършвана последна поддръжка?',
            'Какви са условията на експлоатация?',
        ],
        'детска игра' => [
            'Каква е възрастта на детето/децата?',
            'Къде е било местонахождението им?',
            'Имали ли са достъп до кибрит или запалки?',
        ],
        'строителна неизправност' => [
            'Какъв е видът на конструкцията и използваните материали?',
            'Извършвани ли са скорошни ремонти или преустройства?',
        ],
        'мпс' => [
            'Каква е зоната на възникване (моторен отсек, купе, багажник, външна)?',
            'Какво е състоянието на двигателя — работещ, изключен, паркиран?',
            'Какво е състоянието на ел. инсталацията — акумулатор, ключ, предпазители?',
            'Какво е състоянието на горивната система — горивопровод, помпа, резервоар?',
            'Има ли термични поражения — асиметрия по гуми, лак, антикорозия?',
            'Какви са марката, моделът и годината на МПС-то?',
        ],
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Валидира събраната информация по случая и показва какво липсва. Използвай този инструмент по време на Фаза 1, за да провериш дали имаш достатъчно данни преди да преминеш към анализ.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $filled = [];
        $missing = [];

        foreach (self::FIELDS as $key => $label) {
            $value = $request[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $filled[$key] = $label;
            } else {
                $missing[$key] = $label;
            }
        }

        $output = "## Статус на събраната информация\n\n";

        if ($filled !== []) {
            $output .= "### Попълнени полета\n";
            foreach ($filled as $label) {
                $output .= "- ✅ {$label}\n";
            }
            $output .= "\n";
        }

        if ($missing !== []) {
            $output .= "### Липсващи полета\n";
            foreach ($missing as $label) {
                $output .= "- ❌ {$label}\n";
            }
            $output .= "\n";
        }

        $suspectedCause = $request['suspected_cause'] ?? null;
        $causeQuestions = $this->getCauseSpecificQuestions(
            is_string($suspectedCause) ? $suspectedCause : ''
        );

        if ($causeQuestions !== []) {
            $output .= "### Препоръчителни допълнителни въпроси (за причина: {$suspectedCause})\n";
            foreach ($causeQuestions as $question) {
                $output .= "- {$question}\n";
            }
            $output .= "\n";
        }

        $objectType = $request['object_type'] ?? null;
        if (is_string($objectType) && $this->isVehicle($objectType)) {
            $vehicleQuestions = self::CAUSE_QUESTIONS['мпс'] ?? [];
            $output .= "### Допълнителни въпроси за МПС\n";
            foreach ($vehicleQuestions as $question) {
                $output .= "- {$question}\n";
            }
            $output .= "\n";
        }

        $filledCount = count($filled);
        $totalCount = count(self::FIELDS);
        $readyThreshold = 4;

        if ($filledCount >= $readyThreshold) {
            $output .= "### Готовност\n";
            $output .= "Събрани са {$filledCount}/{$totalCount} основни полета. ";
            $output .= "Можеш да преминеш към Фаза 2 (анализ), но първо обмисли дали допълнителните въпроси по-горе биха подобрили качеството на експертизата.\n";
        } else {
            $output .= "### Готовност\n";
            $output .= "Събрани са {$filledCount}/{$totalCount} основни полета. ";
            $output .= "Продължи със събирането на информация преди да преминеш към анализ.\n";
        }

        return $output;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Дата и час на пожара, ако са известни.'),
            'location' => $schema->string()->description('Местоположение — адрес, населено място.'),
            'object_type' => $schema->string()->description('Обект на пожара — жилищна сграда, склад, автомобил и т.н.'),
            'circumstances' => $schema->string()->description('Обстоятелства по делото — как е открит пожарът, първоначални действия.'),
            'suspected_cause' => $schema->string()->description('Предполагаема причина — палеж, късо съединение, мълния, самозапалване, небрежност, техническа неизправност, детска игра, строителна неизправност.'),
            'expert_questions' => $schema->string()->description('Поставени въпроси пред експерта.'),
        ];
    }

    /**
     * Get cause-specific follow-up questions based on the suspected cause.
     *
     * @return list<string>
     */
    private function getCauseSpecificQuestions(string $suspectedCause): array
    {
        if ($suspectedCause === '') {
            return [];
        }

        $normalizedCause = mb_strtolower(trim($suspectedCause));

        foreach (self::CAUSE_QUESTIONS as $causeKey => $questions) {
            if ($causeKey === 'мпс') {
                continue;
            }

            if (str_contains($normalizedCause, $causeKey)) {
                return $questions;
            }
        }

        return [];
    }

    /**
     * Determine if the object type refers to a vehicle.
     */
    private function isVehicle(string $objectType): bool
    {
        $normalized = mb_strtolower(trim($objectType));
        $vehicleKeywords = ['автомобил', 'мпс', 'кола', 'камион', 'бус', 'микробус', 'ван', 'мотоциклет'];

        foreach ($vehicleKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
