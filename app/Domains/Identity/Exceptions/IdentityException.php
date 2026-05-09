<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use Exception;

class IdentityException extends Exception
{
    public static function userLocked(string $username): self
    {
        return new self("User '{$username}' is locked and cannot perform this action.");
    }

    public static function invalidStateTransition(string $from, string $to): self
    {
        return new self("Cannot transition from '{$from}' to '{$to}'.");
    }

    public static function delegationOverlap(): self
    {
        return new self('Active delegation already exists for this delegator with overlapping scope and timeframe.');
    }

    public static function cannotDeleteSystemUser(): self
    {
        return new self('System users cannot be deleted.');
    }

    public static function cannotDelegateToSelf(): self
    {
        return new self('A user cannot delegate authority to themselves.');
    }
}
