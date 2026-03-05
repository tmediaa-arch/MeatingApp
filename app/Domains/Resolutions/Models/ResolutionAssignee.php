<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Models;

use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Resolutions\Enums\AssigneeRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResolutionAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'resolution_id', 'employee_id', 'org_unit_id',
        'role', 'is_primary',
    ];

    protected $casts = [
        'role' => AssigneeRole::class,
        'is_primary' => 'boolean',
    ];

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->employee) return $this->employee->full_name;
        if ($this->orgUnit) return $this->orgUnit->name;
        return '—';
    }
}
