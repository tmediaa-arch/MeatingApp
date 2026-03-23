<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Jobs;

use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Models\ProcessInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * بررسی SLA: instanceهایی که از sla_due_at گذشتند و هنوز active هستند
 * را با incident علامت‌گذاری می‌کند.
 */
class WorkflowSlaCheckerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $breached = ProcessInstance::slaBreached()
            ->whereDoesntHave('openIncidents', function ($q) {
                $q->where('incident_type', 'sla_breach');
            })
            ->limit(200)
            ->get();

        Log::info("WorkflowSlaCheckerJob: {$breached->count()} SLA-breached instances");

        foreach ($breached as $instance) {
            try {
                ProcessIncident::create([
                    'instance_id' => $instance->id,
                    'token_id' => null,
                    'incident_type' => 'sla_breach',
                    'message' => sprintf(
                        'مهلت SLA (%s) برای instance منقضی شد.',
                        $instance->sla_due_at->format('Y/m/d H:i'),
                    ),
                    'context' => [
                        'sla_due_at' => $instance->sla_due_at->toIso8601String(),
                        'overdue_minutes' => $instance->sla_due_at->diffInMinutes(now()),
                    ],
                    'status' => 'open',
                ]);
            } catch (\Throwable $e) {
                Log::error("WorkflowSlaCheckerJob failed for instance {$instance->instance_uuid}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
