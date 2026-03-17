<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Workflow\Enums\TokenStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Token: نشانگری که محل فعلی اجرا را در BPMN نشان می‌دهد.
 *
 * @property int $id
 * @property string $token_uuid
 * @property int $instance_id
 * @property int|null $parent_token_id
 * @property string $current_element_id
 * @property string $current_element_type
 * @property TokenStatus $status
 * @property \Carbon\Carbon|null $wait_until
 * @property string|null $wait_for_message
 * @property array|null $execution_path
 */
class ProcessToken extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ProcessTokenFactory::new();
    }

    protected $fillable = [
        'token_uuid',
        'instance_id',
        'parent_token_id',
        'current_element_id',
        'current_element_type',
        'status',
        'wait_until',
        'wait_for_message',
        'wait_condition',
        'execution_path',
        'entered_current_element_at',
        'exited_at',
    ];

    protected $casts = [
        'status' => TokenStatus::class,
        'wait_until' => 'datetime',
        'wait_condition' => 'array',
        'execution_path' => 'array',
        'entered_current_element_at' => 'datetime',
        'exited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $token) {
            if (empty($token->token_uuid)) {
                $token->token_uuid = (string) Str::uuid();
            }
            if (empty($token->execution_path)) {
                $token->execution_path = [];
            }
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'instance_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_token_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_token_id');
    }

    public function isAlive(): bool
    {
        return $this->status->isAlive();
    }

    /**
     * اضافه کردن یک element به مسیر طی شده.
     */
    public function pushPath(string $elementId): void
    {
        $path = $this->execution_path ?? [];
        $path[] = [
            'element_id' => $elementId,
            'at' => now()->toIso8601String(),
        ];
        $this->execution_path = $path;
        $this->save();
    }

    /**
     * انتقال به element جدید.
     */
    public function moveTo(string $elementId, string $elementType): void
    {
        $this->pushPath($elementId);
        $this->update([
            'current_element_id' => $elementId,
            'current_element_type' => $elementType,
            'entered_current_element_at' => now(),
            'wait_until' => null,
            'wait_for_message' => null,
            'wait_condition' => null,
        ]);
    }

    public function setWaitingForTimer(\DateTimeInterface $until): void
    {
        $this->update([
            'status' => TokenStatus::Waiting,
            'wait_until' => $until,
        ]);
    }

    public function setWaitingForMessage(string $messageName): void
    {
        $this->update([
            'status' => TokenStatus::Waiting,
            'wait_for_message' => $messageName,
        ]);
    }

    public function setWaitingForUserTask(): void
    {
        $this->update([
            'status' => TokenStatus::Waiting,
        ]);
    }

    public function activate(): void
    {
        $this->update(['status' => TokenStatus::Active]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => TokenStatus::Completed,
            'exited_at' => now(),
        ]);
    }

    public function consume(): void
    {
        $this->update([
            'status' => TokenStatus::Consumed,
            'exited_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => TokenStatus::Cancelled,
            'exited_at' => now(),
        ]);
    }
}
