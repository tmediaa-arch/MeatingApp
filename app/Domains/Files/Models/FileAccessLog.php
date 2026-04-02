<?php

declare(strict_types=1);

namespace App\Domains\Files\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لاگ دسترسی فایل — append-only
 */
class FileAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id', 'user_id', 'action',
        'ip_address', 'user_agent', 'metadata', 'accessed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'accessed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('FileAccessLog append-only است.');
        });
        static::deleting(function () {
            throw new \LogicException('FileAccessLog قابل حذف نیست.');
        });
    }

    public function file(): BelongsTo { return $this->belongsTo(File::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
