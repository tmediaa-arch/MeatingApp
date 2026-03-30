<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Adapters\HrsRestAdapter;
use App\Domains\Integrations\Adapters\LdapActiveDirectoryAdapter;
use App\Domains\Integrations\Adapters\NullLdapAdapter;
use App\Domains\Integrations\Adapters\SamlGenericAdapter;
use App\Domains\Integrations\Contracts\IntegrationDriverInterface;
use App\Domains\Integrations\Enums\IntegrationType;
use App\Domains\Integrations\Models\IntegrationProvider;

/**
 * IntegrationProviderManager — resolver مرکزی برای driverها.
 *
 * این کلاس map type+driver → AdapterClass را مدیریت می‌کند و امکان
 * ثبت driverهای custom را فراهم می‌سازد.
 */
class IntegrationProviderManager
{
    /**
     * @var array<string, class-string<IntegrationDriverInterface>>
     */
    private array $drivers = [];

    public function __construct()
    {
        // driverهای پیش‌فرض
        $this->register(IntegrationType::Ldap, 'active_directory', LdapActiveDirectoryAdapter::class);
        $this->register(IntegrationType::Ldap, 'openldap', LdapActiveDirectoryAdapter::class); // قابل تعمیم
        $this->register(IntegrationType::Ldap, 'null', NullLdapAdapter::class);
        $this->register(IntegrationType::SamlSso, 'generic', SamlGenericAdapter::class);
        $this->register(IntegrationType::Hrs, 'rest_v1', HrsRestAdapter::class);
    }

    public function register(IntegrationType $type, string $driver, string $adapterClass): void
    {
        $this->drivers["{$type->value}:{$driver}"] = $adapterClass;
    }

    public function resolve(IntegrationProvider $provider): IntegrationDriverInterface
    {
        $key = "{$provider->type->value}:{$provider->driver}";
        $class = $this->drivers[$key] ?? null;

        if (!$class) {
            throw new \LogicException("driver یافت نشد برای: {$key}");
        }

        return app($class, ['provider' => $provider]);
    }

    public function listFor(IntegrationType $type): array
    {
        $prefix = "{$type->value}:";
        $out = [];
        foreach ($this->drivers as $key => $class) {
            if (str_starts_with($key, $prefix)) {
                $driver = substr($key, strlen($prefix));
                $out[$driver] = $class;
            }
        }
        return $out;
    }
}
