<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Jobs;

use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job دوره‌ای: tokenهایی که wait_until آنها گذشته را activate می‌کند.
 *
 * توسط `workflow:timer-tick` فراخوانی می‌شود (هر دقیقه).
 */
class WorkflowTimerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WorkflowEngine $engine): void
    {
        $now = now();

        $tokens = ProcessToken::where('status', TokenStatus::Waiting->value)
            ->whereNotNull('wait_until')
            ->where('wait_until', '<=', $now)
            ->with('instance')
            ->limit(200)
            ->get();

        Log::info("WorkflowTimerJob: processing {$tokens->count()} expired timers");

        foreach ($tokens as $token) {
            try {
                if (!$token->instance->isActive()) {
                    continue;
                }
                $engine->wakeUpToken($token);
            } catch (\Throwable $e) {
                Log::error("WorkflowTimerJob failed for token {$token->token_uuid}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
