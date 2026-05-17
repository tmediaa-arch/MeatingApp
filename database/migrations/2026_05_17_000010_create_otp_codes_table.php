<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * otp_codes — کدهای یک‌بارمصرف اعتبارسنجی.
 *
 * کد به‌صورت hash ذخیره می‌شود. هر کد purpose دارد (login | invite) و پس از
 * مصرف یا انقضا بی‌اعتبار می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            $table->string('mobile', 20)->index();
            $table->string('code_hash');
            $table->string('purpose', 20)->default('login'); // login | invite

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->ipAddress('ip_address')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['mobile', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
