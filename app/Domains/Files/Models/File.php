<?php

declare(strict_types=1);

namespace App\Domains\Files\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * File — مرکز مدیریت یکپارچه فایل سامانه.
 * هر فایلی که در سامانه آپلود می‌شود از این مدل عبور می‌کند.
 */
class File extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'organization_id', 'owner_type', 'owner_id',
        'title', 'description',
        'disk', 'file_path', 'file_name', 'original_name',
        'mime_type', 'extension', 'file_size_bytes',
        'file_hash_sha256', 'file_hash_md5',
        'is_encrypted', 'encryption_method', 'has_watermark',
        'category', 'confidentiality_level',
        'version', 'previous_version_file_id',
        'is_ocred', 'ocr_text', 'ocred_at',
        'virus_scan_status', 'virus_scanned_at',
        'extracted_metadata', 'expires_at', 'tags',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'confidentiality_level' => ConfidentialityLevel::class,
        'is_encrypted' => 'boolean',
        'has_watermark' => 'boolean',
        'is_ocred' => 'boolean',
        'extracted_metadata' => 'array',
        'tags' => 'array',
        'ocred_at' => 'datetime',
        'virus_scanned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ──────── روابط ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_file_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(FilePermission::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(FileAccessLog::class);
    }

    // ──────── Scopes ────────

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeClean($query)
    {
        return $query->where('virus_scan_status', 'clean');
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    // ──────── Helpers ────────

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = (int) $this->file_size_bytes;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1024 ** 2) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 ** 3) return round($bytes / 1024 ** 2, 1) . ' MB';
        return round($bytes / 1024 ** 3, 1) . ' GB';
    }

    public function getStoragePath(): string
    {
        return $this->file_path;
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }

    public function canBeAccessedBy(User $user): bool
    {
        // محرمانگی
        if (!$user->clearanceLevel()->canAccess($this->confidentiality_level)) {
            return false;
        }

        // ادمین
        if ($user->hasPermissionTo('file.view_all')) return true;

        // آپلود کننده
        if ($this->uploaded_by_user_id === $user->id) return true;

        // مجوز صریح
        $hasPermission = $this->permissions()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->employee_id && $user->employee?->org_unit_id) {
                    $q->orWhere('org_unit_id', $user->employee->org_unit_id);
                }
            })
            ->where('can_view', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasPermission) return true;

        // اگر owner دار است، از طریق آن
        if ($this->owner && method_exists($this->owner, 'canBeViewedBy')) {
            return $this->owner->canBeViewedBy($user);
        }

        return false;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
