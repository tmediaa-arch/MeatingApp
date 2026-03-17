<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $process_key
 * @property int $version
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string $bpmn_xml
 * @property string $bpmn_xml_hash
 * @property array|null $parsed_metadata
 * @property array|null $start_form_schema
 * @property array|null $variables_schema
 * @property ProcessDefinitionStatus $status
 * @property bool $is_latest
 */
class ProcessDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\ProcessDefinitionFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'process_key',
        'version',
        'name',
        'description',
        'category',
        'bpmn_xml',
        'bpmn_xml_hash',
        'parsed_metadata',
        'start_form_schema',
        'variables_schema',
        'status',
        'is_latest',
        'published_by_user_id',
        'published_at',
        'creator_user_id',
    ];

    protected $casts = [
        'parsed_metadata' => 'array',
        'start_form_schema' => 'array',
        'variables_schema' => 'array',
        'status' => ProcessDefinitionStatus::class,
        'is_latest' => 'boolean',
        'published_at' => 'datetime',
    ];

    // ──────── Relations ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(ProcessElement::class, 'process_definition_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class, 'process_definition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    // ──────── Scopes ────────

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', ProcessDefinitionStatus::Published);
    }

    public function scopeLatest(Builder $q): Builder
    {
        return $q->where('is_latest', true);
    }

    public function scopeForKey(Builder $q, string $key): Builder
    {
        return $q->where('process_key', $key);
    }

    // ──────── Helpers ────────

    public function getStartElement(): ?ProcessElement
    {
        return $this->elements()
            ->where('element_type', 'startEvent')
            ->first();
    }

    public function findElement(string $elementId): ?ProcessElement
    {
        return $this->elements()->where('element_id', $elementId)->first();
    }

    public function isLatestVersion(): bool
    {
        return $this->is_latest;
    }

    public function canStartInstance(): bool
    {
        return $this->status === ProcessDefinitionStatus::Published;
    }
}
