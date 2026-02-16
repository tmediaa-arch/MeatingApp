<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Domain — Organizational Units (واحدهای سازمانی)
 *
 * طراحی پیشنهادی: به‌جای جدول جداگانه برای department/division/unit،
 * یک جدول org_units با ستون type و parent_id نگه می‌داریم. این الگو:
 * - انعطاف بیشتر برای سازمان‌هایی با عمق متفاوت
 * - JOIN ساده‌تر
 * - اجازه می‌دهد ساختار درختی با هر عمقی داشته باشیم
 *
 * type می‌تواند یکی از: organization, deputy (معاونت), department (مدیریت),
 *   bureau (اداره), unit (واحد), office (دفتر), branch (شعبه), other
 *
 * برای بهینه‌سازی query درختی، از path materialized استفاده می‌کنیم:
 *   path = "1/5/12/23" تا با LIKE 'parent_path/%' فرزندان را سریع پیدا کنیم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();

            $table->string('code', 50)->comment('کد یکتای واحد در سازمان');
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('english_name', 200)->nullable();

            // نوع و سطح در درخت
            $table->string('type', 30)->index()
                ->comment('deputy|department|bureau|unit|office|branch|other');
            $table->unsignedTinyInteger('level')->default(1)->index()
                ->comment('عمق در درخت — برای جستجوی سریع');
            $table->string('path', 500)->nullable()->index()
                ->comment('materialized path مثل "1/5/12"');

            // اطلاعات تماس
            $table->string('phone', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('location_floor', 50)->nullable();
            $table->string('location_building', 100)->nullable();

            // مدیر واحد (به‌صورت soft pointer — کارمند می‌تواند بعداً تغییر کند)
            // FK واقعی به employees بعد از ایجاد employees ALTER می‌شود
            $table->unsignedBigInteger('manager_employee_id')->nullable()->index();

            // مرتب‌سازی نمایش
            $table->unsignedInteger('display_order')->default(0);

            $table->boolean('is_active')->default(true)->index();
            $table->date('activated_at')->nullable();
            $table->date('deactivated_at')->nullable();
            $table->json('metadata')->nullable();

            // ردیابی
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌های ترکیبی
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);
            $table->index(['organization_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
