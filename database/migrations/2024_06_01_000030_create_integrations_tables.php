<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Integrations domain
 *
 * - integration_providers: LDAP/AD، SAML SSO، HRS، Webhook outbound
 * - integration_sync_logs: append-only — هر بار sync چه شد
 * - ldap_user_mappings: نگاشت کاربر LDAP به کاربر داخلی
 * - sso_sessions: ردیابی session های SAML
 * - api_webhooks: webhook های outbound برای integration با سامانه‌های خارجی
 * - webhook_deliveries: append-only لاگ تحویل
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('key', 100); // primary_ldap, hrs_main, saml_idp_1
            $table->string('display_name', 200);

            // نوع
            $table->string('type', 30); // ldap, saml_sso, hrs, oauth_oidc, webhook
            $table->string('driver', 50); // active_directory, openldap, generic_saml, hrs_rest_v1

            // تنظیمات (encrypted در فاز آینده)
            $table->json('config'); // host, port, base_dn, credentials, ...

            // وضعیت
            $table->boolean('is_active')->default(false);
            $table->string('health_status', 20)->default('unknown'); // healthy, degraded, down, unknown
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_health_message')->nullable();

            // sync configuration (فقط برای HRS و LDAP)
            $table->boolean('auto_sync_enabled')->default(false);
            $table->string('sync_schedule', 100)->nullable(); // cron expression
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();

            // statistics
            $table->unsignedInteger('total_syncs')->default(0);
            $table->unsignedInteger('successful_syncs')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'key']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('integration_providers')->cascadeOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('sync_type', 30); // manual, scheduled, on_demand
            $table->string('direction', 20); // inbound, outbound, bidirectional

            // وضعیت
            $table->string('status', 20); // running, completed, failed, partial
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // statistics
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            // اطلاعات تشخیص
            $table->json('error_summary')->nullable();
            $table->text('full_log')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
            $table->index('status');
        });

        // append-only enforcement در Model
        Schema::create('ldap_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('integration_providers')->cascadeOnDelete();

            $table->string('ldap_dn', 500); // CN=User,OU=Users,DC=corp,DC=local
            $table->string('ldap_uid', 200); // sAMAccountName / uid
            $table->string('ldap_guid', 100)->nullable(); // objectGUID
            $table->string('ldap_email', 200)->nullable();

            $table->json('ldap_attributes')->nullable(); // snapshot آخرین sync
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_disabled_in_ldap')->default(false);

            $table->timestamps();

            $table->unique(['provider_id', 'ldap_uid']);
            $table->index('user_id');
        });

        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('integration_providers')->cascadeOnDelete();

            $table->string('session_id', 200)->unique(); // SAML SessionIndex
            $table->string('name_id', 200); // SAML NameID
            $table->string('name_id_format', 200)->nullable();

            $table->json('attributes')->nullable(); // SAML attributes
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('authenticated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'logged_out_at']);
            $table->index('expires_at');
        });

        Schema::create('api_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('name', 200);
            $table->string('url', 500);

            // event هایی که این webhook به آنها reactive است
            // meeting.created, meeting.cancelled, resolution.approved, task.completed, ...
            $table->json('events');

            // امنیت
            $table->string('secret', 100); // HMAC signing secret
            $table->boolean('verify_ssl')->default(true);

            // وضعیت
            $table->boolean('is_active')->default(true);
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);

            // پیکربندی delivery
            $table->unsignedInteger('max_retries')->default(5);
            $table->unsignedInteger('timeout_seconds')->default(30);

            $table->json('headers')->nullable(); // headers اضافی
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        // append-only enforcement
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('api_webhooks')->cascadeOnDelete();

            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('payload_signature', 200)->nullable();

            // وضعیت
            $table->string('status', 20); // pending, success, failed, retrying
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->unsignedInteger('total_duration_ms')->nullable();

            $table->timestamps();

            $table->index(['webhook_id', 'status', 'next_retry_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('api_webhooks');
        Schema::dropIfExists('sso_sessions');
        Schema::dropIfExists('ldap_user_mappings');
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('integration_providers');
    }
};
