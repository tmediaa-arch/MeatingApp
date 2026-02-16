<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'short_name', 'english_name',
        'national_id', 'economic_code', 'registration_number',
        'phone', 'fax', 'email', 'website',
        'address', 'postal_code',
        'logo_path', 'letterhead_path',
        'primary_color', 'secondary_color',
        'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function orgUnits(): HasMany
    {
        return $this->hasMany(OrgUnit::class);
    }

    /**
     * فقط واحدهای ریشه (parent_id = null)
     */
    public function rootUnits(): HasMany
    {
        return $this->hasMany(OrgUnit::class)->whereNull('parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
