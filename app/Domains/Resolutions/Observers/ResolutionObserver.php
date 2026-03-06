<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Observers;

use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Exceptions\ResolutionException;
use App\Domains\Resolutions\Models\Resolution;

class ResolutionObserver
{
    public function updating(Resolution $resolution): void
    {
        if ($resolution->isDirty('status')) {
            $original = ResolutionStatus::tryFrom($resolution->getOriginal('status'));
            $new = $resolution->status;
            if ($original && $original !== $new && !$original->canTransitionTo($new)) {
                throw ResolutionException::invalidStateTransition($original, $new);
            }
        }
    }
}
