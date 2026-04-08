<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'dashboard_id',
        'is_pinned', 'widget_overrides', 'custom_filters',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'widget_overrides' => 'array',
        'custom_filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
