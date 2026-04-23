<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $fileName = $this->faker->word() . '.pdf';
        $hash = hash('sha256', $fileName . microtime() . random_bytes(8));

        return [
            'organization_id' => Organization::factory(),
            'owner_type' => null,
            'owner_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'disk' => 'local',
            'file_path' => 'files/' . $fileName,
            'file_name' => $fileName,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size_bytes' => $this->faker->numberBetween(1024, 10_485_760),
            'file_hash_sha256' => $hash,
            'file_hash_md5' => substr(md5($hash), 0, 32),
            'is_encrypted' => false,
            'has_watermark' => false,
            'category' => 'general',
            'confidentiality_level' => ConfidentialityLevel::Internal,
            'version' => 1,
            'is_ocred' => false,
            'virus_scan_status' => 'clean',
            'tags' => [],
            'uploaded_by_user_id' => User::factory(),
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ]);
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
        ]);
    }

    public function confidential(): static
    {
        return $this->state(fn () => [
            'confidentiality_level' => ConfidentialityLevel::Confidential,
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn () => [
            'confidentiality_level' => ConfidentialityLevel::Restricted,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
