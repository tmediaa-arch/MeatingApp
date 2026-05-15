<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LdapUserMapping — نگاشت کاربر LDAP به کاربر داخلی سامانه.
 */
class LdapUserMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'ldap_dn',
        'ldap_uid',
        'ldap_guid',
        'ldap_email',
        'ldap_attributes',
        'last_synced_at',
        'is_disabled_in_ldap',
    ];

    protected $casts = [
        'ldap_attributes' => 'array',
        'last_synced_at' => 'datetime',
        'is_disabled_in_ldap' => 'boolean',
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
