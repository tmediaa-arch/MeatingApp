<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Organization\Models\Employee;
use App\Domains\Resolutions\Enums\VoteValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رأی به مصوبه — append-only.
 * هر کارمند فقط یک رأی در یک مصوبه دارد.
 */
class ResolutionVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'resolution_id', 'voter_employee_id', 'voter_user_id',
        'vote', 'weight', 'delegated_from_employee_id', 'delegation_id',
        'rationale', 'voter_ip', 'voted_at',
    ];

    protected $casts = [
        'vote' => VoteValue::class,
        'weight' => 'decimal:3',
        'voted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('رأی append-only است؛ برای تغییر آن، رأی فعلی را invalidate کرده و رأی جدید ثبت کنید.');
        });
        static::deleting(function () {
            throw new \LogicException('رأی append-only است و قابل حذف نیست.');
        });
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'voter_employee_id');
    }

    public function voterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_user_id');
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegated_from_employee_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(UserDelegation::class, 'delegation_id');
    }

    public function isProxyVote(): bool
    {
        return $this->delegated_from_employee_id !== null;
    }
}
