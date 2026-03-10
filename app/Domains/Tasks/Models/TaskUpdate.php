<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Tasks\Enums\TaskUpdateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * به‌روزرسانی‌های وظیفه — append-only.
 * تاریخچه کامل تغییرات و کامنت‌ها.
 */
class TaskUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'updater_user_id', 'update_type',
        'content', 'old_status', 'new_status',
        'old_progress', 'new_progress', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'update_type' => TaskUpdateType::class,
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('به‌روزرسانی‌های وظیفه append-only هستند.');
        });
        static::deleting(function () {
            throw new \LogicException('به‌روزرسانی‌های وظیفه قابل حذف نیستند.');
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updater_user_id');
    }
}
