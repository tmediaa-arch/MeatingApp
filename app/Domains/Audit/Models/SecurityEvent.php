<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type', 'severity',
        'user_id', 'ip_address',
        'title', 'description', 'evidence',
        'auto_blocked', 'notified_admins',
        'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'auto_blocked' => 'boolean',
            'notified_admins' => 'boolean',
            'reviewed_at' => 'datetime',
            'performed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', 'under_review');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }
}
