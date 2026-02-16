<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Identity\Models\User;
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
 * Class Employee
 *
 * موجودیت سازمانی (HR-محور).
 * یک employee یک user دارد برای ورود به سامانه.
 * در ساختار درختی سازمان جای می‌گیرد از طریق primary_position_id.
 *
 * مدیر مستقیم از طریق reports_to_employee_id مشخص می‌شود
 * (که می‌تواند با مدیر واحد فرق داشته باشد).
 *
 * @property int $id
 * @property string $employee_code
 * @property string $first_name
 * @property string $last_name
 * @property int|null $user_id
 * @property int|null $primary_position_id
 * @property int|null $current_org_unit_id
 * @property string $employment_status
 * @property ConfidentialityLevel $clearance_level
 */
class Employee extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;
    use TracksUserChanges;

    protected $fillable = [
        'organization_id', 'user_id',
        'employee_code', 'national_code',
        'first_name', 'last_name', 'father_name',
        'birth_date', 'gender',
        'work_email', 'work_phone', 'extension', 'mobile', 'office_location',
        'primary_position_id', 'current_org_unit_id', 'reports_to_employee_id',
        'employment_status', 'employment_type',
        'hire_date', 'termination_date', 'termination_reason',
        'clearance_level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'clearance_level' => ConfidentialityLevel::class,
            'metadata' => 'array',
        ];
    }

    // ------------------------- Relationships ------------------------- //

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'primary_position_id');
    }

    public function currentOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'current_org_unit_id');
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reports_to_employee_id');
    }

    public function positionHistory(): HasMany
    {
        return $this->hasMany(EmployeePositionHistory::class)
            ->orderByDesc('started_at');
    }

    /**
     * تمام انتسابات فعلی (پست‌های موازی)
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(EmployeePositionHistory::class)
            ->whereNull('ended_at');
    }

    // ------------------------- Accessors ------------------------- //

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getDisplayTitleAttribute(): string
    {
        $name = $this->full_name;
        $title = $this->primaryPosition?->title;

        return $title ? "{$name} ({$title})" : $name;
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }

    public function scopeInUnit(Builder $query, int $unitId): Builder
    {
        return $query->where('current_org_unit_id', $unitId);
    }

    public function scopeInUnitOrDescendants(Builder $query, OrgUnit $unit): Builder
    {
        $unitIds = $unit->descendantsAndSelf()->pluck('id')->toArray();
        return $query->whereIn('current_org_unit_id', $unitIds);
    }

    public function scopeWithMinClearance(Builder $query, ConfidentialityLevel $level): Builder
    {
        $levels = collect(ConfidentialityLevel::cases())
            ->filter(fn ($c) => $c->level() >= $level->level())
            ->map(fn ($c) => $c->value)
            ->toArray();

        return $query->whereIn('clearance_level', $levels);
    }
}
