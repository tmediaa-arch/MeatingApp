<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Concerns\TracksUserChanges;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Position
 *
 * پست سازمانی واقعی. متفاوت از JobTitle:
 * - JobTitle: عنوان شغلی استاندارد (dictionary)
 * - Position: پست واقعی در یک واحد مشخص با یک عنوان مشخص
 *
 * یک Position می‌تواند بدون متصدی باشد (status = vacant).
 * تاریخچه متصدیان در employee_position_histories ذخیره می‌شود.
 */
class Position extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;
    use TracksUserChanges;

    protected $fillable = [
        'organization_id', 'org_unit_id', 'job_title_id',
        'code', 'title', 'description', 'responsibilities',
        'status',
        'is_managerial', 'can_chair_meetings',
        'requires_security_clearance', 'max_clearance_level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_managerial' => 'boolean',
            'can_chair_meetings' => 'boolean',
            'requires_security_clearance' => 'boolean',
            'max_clearance_level' => ConfidentialityLevel::class,
            'metadata' => 'array',
        ];
    }

    // ------------------------- Relationships ------------------------- //

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function currentOccupants(): HasMany
    {
        return $this->hasMany(Employee::class, 'primary_position_id');
    }

    public function positionHistory(): HasMany
    {
        return $this->hasMany(EmployeePositionHistory::class);
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeVacant(Builder $query): Builder
    {
        return $query->where('status', 'vacant');
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', 'occupied');
    }

    public function scopeManagerial(Builder $query): Builder
    {
        return $query->where('is_managerial', true);
    }

    public function scopeCanChair(Builder $query): Builder
    {
        return $query->where('can_chair_meetings', true);
    }
}
