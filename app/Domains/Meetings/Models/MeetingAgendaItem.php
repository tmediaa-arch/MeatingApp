<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Organization\Models\Employee;
use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAgendaItem extends Model
{
    use HasFactory;
    use HasAuditLog;

    protected $fillable = [
        'meeting_id',
        'order_index',
        'title',
        'description',
        'presenter_employee_id',
        'presenter_external',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'actual_started_at',
        'actual_ended_at',
        'item_type',
        'status',
        'attachment_refs',
        'summary_notes',
        'initial_decisions',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'actual_started_at' => 'datetime',
        'actual_ended_at' => 'datetime',
        'attachment_refs' => 'array',
        'initial_decisions' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'presenter_employee_id');
    }
}
