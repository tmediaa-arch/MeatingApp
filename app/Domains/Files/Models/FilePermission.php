<?php

declare(strict_types=1);

namespace App\Domains\Files\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id', 'user_id', 'role_id', 'org_unit_id',
        'can_view', 'can_download', 'can_share', 'can_delete',
        'expires_at', 'granted_by_user_id',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_download' => 'boolean',
        'can_share' => 'boolean',
        'can_delete' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function file(): BelongsTo { return $this->belongsTo(File::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function orgUnit(): BelongsTo { return $this->belongsTo(OrgUnit::class); }
    public function grantedBy(): BelongsTo { return $this->belongsTo(User::class, 'granted_by_user_id'); }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
