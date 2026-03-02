<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Audit\Concerns\TracksUserChanges;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\Organization;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * صورتجلسه (Minutes) — مستند رسمی جلسه.
 *
 * هر جلسه دقیقاً یک صورتجلسه دارد (one-to-one با meeting).
 * صورتجلسه از منوی Meeting (پس از Completed) ایجاد می‌شود.
 *
 * چرخه عمر: draft → review → signed → published
 * نسخه‌بندی در minute_versions
 * امضاها در minute_signatures (append-only)
 */
class Minute extends Model
{
    use HasFactory, HasAuditLog, TracksUserChanges, SoftDeletes;

    protected $fillable = [
        'meeting_id', 'organization_id', 'minute_number', 'title',
        'content_html', 'content_text', 'summary', 'key_decisions',
        'status', 'secretary_employee_id', 'secretary_signed_at',
        'chairperson_employee_id', 'chairperson_signed_at',
        'published_at', 'published_by_user_id',
        'pdf_path', 'pdf_hash', 'pdf_generated_at',
        'current_version', 'confidentiality_level', 'metadata',
        'creator_user_id', 'updater_user_id',
    ];

    protected $casts = [
        'status' => MinuteStatus::class,
        'confidentiality_level' => ConfidentialityLevel::class,
        'key_decisions' => 'array',
        'metadata' => 'array',
        'secretary_signed_at' => 'datetime',
        'chairperson_signed_at' => 'datetime',
        'published_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    // ──────── روابط ────────

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'secretary_employee_id');
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'chairperson_employee_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MinuteVersion::class)->orderBy('version_number');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(MinuteSignature::class)->orderBy('signed_at');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }

    // ──────── Scopes ────────

    public function scopePublished($query)
    {
        return $query->where('status', MinuteStatus::Published);
    }

    public function scopeForUser($query, User $user)
    {
        // اگر admin بود همه را می‌بیند، در غیر این صورت فقط جلساتی که در آن‌ها بوده
        if ($user->hasRole('super-admin') || $user->hasPermissionTo('minute.view_all')) {
            return $query;
        }
        return $query->whereHas('meeting', function ($q) use ($user) {
            $q->forUser($user);
        });
    }

    // ──────── Helpers ────────

    public function canBeViewedBy(User $user): bool
    {
        // محرمانگی
        if (!$user->clearanceLevel()->canAccess($this->confidentiality_level)) {
            return false;
        }

        // اگر admin
        if ($user->hasPermissionTo('minute.view_all')) {
            return true;
        }

        // اگر در جلسه بوده
        return $this->meeting->canBeViewedBy($user);
    }

    public function canBeEditedBy(User $user): bool
    {
        if (!$this->status->isEditable()) {
            return false;
        }

        // فقط دبیر (یا admin) می‌تواند ویرایش کند
        return $user->hasRole('super-admin')
            || ($user->employee_id === $this->secretary_employee_id)
            || $user->hasPermissionTo('minute.update');
    }

    public function isFullySigned(): bool
    {
        return $this->secretary_signed_at !== null
            && $this->chairperson_signed_at !== null;
    }

    /**
     * hash محتوای فعلی برای اعتبارسنجی امضا
     */
    public function getContentHash(): string
    {
        return hash('sha256', (string) $this->content_html);
    }
}
