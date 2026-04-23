<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Models\ProcessInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcessIncidentFactory extends Factory
{
    protected $model = ProcessIncident::class;

    public function definition(): array
    {
        return [
            'instance_id' => ProcessInstance::factory()->running(),
            'incident_type' => $this->faker->randomElement([
                'service_task_failed',
                'expression_error',
                'sla_breach',
                'execution_error',
            ]),
            'message' => $this->faker->sentence(),
            'status' => 'open',
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => 'open']);
    }

    public function resolved(): self
    {
        return $this->state([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => 'حل شد',
        ]);
    }
}
