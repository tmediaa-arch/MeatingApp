<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationPreference — ترجیحات اعلان هر کاربر برای هر template.
 */
class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_key',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
        'push_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'quiet_hours_start' => 'datetime',
        'quiet_hours_end' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
