<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->word() . '.' . $this->faker->word();

        return [
            'organization_id' => null,
            'key' => $key,
            'display_name' => 'قالب آزمایشی ' . $this->faker->word(),
            'description' => $this->faker->sentence(),
            'priority' => 'normal',
            'supported_channels' => [
                NotificationChannel::InApp->value,
                NotificationChannel::Email->value,
            ],
            'available_variables' => ['user_name' => 'نام کاربر'],
            'is_active' => true,
            'is_admin_editable' => true,
            'is_user_disablable' => true,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['priority' => 'critical']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
