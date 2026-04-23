<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Models\NotificationOutbox;
use App\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationOutboxFactory extends Factory
{
    protected $model = NotificationOutbox::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $template = NotificationTemplate::factory()->create();

        return [
            'correlation_id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'channel' => NotificationChannel::InApp,
            'status' => NotificationStatus::Pending,
            'recipient_user_id' => $user->id,
            'recipient_employee_id' => null,
            'to_address' => (string) $user->id,
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'body_html' => null,
            'priority' => 'normal',
            'attempts' => 0,
            'max_attempts' => 5,
            'read_in_inbox' => false,
            'archived_in_inbox' => false,
            'metadata' => ['template_key' => $template->key],
        ];
    }

    public function delivered(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Delivered,
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Failed,
            'attempts' => 3,
            'last_error' => 'Connection refused',
            'next_retry_at' => now()->addMinutes(30),
        ]);
    }

    public function retryable(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Failed,
            'attempts' => 1,
            'max_attempts' => 5,
            'last_error' => 'Temporary error',
            'next_retry_at' => now()->subMinute(),
        ]);
    }

    public function unreadInbox(): self
    {
        return $this->state(fn () => [
            'channel' => NotificationChannel::InApp,
            'status' => NotificationStatus::Delivered,
            'read_in_inbox' => false,
            'archived_in_inbox' => false,
        ]);
    }

    public function scheduled(\DateTimeInterface $when): self
    {
        return $this->state(fn () => [
            'scheduled_at' => $when,
            'status' => NotificationStatus::Pending,
        ]);
    }
}
