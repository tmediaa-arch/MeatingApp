<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Organization\Models\Organization;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class VideoConferenceProviderFactory extends Factory
{
    protected $model = VideoConferenceProvider::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Provider ' . $this->faker->unique()->word(),
            'driver' => VideoConferenceDriver::Null,
            'config_encrypted' => Crypt::encryptString(json_encode([])),
            'max_concurrent_meetings' => null,
            'max_participants_per_meeting' => null,
            'supports_recording' => false,
            'supports_streaming' => false,
            'supports_breakout_rooms' => false,
            'is_active' => true,
            'is_default' => false,
            'health_status' => HealthStatus::Unknown,
        ];
    }

    public function nullDriver(): self
    {
        return $this->state(['driver' => VideoConferenceDriver::Null]);
    }

    public function jitsi(array $extraConfig = []): self
    {
        return $this->state([
            'driver' => VideoConferenceDriver::Jitsi,
            'config_encrypted' => Crypt::encryptString(json_encode(array_merge([
                'base_url' => 'https://meet.example.com',
                'jwt_secret' => 'test-secret-for-jitsi-jwt-signing',
                'jwt_app_id' => 'mms-test',
            ], $extraConfig))),
            'supports_recording' => true,
            'supports_breakout_rooms' => true,
        ]);
    }

    public function bigbluebutton(array $extraConfig = []): self
    {
        return $this->state([
            'driver' => VideoConferenceDriver::BigBlueButton,
            'config_encrypted' => Crypt::encryptString(json_encode(array_merge([
                'base_url' => 'https://bbb.example.com/bigbluebutton/',
                'shared_secret' => 'test-shared-secret',
            ], $extraConfig))),
            'supports_recording' => true,
            'supports_breakout_rooms' => true,
        ]);
    }

    public function alocom(array $extraConfig = []): self
    {
        return $this->state([
            'driver' => VideoConferenceDriver::Alocom,
            'config_encrypted' => Crypt::encryptString(json_encode(array_merge([
                'api_base_url' => 'https://alocom.example.com/api/v1',
                'api_token' => 'test-token',
                'tenant_id' => 'tenant-1',
            ], $extraConfig))),
            'supports_recording' => true,
        ]);
    }

    public function active(): self
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }

    public function healthy(): self
    {
        return $this->state([
            'health_status' => HealthStatus::Healthy,
            'last_health_check_at' => now(),
        ]);
    }

    public function unhealthy(?string $message = null): self
    {
        return $this->state([
            'health_status' => HealthStatus::Unhealthy,
            'health_message' => $message ?? 'Connection failed',
            'last_health_check_at' => now(),
        ]);
    }
}
