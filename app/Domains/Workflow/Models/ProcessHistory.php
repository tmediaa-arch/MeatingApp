<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاریخچه اجرای فرایند — append-only.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $token_id
 * @property string $event_type
 * @property string|null $element_id
 * @property string|null $element_type
 * @property string|null $element_name
 * @property array|null $payload
 * @property \Carbon\Carbon $occurred_at
 */
class ProcessHistory extends Model
{
    use HasFactory;

    protected $table = 'process_history';

    public $timestamps = false; // فقط created_at

    protected $fillable = [
        'instance_id',
        'token_id',
        'event_type',
        'element_id',
        'element_type',
        'element_name',
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
        static::creating(function (self $h) {
            if (empty($h->occurred_at)) {
                $h->occurred_at = now();
            }
        });

        static::updating(function () {
            throw new \LogicException('ProcessHistory append-only است و قابل ویرایش نیست.');
        });

        static::deleting(function () {
            throw new \LogicException('ProcessHistory append-only است و قابل حذف نیست.');
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'instance_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ProcessToken::class, 'token_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Helper برای ثبت یک رویداد.
     */
    public static function log(
        int $instanceId,
        ?int $tokenId,
        string $eventType,
        ?string $elementId = null,
        ?string $elementType = null,
        ?string $elementName = null,
        ?array $payload = null,
        ?int $actorUserId = null,
    ): self {
        return self::create([
            'instance_id' => $instanceId,
            'token_id' => $tokenId,
            'event_type' => $eventType,
            'element_id' => $elementId,
            'element_type' => $elementType,
            'element_name' => $elementName,
            'payload' => $payload,
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'occurred_at' => now(),
        ]);
    }
}
