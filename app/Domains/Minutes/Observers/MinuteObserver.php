<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Observers;

use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;

/**
 * Defence in depth — اگر کسی از مسیر Action عبور کند و
 * مستقیماً model را update کند، اینجا state machine گارد می‌شود.
 */
class MinuteObserver
{
    public function updating(Minute $minute): void
    {
        if ($minute->isDirty('status')) {
            $original = MinuteStatus::tryFrom($minute->getOriginal('status'));
            $new = $minute->status;
            if ($original && $original !== $new && !$original->canTransitionTo($new)) {
                throw MinuteException::invalidStateTransition($original, $new);
            }
        }
    }
}
