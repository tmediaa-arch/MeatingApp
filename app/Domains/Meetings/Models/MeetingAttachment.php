<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MeetingAttachment — پیوست‌های جلسه و دستور جلسه.
 *
 * با جدول meeting_attachments هماهنگ است (migration فاز ۲).
 */
class MeetingAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasAuditLog;

    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'attachment_type',
        'visibility',
        'visible_to_roles',
        'confidentiality_level',
        'uploaded_by_user_id',
        'uploaded_at',
        'is_circulated_before_meeting',
        'circulated_at',
        'metadata',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'visible_to_roles' => 'array',
        'confidentiality_level' => ConfidentialityLevel::class,
        'uploaded_at' => 'datetime',
        'is_circulated_before_meeting' => 'boolean',
        'circulated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * حجم فایل به صورت خوانا (برای نمایش در Filament).
     */
    public function getFileSizeHumanAttribute(): ?string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf('%s %s', round($value, $unit === 0 ? 0 : 1), $units[$unit]);
    }
}
