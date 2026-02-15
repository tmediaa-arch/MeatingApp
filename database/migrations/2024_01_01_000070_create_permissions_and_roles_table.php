<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity Domain — Roles, Permissions, Access Policies
 *
 * این migration ساختار permissions/roles را با گسترش‌های مهم می‌سازد:
 *
 * 1. RBAC استاندارد:
 *    - permissions, roles, role_has_permissions, model_has_roles, model_has_permissions
 *    - سازگار با spatie/laravel-permission (بنابراین می‌توان از helpers اش استفاده کرد)
 *
 * 2. گسترش‌های اختصاصی:
 *    - permissions دارای ستون category و is_system هستند برای UI بهتر
 *    - roles دارای ستون priority برای مرتب‌سازی و scope برای محدودسازی به سازمان
 *    - model_has_roles دارای ستون org_unit_id برای scope-restricted role
 *      (مثلاً «مدیر واحد» فقط در یک واحد خاص فعال است)
 *
 * 3. access_policies برای ABAC:
 *    - قوانین داینامیک بر اساس attribute (واحد، محرمانگی، وضعیت، ...)
 *    - این به ما اجازه می‌دهد قوانینی مثل
 *      «هر کاربر فقط جلساتی را ببیند که عضو آن است یا سطح محرمانگی‌اش کافی است»
 *      را بدون hard-code کردن در کد، در دیتابیس ذخیره کنیم.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PERMISSIONS
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('guard_name', 50);

            // گسترش‌های MMS
            $table->string('category', 50)->nullable()->index()
                ->comment('grouping برای UI: identity|meetings|workflow|...');
            $table->string('display_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)
                ->comment('permission سیستمی — قابل حذف نیست');

            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // ROLES
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('guard_name', 50);

            // گسترش‌های MMS
            $table->string('display_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(0)->index()
                ->comment('برای مرتب‌سازی نقش‌ها و escalation');
            $table->boolean('is_system')->default(false)
                ->comment('نقش سیستمی — قابل حذف یا تغییر نام نیست');
            $table->boolean('is_assignable')->default(true)
                ->comment('قابل انتساب در UI');

            // محدودسازی نقش به سازمان (در حالت multi-tenant آینده)
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['name', 'guard_name', 'organization_id']);
        });

        // ROLE -> PERMISSION
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        // MODEL -> ROLE (با scope سازمانی)
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // گسترش: scope این انتساب به یک واحد خاص (اختیاری)
            $table->foreignId('org_unit_id')->nullable()
                ->constrained('org_units')->nullOnDelete()
                ->comment('اگر نقش محدود به یک واحد است');

            // بازه اعتبار (برای انتساب موقت)
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable()->index();

            // ردیابی
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_reason')->nullable();

            // primary key استاندارد Spatie — بدون org_unit_id
            $table->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');

            $table->index(['model_id', 'model_type'], 'mhr_model_idx');
            $table->index(['role_id', 'org_unit_id']);
        });

        // MODEL -> PERMISSION (override مستقیم — کمتر استفاده می‌شود)
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // grant یا deny
            $table->string('mode', 10)->default('grant')
                ->comment('grant|deny');

            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->text('grant_reason')->nullable();

            $table->index(['model_id', 'model_type'], 'mhp_model_idx');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
        });

        // ACCESS POLICIES — ABAC
        Schema::create('access_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // موجودیت هدف
            $table->string('subject_type', 100)->index()
                ->comment('کدام مدل: Meeting|RoomReservation|Task|...');

            // عملیات هدف
            $table->string('action', 50)->index()
                ->comment('view|update|delete|approve|sign|...');

            // اثر سیاست
            $table->string('effect', 10)->default('allow')
                ->comment('allow|deny');

            // شرایط (JSON — توسط Service ارزیابی می‌شود)
            // مثال:
            // { "all": [
            //   { "fact": "user.role", "operator": "in", "value": ["secretary"] },
            //   { "fact": "subject.confidentiality_level", "operator": "lte", "value": "user.clearance_level" }
            // ]}
            $table->json('conditions');

            // اولویت ارزیابی (deny با priority بالاتر بر allow غلبه می‌کند)
            $table->unsignedInteger('priority')->default(100);

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'action', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_policies');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
