<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Models;

use App\Domains\Organization\Models\Organization;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property VideoConferenceDriver $driver
 * @property string $config_encrypted
 * @property bool $is_active
 * @property bool $is_default
 * @property HealthStatus $health_status
 */
class VideoConferenceProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'video_conference_providers';

    protected static function newFactory()
    {
        return \Database\Factories\VideoConferenceProviderFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'name',
        'driver',
        'config_encrypted',
        'max_concurrent_meetings',
        'max_participants_per_meeting',
        'supports_recording',
        'supports_streaming',
        'supports_breakout_rooms',
        'is_active',
        'is_default',
        'last_health_check_at',
        'health_status',
        'health_message',
    ];

    protected $casts = [
        'driver' => VideoConferenceDriver::class,
        'health_status' => HealthStatus::class,
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'supports_recording' => 'boolean',
        'supports_streaming' => 'boolean',
        'supports_breakout_rooms' => 'boolean',
        'last_health_check_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(VideoConferenceRoom::class, 'provider_id');
    }

    public function activeRooms(): HasMany
    {
        return $this->hasMany(VideoConferenceRoom::class, 'provider_id')
            ->whereIn('status', ['scheduled', 'starting', 'in_progress']);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForOrganization(Builder $q, int $orgId): Builder
    {
        return $q->where('organization_id', $orgId);
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->health_status->isUsable();
    }

    public function hasReachedConcurrentLimit(): bool
    {
        if ($this->max_concurrent_meetings === null) return false;
        return $this->activeRooms()->count() >= $this->max_concurrent_meetings;
    }
}
