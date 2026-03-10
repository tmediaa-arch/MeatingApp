<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'title', 'file_path', 'file_name',
        'mime_type', 'file_size_bytes', 'file_hash',
        'uploaded_by_user_id', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = (int) $this->file_size_bytes;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1024**2) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024**3) return round($bytes / 1024**2, 1) . ' MB';
        return round($bytes / 1024**3, 1) . ' GB';
    }
}
