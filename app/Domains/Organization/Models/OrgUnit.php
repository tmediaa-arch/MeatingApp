<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class OrgUnit
 *
 * واحد سازمانی. درختی با استفاده از materialized path.
 *
 * نکات طراحی:
 * - path به‌صورت "1/5/12" ذخیره می‌شود — برای descendants سریع از LIKE استفاده می‌کنیم
 * - level به‌صورت خودکار محاسبه می‌شود (در Observer)
 * - حذف واحد دارای فرزند باید validate شود (در Action یا Policy)
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OrgUnitType $type
 * @property int $level
 * @property string|null $path
 * @property int|null $parent_id
 * @property int $organization_id
 * @property int|null $manager_employee_id
 */
class OrgUnit extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;
    use TracksUserChanges;

    protected $table = 'org_units';

    protected $fillable = [
        'organization_id', 'parent_id',
        'code', 'name', 'short_name', 'english_name',
        'type', 'level', 'path',
        'phone', 'email', 'address',
        'location_floor', 'location_building',
        'manager_employee_id',
        'display_order',
        'is_active', 'activated_at', 'deactivated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrgUnitType::class,
            'level' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'date',
            'deactivated_at' => 'date',
            'metadata' => 'array',
        ];
    }

    // ------------------------- Relationships ------------------------- //

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id')
            ->orderBy('display_order')
            ->orderBy('name');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'current_org_unit_id');
    }

    // ------------------------- Tree Operations ------------------------- //

    /**
     * تمام نوادگان (descendants) با استفاده از path
     */
    public function descendants(): Builder
    {
        return static::query()
            ->where('organization_id', $this->organization_id)
            ->where('id', '!=', $this->id)
            ->where(function ($q) {
                $q->where('path', 'like', $this->descendantPathPattern());
            });
    }

    /**
     * نوادگان به‌علاوه خود
     */
    public function descendantsAndSelf(): Builder
    {
        return static::query()
            ->where('organization_id', $this->organization_id)
            ->where(function ($q) {
                $q->where('id', $this->id)
                  ->orWhere('path', 'like', $this->descendantPathPattern());
            });
    }

    /**
     * تمام اجداد (ancestors)
     */
    public function ancestors(): Collection
    {
        if (!$this->path) {
            return new Collection();
        }

        $ids = array_filter(explode('/', $this->path));
        // آخرین id خود این رکورد است، پس آن را حذف می‌کنیم
        array_pop($ids);

        if (empty($ids)) {
            return new Collection();
        }

        return static::whereIn('id', $ids)
            ->orderByRaw("FIELD(id, " . implode(',', $ids) . ")")
            ->get();
    }

    public function isAncestorOf(self $other): bool
    {
        if (!$other->path || !$this->id) {
            return false;
        }

        return str_starts_with($other->path . '/', $this->path . '/' . $this->id . '/')
            || str_contains('/' . $other->path . '/', '/' . $this->id . '/');
    }

    public function isDescendantOf(self $other): bool
    {
        return $other->isAncestorOf($this);
    }

    /**
     * pattern برای جستجوی LIKE descendant ها
     */
    protected function descendantPathPattern(): string
    {
        if (!$this->id) {
            return '0/%'; // ناممکن — اگر هنوز ذخیره نشده
        }

        if (!$this->path) {
            return $this->id . '/%';
        }

        return $this->path . '/' . $this->id . '/%';
    }

    /**
     * محاسبه path برای ذخیره
     */
    public function calculatePath(): string
    {
        if (!$this->parent_id) {
            return (string) $this->id;
        }

        $parent = $this->parent ?? static::find($this->parent_id);
        if (!$parent) {
            return (string) $this->id;
        }

        return $parent->path . '/' . $this->id;
    }

    /**
     * محاسبه level بر اساس parent
     */
    public function calculateLevel(): int
    {
        if (!$this->parent_id) {
            return 1;
        }

        $parent = $this->parent ?? static::find($this->parent_id);
        return $parent ? $parent->level + 1 : 1;
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType(Builder $query, OrgUnitType|string $type): Builder
    {
        $value = $type instanceof OrgUnitType ? $type->value : $type;
        return $query->where('type', $value);
    }

    public function scopeAtLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }
}
