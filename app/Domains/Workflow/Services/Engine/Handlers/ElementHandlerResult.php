<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

/**
 * نتیجه اجرای یک Element Handler.
 */
class ElementHandlerResult
{
    public function __construct(
        public readonly string $action,
        public readonly array $nextFlowIds = [],
        public readonly ?array $variables = null,
        public readonly array $newTokens = [],
        public readonly ?string $waitFor = null,
        public readonly ?\DateTimeInterface $waitUntil = null,
    ) {
    }

    /**
     * token به element بعدی برود — از طریق flows تعیین‌شده.
     */
    public static function continueTo(array $flowIds, array $variables = []): self
    {
        return new self('continue', nextFlowIds: $flowIds, variables: $variables);
    }

    /**
     * token در waiting قرار گیرد.
     */
    public static function wait(?string $waitFor = null, ?\DateTimeInterface $until = null): self
    {
        return new self('wait', waitFor: $waitFor, waitUntil: $until);
    }

    /**
     * token مصرف شد (در join، یا cancellation).
     */
    public static function consumed(): self
    {
        return new self('consumed');
    }

    /**
     * این مسیر کامل شد (end event).
     */
    public static function complete(): self
    {
        return new self('complete');
    }

    /**
     * یک token به چند token تقسیم شد (split).
     */
    public static function split(array $flowIds, array $variables = []): self
    {
        return new self('split', nextFlowIds: $flowIds, variables: $variables);
    }
}
