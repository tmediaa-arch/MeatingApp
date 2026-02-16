<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class EmployeePositionHistory
 *
 * این جدول append-only است:
 * - رکورد ایجاد می‌شود زمانی که کارمندی به پستی منصوب می‌شود
 * - ended_at پر می‌شود زمانی که انتساب پایان می‌یابد
 * - حذف رکورد ممنوع است (در سطح Model و دیتابیس)
 *
 * چرا append-only؟
 * - حفظ تاریخچه برای audit و گزارش‌گیری «در فلان تاریخ، چه کسی این پست را داشت»
 * - بسیار مهم برای ممیزی در سازمان‌های دولتی
 */
class EmployeePositionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'position_id', 'org_unit_id',
        'assignment_type',
        'started_at', 'ended_at',
        'end_reason',
        'decree_number', 'decree_date',
        'notes', 'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'decree_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Append-only enforcement
     */
    protected static function booted(): void
    {
        static::updating(function ($model) {
            // فقط ended_at و end_reason و notes قابل update هستند
            $dirty = $model->getDirty();
            $allowedToUpdate = ['ended_at', 'end_reason', 'notes', 'updated_at'];

            foreach (array_keys($dirty) as $field) {
                if (!in_array($field, $allowedToUpdate, true)) {
                    throw new \LogicException(
                        "Cannot update field '{$field}' on EmployeePositionHistory — this table is append-only."
                    );
                }
            }
        });

        static::deleting(function () {
            throw new \LogicException(
                'Cannot delete EmployeePositionHistory records — this table is append-only.'
            );
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeOnDate(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query
            ->where('started_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('ended_at')
                  ->orWhere('ended_at', '>', $date);
            });
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
