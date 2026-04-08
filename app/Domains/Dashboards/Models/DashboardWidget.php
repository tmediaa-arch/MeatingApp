<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id', 'key', 'display_name',
        'widget_class', 'type', 'chart_type',
        'row', 'column', 'width', 'height',
        'config', 'input_params',
        'refresh_interval_seconds', 'is_cacheable',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'input_params' => 'array',
        'is_cacheable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
