<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine;

use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Models\ProcessVariable;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت متغیرهای یک instance.
 *
 * متغیرها در سطوح زیر قابل تعریف هستند:
 *  1. instance-level: همه tokenها در دسترسی دارند
 *  2. token-level: فقط در scope یک token خاص (مثلاً subProcess)
 *
 * هنگام خواندن، اول scope-level بررسی می‌شود، سپس instance-level.
 */
class VariablesService
{
    /**
     * تنظیم یک یا چند متغیر در سطح instance.
     */
    public function setMany(ProcessInstance $instance, array $variables, ?ProcessToken $scope = null): void
    {
        DB::transaction(function () use ($instance, $variables, $scope) {
            foreach ($variables as $name => $value) {
                $this->set($instance, $name, $value, $scope);
            }
        });
    }

    /**
     * تنظیم یک متغیر.
     */
    public function set(
        ProcessInstance $instance,
        string $name,
        mixed $value,
        ?ProcessToken $scope = null,
    ): ProcessVariable {
        $var = ProcessVariable::firstOrNew([
            'instance_id' => $instance->id,
            'scope_token_id' => $scope?->id,
            'name' => $name,
        ]);

        $var->setValue($value);
        $var->save();

        return $var;
    }

    /**
     * خواندن یک متغیر — اول scope، سپس instance-level.
     */
    public function get(
        ProcessInstance $instance,
        string $name,
        ?ProcessToken $scope = null,
    ): mixed {
        if ($scope) {
            $scoped = ProcessVariable::where('instance_id', $instance->id)
                ->where('scope_token_id', $scope->id)
                ->where('name', $name)
                ->first();
            if ($scoped) return $scoped->getValue();
        }

        $global = ProcessVariable::where('instance_id', $instance->id)
            ->whereNull('scope_token_id')
            ->where('name', $name)
            ->first();

        return $global?->getValue();
    }

    /**
     * تمام متغیرها به‌صورت array (با scope merged).
     */
    public function getAll(ProcessInstance $instance, ?ProcessToken $scope = null): array
    {
        $globals = ProcessVariable::where('instance_id', $instance->id)
            ->whereNull('scope_token_id')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->name => $v->getValue()])
            ->toArray();

        if (!$scope) return $globals;

        $scoped = ProcessVariable::where('instance_id', $instance->id)
            ->where('scope_token_id', $scope->id)
            ->get()
            ->mapWithKeys(fn ($v) => [$v->name => $v->getValue()])
            ->toArray();

        // scope تقدم دارد
        return array_merge($globals, $scoped);
    }

    /**
     * حذف یک متغیر.
     */
    public function remove(
        ProcessInstance $instance,
        string $name,
        ?ProcessToken $scope = null,
    ): void {
        ProcessVariable::where('instance_id', $instance->id)
            ->where('scope_token_id', $scope?->id)
            ->where('name', $name)
            ->delete();
    }
}
