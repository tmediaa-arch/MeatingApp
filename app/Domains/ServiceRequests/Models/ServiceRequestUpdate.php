<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاریخچه به‌روزرسانی‌ها — append-only.
 *
 * @property int $id
 * @property int $service_request_id
 * @property string $update_type
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $comment
 */
class ServiceRequestUpdate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'service_request_id',
        'update_type',
        'from_value',
        'to_value',
        'comment',
        'payload',
        'actor_user_id',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $u) {
            if (empty($u->occurred_at)) {
                $u->occurred_at = now();
            }
        });

        static::updating(function () {
            throw new \LogicException('ServiceRequestUpdate append-only است.');
        });

        static::deleting(function () {
            throw new \LogicException('ServiceRequestUpdate append-only است.');
        });
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
