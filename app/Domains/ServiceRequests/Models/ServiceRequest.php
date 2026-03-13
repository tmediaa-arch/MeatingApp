<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Models;

use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $request_number
 * @property int $organization_id
 * @property ServiceRequestType $type
 * @property string $title
 * @property string|null $description
 * @property int|null $meeting_id
 * @property array|null $type_specific_data
 * @property string $priority
 * @property ServiceRequestStatus $status
 * @property \Carbon\Carbon $required_at
 */
class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\ServiceRequestFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'request_number',
        'type',
        'title',
        'description',
        'meeting_id',
        'type_specific_data',
        'priority',
        'status',
        'required_at',
        'estimated_duration_minutes',
        'requester_user_id',
        'requester_employee_id',
        'requester_unit_id',
        'provider_unit_id',
        'assigned_to_employee_id',
        'reviewer_user_id',
        'reviewed_at',
        'review_comment',
        'estimated_cost',
        'actual_cost',
        'tags',
        'submitted_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'type' => ServiceRequestType::class,
        'status' => ServiceRequestStatus::class,
        'type_specific_data' => 'array',
        'tags' => 'array',
        'required_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    // ──────── Relations ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function requesterUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'requester_unit_id');
    }

    public function providerUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'provider_unit_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ServiceRequestUpdate::class);
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'service_request_attachments', 'service_request_id', 'file_id')
            ->withPivot(['purpose', 'description', 'uploaded_by_user_id'])
            ->withTimestamps();
    }

    // ──────── Scopes ────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
            ServiceRequestStatus::Completed->value,
            ServiceRequestStatus::Rejected->value,
            ServiceRequestStatus::Cancelled->value,
        ]);
    }

    public function scopeOfType(Builder $q, ServiceRequestType $type): Builder
    {
        return $q->where('type', $type->value);
    }

    public function scopeForUnit(Builder $q, int $unitId): Builder
    {
        return $q->where('provider_unit_id', $unitId);
    }

    public function scopePendingReview(Builder $q): Builder
    {
        return $q->whereIn('status', [
            ServiceRequestStatus::Submitted->value,
            ServiceRequestStatus::UnderReview->value,
        ]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->where('required_at', '<', now());
    }

    // ──────── Helpers ────────

    public function isOverdue(): bool
    {
        return $this->required_at->isPast() && $this->status->isOpen();
    }

    public function getTypeFieldValue(string $key): mixed
    {
        return $this->type_specific_data[$key] ?? null;
    }
}
