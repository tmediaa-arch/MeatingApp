<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine;

use App\Domains\Workflow\Models\ProcessDefinition;
use Illuminate\Support\Facades\Cache;

/**
 * یافتن sequence flows از parsed_metadata تعریف فرایند.
 *
 * چون flows در XML پارس شده ذخیره می‌شوند، این service آنها را
 * برای استفاده مکرر کش می‌کند.
 */
class FlowResolver
{
    /**
     * یافتن flow ها از process_definition.parsed_metadata.flows
     *
     * @return array<int, array{id: string, source: string, target: string, condition: ?string, default: bool}>
     */
    public function getFlows(int $definitionId, ?array $filterIds = null): array
    {
        $allFlows = Cache::remember(
            "workflow.flows.{$definitionId}",
            now()->addHours(1),
            function () use ($definitionId) {
                $definition = ProcessDefinition::find($definitionId);
                return $definition?->parsed_metadata['flows'] ?? [];
            },
        );

        if ($filterIds === null) return $allFlows;

        return array_values(array_filter(
            $allFlows,
            fn ($f) => in_array($f['id'], $filterIds, true),
        ));
    }

    /**
     * یافتن یک flow خاص.
     */
    public function findFlow(int $definitionId, string $flowId): ?array
    {
        $flows = $this->getFlows($definitionId, [$flowId]);
        return $flows[0] ?? null;
    }

    /**
     * یافتن element مقصد یک flow.
     */
    public function getTargetElement(int $definitionId, string $flowId): ?string
    {
        return $this->findFlow($definitionId, $flowId)['target'] ?? null;
    }

    public function clearCache(int $definitionId): void
    {
        Cache::forget("workflow.flows.{$definitionId}");
    }
}
