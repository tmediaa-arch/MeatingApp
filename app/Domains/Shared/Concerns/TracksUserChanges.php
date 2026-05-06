<?php

declare(strict_types=1);

namespace App\Domains\Shared\Concerns;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Trait TracksUserChanges
 *
 * مدل‌هایی که این trait را استفاده می‌کنند، created_by و updated_by
 * را به‌صورت خودکار از Auth::id() پر می‌کنند.
 *
 * نیاز به ستون‌های created_by و updated_by در جدول دارد.
 */
trait TracksUserChanges
{
    /**
     * Cache for column existence checks (per table per request).
     *
     * @var array<string, array{created_by:bool, updated_by:bool}>
     */
    private static array $tracksUserColumnsCache = [];

    public static function bootTracksUserChanges(): void
    {
        static::creating(function ($model) {
            if (!Auth::check()) return;
            $cols = self::tracksUserColumnsFor($model->getTable());

            if ($cols['created_by']) {
                $model->created_by ??= Auth::id();
            }
            if ($cols['updated_by']) {
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (!Auth::check()) return;
            $cols = self::tracksUserColumnsFor($model->getTable());

            if ($cols['updated_by']) {
                $model->updated_by = Auth::id();
            }
        });
    }

    private static function tracksUserColumnsFor(string $table): array
    {
        if (!isset(self::$tracksUserColumnsCache[$table])) {
            self::$tracksUserColumnsCache[$table] = [
                'created_by' => Schema::hasColumn($table, 'created_by'),
                'updated_by' => Schema::hasColumn($table, 'updated_by'),
            ];
        }

        return self::$tracksUserColumnsCache[$table];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
