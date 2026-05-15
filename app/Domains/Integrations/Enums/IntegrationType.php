<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum IntegrationType: string implements HasIcon, HasLabel
{
    case Ldap = 'ldap';
    case SamlSso = 'saml_sso';
    case Hrs = 'hrs';
    case OAuthOidc = 'oauth_oidc';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::Ldap => 'LDAP / Active Directory',
            self::SamlSso => 'SAML SSO',
            self::Hrs => 'سامانه منابع انسانی (HRS)',
            self::OAuthOidc => 'OAuth 2.0 / OIDC',
            self::Webhook => 'Webhook',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ldap => 'heroicon-o-server-stack',
            self::SamlSso => 'heroicon-o-key',
            self::Hrs => 'heroicon-o-user-group',
            self::OAuthOidc => 'heroicon-o-lock-closed',
            self::Webhook => 'heroicon-o-arrow-up-on-square',
        };
    }

    public function supportsSync(): bool
    {
        return in_array($this, [self::Ldap, self::Hrs], true);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }
}
