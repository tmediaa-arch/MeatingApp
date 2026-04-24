<?php

declare(strict_types=1);

/*
 * تنظیمات LDAP پیش‌فرض برای directorytree/ldaprecord-laravel.
 *
 * هر IntegrationProvider اتصال LDAP خود را در runtime می‌سازد و به
 * Container اضافه می‌کند. این فایل فقط fallback default است.
 */
return [

    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts' => explode(',', env('LDAP_HOST', '127.0.0.1')),
            'username' => env('LDAP_USERNAME'),
            'password' => env('LDAP_PASSWORD'),
            'port' => (int) env('LDAP_PORT', 389),
            'base_dn' => env('LDAP_BASE_DN', 'dc=example,dc=local'),
            'timeout' => (int) env('LDAP_TIMEOUT', 5),
            'use_ssl' => (bool) env('LDAP_SSL', false),
            'use_tls' => (bool) env('LDAP_TLS', false),
            'use_sasl' => (bool) env('LDAP_SASL', false),
            'version' => 3,
        ],
    ],

    'logging' => [
        'enabled' => env('LDAP_LOGGING', false),
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver' => env('LDAP_CACHE_DRIVER', 'file'),
    ],

];
