<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\Organization;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $org = Organization::factory()->create();
        $serial = str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT);
        $year = now()->year;

        return [
            'organization_id' => $org->id,
            'task_number' => "{$org->code}-TSK-{$year}-{$serial}",
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'type' => TaskType::Action,
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Open,
            'progress_percent' => 0,
            'confidentiality_level' => 'internal',
            'creator_user_id' => User::factory(),
            'due_date' => now()->addDays(7),
        ];
    }

    public function assigned(?Employee $assignee = null): self
    {
        return $this->state(function () use ($assignee) {
            $emp = $assignee ?? Employee::factory()->create();
            return [
                'status' => TaskStatus::Assigned,
                'assignee_employee_id' => $emp->id,
                'assignee_user_id' => $emp->user_id,
                'assigned_at' => now(),
            ];
        });
    }

    public function inProgress(): self
    {
        return $this->assigned()->state(fn () => [
            'status' => TaskStatus::InProgress,
            'progress_percent' => 40,
            'started_at' => now()->subDays(2),
        ]);
    }

    public function submitted(): self
    {
        return $this->inProgress()->state(fn () => [
            'status' => TaskStatus::Submitted,
            'progress_percent' => 100,
            'submitted_at' => now(),
            'result_summary' => 'انجام شد و نتیجه آماده است.',
        ]);
    }

    public function completed(): self
    {
        return $this->submitted()->state(fn () => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'completion_quality' => 'good',
        ]);
    }

    public function overdue(int $daysOverdue = 5): self
    {
        return $this->assigned()->state(fn () => [
            'status' => TaskStatus::InProgress,
            'due_date' => now()->subDays($daysOverdue),
            'is_overdue' => true,
        ]);
    }
}
