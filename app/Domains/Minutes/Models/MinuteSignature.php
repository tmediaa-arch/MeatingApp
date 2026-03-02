<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * امضای دیجیتال صورتجلسه — append-only.
 *
 * هر امضا با hash محتوای زمان امضا ذخیره می‌شود تا
 * هرگونه تغییر بعدی قابل تشخیص باشد.
 */
class MinuteSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'minute_id', 'signer_user_id', 'signer_employee_id', 'signer_role',
        'content_hash', 'signature_method', 'signature_data',
        'signer_ip', 'signer_user_agent', 'metadata', 'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('امضای صورتجلسه append-only است و قابل تغییر نیست.');
        });
        static::deleting(function () {
            throw new \LogicException('امضای صورتجلسه append-only است و قابل حذف نیست.');
        });
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function signerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'signer_employee_id');
    }

    /**
     * اعتبارسنجی: آیا محتوای فعلی صورتجلسه با hash زمان امضا یکسان است؟
     */
    public function isValidForCurrentContent(): bool
    {
        return hash('sha256', (string) $this->minute->content_html) === $this->content_hash;
    }
}
