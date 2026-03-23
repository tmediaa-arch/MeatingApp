<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Workflow\Exceptions\WorkflowException;

/**
 * Registry مرکزی Service Taskها.
 *
 * فقط service taskهای ثبت شده در این registry قابل اجرا هستند —
 * این whitelist مهم‌ترین مکانیزم امنیتی موتور است.
 */
class ServiceTaskRegistry
{
    /**
     * @var array<string, class-string<ServiceTaskInterface>>
     */
    private array $tasks = [];

    public function register(string $taskClass): void
    {
        if (!is_subclass_of($taskClass, ServiceTaskInterface::class)) {
            throw new \InvalidArgumentException(
                "Class {$taskClass} باید ServiceTaskInterface را پیاده‌سازی کند.",
            );
        }

        $key = $taskClass::key();
        if (isset($this->tasks[$key])) {
            throw new \InvalidArgumentException(
                "Service Task با کلید '{$key}' قبلاً ثبت شده است.",
            );
        }

        $this->tasks[$key] = $taskClass;
    }

    public function get(string $key): ServiceTaskInterface
    {
        if (!isset($this->tasks[$key])) {
            throw WorkflowException::serviceTaskNotInWhitelist($key);
        }

        return app($this->tasks[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->tasks[$key]);
    }

    /**
     * @return array<string, class-string<ServiceTaskInterface>>
     */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * فهرست برای نمایش در BPMN designer.
     */
    public function metadata(): array
    {
        $result = [];
        foreach ($this->tasks as $key => $class) {
            $result[$key] = [
                'key' => $key,
                'description' => $class::description(),
                'config_schema' => $class::configSchema(),
            ];
        }
        return $result;
    }
}
