<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Domains\Workflow\Models\ProcessInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProcessInstanceFactory extends Factory
{
    protected $model = ProcessInstance::class;

    public function definition(): array
    {
        $definition = ProcessDefinition::factory()->published()->create();

        return [
            'instance_uuid' => (string) Str::uuid(),
            'process_definition_id' => $definition->id,
            'process_key' => $definition->process_key,
            'process_version' => $definition->version,
            'organization_id' => $definition->organization_id,
            'business_key' => 'BK-' . $this->faker->numerify('####'),
            'status' => ProcessInstanceStatus::Pending,
            'priority' => 'normal',
            'starter_user_id' => User::factory(),
            'start_variables' => [],
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => ProcessInstanceStatus::Pending]);
    }

    public function running(): self
    {
        return $this->state([
            'status' => ProcessInstanceStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => ProcessInstanceStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state([
            'status' => ProcessInstanceStatus::Failed,
            'started_at' => now()->subHour(),
            'failure_reason' => 'تست خطا',
        ]);
    }

    public function suspended(): self
    {
        return $this->state([
            'status' => ProcessInstanceStatus::Suspended,
            'started_at' => now()->subHour(),
            'suspended_at' => now(),
        ]);
    }

    public function slaBreached(): self
    {
        return $this->state([
            'status' => ProcessInstanceStatus::Running,
            'started_at' => now()->subDays(2),
            'sla_due_at' => now()->subHours(2),
        ]);
    }
}
