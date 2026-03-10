<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'requested_by_user_id',
        'original_due_date', 'requested_due_date', 'reason',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'original_due_date' => 'date',
        'requested_due_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
