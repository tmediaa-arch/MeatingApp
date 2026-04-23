<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Organization\Models\Organization;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\ResolutionType;
use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResolutionFactory extends Factory
{
    protected $model = Resolution::class;

    public function definition(): array
    {
        $minute = Minute::factory()->signed()->create();
        $orgCode = Organization::find($minute->organization_id)?->code ?? 'ORG';
        $year = now()->year;
        $serial = str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'minute_id' => $minute->id,
            'meeting_id' => $minute->meeting_id,
            'organization_id' => $minute->organization_id,
            'resolution_number' => "{$orgCode}-RES-{$year}-{$serial}",
            'title' => $this->faker->sentence(6),
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
            'rationale' => $this->faker->sentence(10),
            'type' => ResolutionType::Decision,
            'priority' => 'normal',
            'status' => ResolutionStatus::Draft,
            'requires_voting' => false,
            'majority_threshold_percent' => 50,
            'creator_user_id' => User::factory(),
        ];
    }

    public function withVoting(int $quorum = 3): self
    {
        return $this->state(fn () => [
            'requires_voting' => true,
            'voting_type' => 'open',
            'quorum_required' => $quorum,
            'majority_threshold_percent' => 50,
            'voting_opened_at' => now()->subMinute(),
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => ResolutionStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function inExecution(): self
    {
        return $this->approved()->state(fn () => [
            'status' => ResolutionStatus::InExecution,
        ]);
    }

    public function completed(): self
    {
        return $this->inExecution()->state(fn () => [
            'status' => ResolutionStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
