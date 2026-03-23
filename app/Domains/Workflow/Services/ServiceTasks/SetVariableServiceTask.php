<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * Service Task: تنظیم یک یا چند متغیر در instance.
 *
 * Config:
 *   variables:  key-value به‌صورت literal یا expression
 */
class SetVariableServiceTask implements ServiceTaskInterface
{
    public static function key(): string
    {
        return 'set_variable';
    }

    public static function description(): string
    {
        return 'تنظیم متغیر(ها) در instance';
    }

    public static function configSchema(): array
    {
        return [
            'variables' => [
                'type' => 'json',
                'required' => true,
                'label' => 'متغیرها به‌صورت key-value',
            ],
        ];
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array {
        $vars = $config['variables'] ?? [];
        if (!is_array($vars)) return [];
        return $vars;
    }
}
