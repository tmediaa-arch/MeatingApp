<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'password' => 'password',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
            'is_external' => false,
            'is_system' => false,
            'mfa_enabled' => false,
            'preferred_locale' => 'fa',
            'preferred_calendar' => 'jalali',
            'timezone' => 'Asia/Tehran',
            'remember_token' => Str::random(10),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Locked,
            'locked_until' => now()->addHour(),
            'failed_login_attempts' => 5,
        ]);
    }

    public function external(): static
    {
        return $this->state(fn () => ['is_external' => true]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function withMfa(): static
    {
        return $this->state(fn () => [
            'mfa_enabled' => true,
            'mfa_secret' => 'ABCDEFGHIJKLMNOP',
        ]);
    }
}
