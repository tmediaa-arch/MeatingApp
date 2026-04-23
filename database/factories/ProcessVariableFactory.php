<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessVariable;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcessVariableFactory extends Factory
{
    protected $model = ProcessVariable::class;

    public function definition(): array
    {
        return [
            'instance_id' => ProcessInstance::factory(),
            'scope_token_id' => null,
            'name' => 'var_' . $this->faker->slug(2, false),
            'type' => 'string',
            'string_value' => $this->faker->sentence(),
        ];
    }

    public function string(string $name, string $value): self
    {
        return $this->state([
            'name' => $name,
            'type' => 'string',
            'string_value' => $value,
            'integer_value' => null,
        ]);
    }

    public function integer(string $name, int $value): self
    {
        return $this->state([
            'name' => $name,
            'type' => 'integer',
            'integer_value' => $value,
            'string_value' => null,
        ]);
    }

    public function boolean(string $name, bool $value): self
    {
        return $this->state([
            'name' => $name,
            'type' => 'boolean',
            'boolean_value' => $value,
            'string_value' => null,
        ]);
    }
}
