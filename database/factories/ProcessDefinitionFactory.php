<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Models\ProcessDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcessDefinitionFactory extends Factory
{
    protected $model = ProcessDefinition::class;

    public function definition(): array
    {
        $xml = $this->minimalBpmnXml();
        $key = 'proc_' . $this->faker->unique()->slug(2, false);

        return [
            'organization_id' => Organization::factory(),
            'process_key' => $key,
            'version' => 1,
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['meeting', 'task', 'approval', 'system']),
            'bpmn_xml' => $xml,
            'bpmn_xml_hash' => hash('sha256', $xml),
            'parsed_metadata' => [
                'process_id' => 'Process_1',
                'elements' => [],
                'flows' => [],
            ],
            'status' => ProcessDefinitionStatus::Draft,
            'is_latest' => false,
            'creator_user_id' => User::factory(),
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => ProcessDefinitionStatus::Draft, 'is_latest' => false]);
    }

    public function published(): self
    {
        return $this->state([
            'status' => ProcessDefinitionStatus::Published,
            'is_latest' => true,
            'published_at' => now(),
            'published_by_user_id' => User::factory(),
        ]);
    }

    public function deprecated(): self
    {
        return $this->state(['status' => ProcessDefinitionStatus::Deprecated, 'is_latest' => false]);
    }

    /**
     * یک BPMN ساده: start → end
     */
    private function minimalBpmnXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:mms="http://mms.local/bpmn"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="true">
    <bpmn:startEvent id="StartEvent_1" name="شروع">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:endEvent id="EndEvent_1" name="پایان">
      <bpmn:incoming>Flow_1</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="EndEvent_1"/>
  </bpmn:process>
</bpmn:definitions>
XML;
    }
}
