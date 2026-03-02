<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * هر ویرایش یک snapshot جدید — append-only
 */
class MinuteVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'minute_id', 'version_number', 'content_html', 'content_text',
        'change_summary', 'created_by_user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('نسخه‌های صورتجلسه append-only هستند و قابل تغییر نیستند.');
        });
        static::deleting(function () {
            throw new \LogicException('نسخه‌های صورتجلسه append-only هستند و قابل حذف نیستند.');
        });
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
