<?php

declare(strict_types=1);

namespace App\Domains\Reports\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Reports\Contracts\ReportInterface;
use App\Domains\Reports\Enums\ReportCategory;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Report — تعریف یک گزارش از پیش تعریف‌شده در سامانه.
 *
 * هر Report به یک کلاس PHP (handler_class) اشاره می‌کند که ReportInterface
 * را پیاده‌سازی می‌کند. سرویس ReportRunnerService در runtime آن را resolve می‌کند.
 */
class Report extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'organization_id', 'key', 'display_name', 'description',
        'category', 'handler_class',
        'input_schema', 'supported_formats',
        'confidentiality_level',
        'is_cacheable', 'cache_ttl_minutes',
        'is_active', 'is_system',
        'metadata',
    ];

    protected $casts = [
        'category' => ReportCategory::class,
        'confidentiality_level' => ConfidentialityLevel::class,
        'input_schema' => 'array',
        'supported_formats' => 'array',
        'is_cacheable' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'metadata' => 'array',
    ];

    // ──────── روابط ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    // ──────── Scopes ────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory($query, ReportCategory|string $category)
    {
        $value = $category instanceof ReportCategory ? $category->value : $category;
        return $query->where('category', $value);
    }

    public function scopeForOrganization($query, ?int $orgId)
    {
        return $query->where(function ($q) use ($orgId) {
            $q->whereNull('organization_id')->orWhere('organization_id', $orgId);
        });
    }

    // ──────── Helpers ────────

    /**
     * Handler را instantiate می‌کند.
     *
     * @throws \LogicException اگر کلاس وجود نداشت یا قرارداد را پیاده‌سازی نکرده باشد
     */
    public function makeHandler(): ReportInterface
    {
        if (!class_exists($this->handler_class)) {
            throw new \LogicException("Report handler class '{$this->handler_class}' وجود ندارد.");
        }

        $instance = app($this->handler_class);

        if (!$instance instanceof ReportInterface) {
            throw new \LogicException(
                "Report handler '{$this->handler_class}' باید ReportInterface را پیاده‌سازی کند."
            );
        }

        return $instance;
    }

    public function supportsFormat(string $format): bool
    {
        return in_array($format, $this->supported_formats ?? [], true);
    }
}
