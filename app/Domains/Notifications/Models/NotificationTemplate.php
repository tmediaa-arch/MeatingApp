<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قالب اعلان — یک‌بار توسط ادمین تعریف می‌شود و سپس
 * در سراسر سامانه از طریق key استفاده می‌شود.
 */
class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'key', 'display_name', 'description',
        'supported_channels', 'available_variables',
        'is_user_disablable', 'is_admin_editable', 'priority',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'supported_channels' => 'array',
        'available_variables' => 'array',
        'metadata' => 'array',
        'is_user_disablable' => 'boolean',
        'is_admin_editable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(NotificationTemplateChannel::class, 'template_id');
    }

    public function getChannelContent(string $channel): ?NotificationTemplateChannel
    {
        return $this->channels()->where('channel', $channel)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }
}
