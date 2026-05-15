<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SsoSession — ردیابی session های احراز هویت SAML/SSO.
 */
class SsoSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'session_id',
        'name_id',
        'name_id_format',
        'attributes',
        'ip_address',
        'user_agent',
        'authenticated_at',
        'expires_at',
        'logged_out_at',
    ];

    protected $casts = [
        'attributes' => 'array',
        'authenticated_at' => 'datetime',
        'expires_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class, 'provider_id');
    }
}
