<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class MinuteFactory extends Factory
{
    protected $model = Minute::class;

    public function definition(): array
    {
        $meeting = Meeting::factory()->create();
        $orgCode = Organization::find($meeting->organization_id)?->code ?? 'ORG';
        $year = now()->year;
        $serial = str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        $content = '<p>' . $this->faker->paragraphs(3, true) . '</p>';

        return [
            'meeting_id' => $meeting->id,
            'organization_id' => $meeting->organization_id,
            'minute_number' => "{$orgCode}-MIN-{$year}-{$serial}",
            'title' => 'صورتجلسه: ' . $meeting->subject,
            'summary' => $this->faker->sentence(20),
            'content_html' => $content,
            'content_text' => strip_tags($content),
            'key_decisions' => [$this->faker->sentence(), $this->faker->sentence()],
            'status' => MinuteStatus::Draft,
            'confidentiality_level' => ConfidentialityLevel::Internal,
            'secretary_employee_id' => Employee::factory(),
            'chairperson_employee_id' => Employee::factory(),
            'current_version' => 1,
            'creator_user_id' => User::factory(),
        ];
    }

    public function inReview(): self
    {
        return $this->state(fn () => ['status' => MinuteStatus::Review]);
    }

    public function signed(): self
    {
        return $this->state(fn () => [
            'status' => MinuteStatus::Signed,
            'secretary_signed_at' => now()->subHours(2),
            'chairperson_signed_at' => now()->subHour(),
        ]);
    }

    public function published(): self
    {
        return $this->signed()->state(fn () => [
            'status' => MinuteStatus::Published,
            'published_at' => now(),
            'pdf_path' => 'minutes/test.pdf',
            'pdf_hash' => str_repeat('a', 64),
        ]);
    }
}
