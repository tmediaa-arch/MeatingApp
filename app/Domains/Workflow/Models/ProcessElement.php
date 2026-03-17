<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Workflow\Enums\ElementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک عنصر BPMN استخراج شده از تعریف فرایند.
 *
 * @property int $id
 * @property int $process_definition_id
 * @property string $element_id
 * @property string $element_type
 * @property string|null $name
 * @property array|null $properties
 * @property array|null $form_schema
 * @property string|null $service_task_class
 * @property array|null $service_task_config
 */
class ProcessElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_definition_id',
        'element_id',
        'element_type',
        'name',
        'properties',
        'form_schema',
        'service_task_class',
        'service_task_config',
        'sort_order',
    ];

    protected $casts = [
        'properties' => 'array',
        'form_schema' => 'array',
        'service_task_config' => 'array',
        'sort_order' => 'integer',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    public function getElementTypeEnum(): ElementType
    {
        return ElementType::from($this->element_type);
    }

    /**
     * فهرست id عناصر مقصد از این عنصر (از سکانس فلوها).
     */
    public function getOutgoingFlows(): array
    {
        return $this->properties['outgoing'] ?? [];
    }

    public function getIncomingFlows(): array
    {
        return $this->properties['incoming'] ?? [];
    }

    public function getAssigneeExpression(): ?string
    {
        return $this->properties['assignee'] ?? null;
    }

    public function getCandidateUsersExpression(): ?string
    {
        return $this->properties['candidate_users'] ?? null;
    }

    public function getCandidateRolesExpression(): ?string
    {
        return $this->properties['candidate_groups'] ?? null;
    }

    public function getDueDateExpression(): ?string
    {
        return $this->properties['due_date'] ?? null;
    }

    public function getConditionExpression(): ?string
    {
        return $this->properties['condition'] ?? null;
    }

    public function getTimerDuration(): ?string
    {
        return $this->properties['timer_duration'] ?? null;
    }

    public function getMessageReference(): ?string
    {
        return $this->properties['message_ref'] ?? null;
    }
}
