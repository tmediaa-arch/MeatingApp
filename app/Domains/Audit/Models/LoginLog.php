<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'login_logs';

    protected $fillable = [
        'user_id', 'username_attempted',
        'result', 'auth_method',
        'ip_address', 'user_agent', 'device_fingerprint',
        'country_code', 'city',
        'session_id', 'logged_out_at', 'logout_reason',
        'metadata',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_out_at' => 'datetime',
            'performed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function ($model) {
            // فقط logged_out_at و logout_reason قابل update هستند
            $allowed = ['logged_out_at', 'logout_reason'];
            foreach (array_keys($model->getDirty()) as $field) {
                if (!in_array($field, $allowed, true)) {
                    throw new \LogicException(
                        "LoginLog field '{$field}' is immutable after creation."
                    );
                }
            }
        });

        static::deleting(function () {
            throw new \LogicException('LoginLog is append-only.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('result', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('result', 'like', 'failed_%');
    }

    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('performed_at', '>=', now()->subMinutes($minutes));
    }
}
