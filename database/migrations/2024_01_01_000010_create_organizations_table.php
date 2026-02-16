<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Domain — Organizations
 *
 * موجودیت ریشه ساختار سازمانی. در حالت تک‌سازمانی فقط یک رکورد دارد.
 * در آینده (فاز ۶) برای multi-tenant قابل گسترش است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('کد یکتای سازمان');
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('english_name', 200)->nullable();

            // مشخصات
            $table->string('national_id', 20)->nullable()->unique()->comment('شناسه ملی سازمان');
            $table->string('economic_code', 20)->nullable();
            $table->string('registration_number', 50)->nullable();

            // اطلاعات تماس
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();

            // برندینگ
            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
