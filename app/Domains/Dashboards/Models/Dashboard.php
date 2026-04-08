<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'organization_id', 'key', 'display_name', 'description',
        'allowed_roles', 'icon', 'color',
        'is_default', 'is_active', 'sort_order',
        'metadata',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('row')->orderBy('column');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserDashboardPreference::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, User $user)
    {
        $roleNames = $user->getRoleNames()->toArray();

        return $query->active()->where(function ($q) use ($roleNames) {
            $q->whereNull('allowed_roles');
            foreach ($roleNames as $role) {
                $q->orWhereJsonContains('allowed_roles', $role);
            }
        });
    }

    public function canBeViewedBy(User $user): bool
    {
        if (!$this->allowed_roles) return true; // public
        $userRoles = $user->getRoleNames()->toArray();
        return count(array_intersect($this->allowed_roles, $userRoles)) > 0;
    }
}
