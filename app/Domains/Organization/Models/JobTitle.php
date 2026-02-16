<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobTitle extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'english_name',
        'description',
        'management_level',
        'rank',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeManagerial(Builder $query): Builder
    {
        return $query->whereIn('management_level', ['executive', 'senior_manager', 'manager']);
    }
}
