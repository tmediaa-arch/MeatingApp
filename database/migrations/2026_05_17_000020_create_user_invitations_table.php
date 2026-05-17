<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_invitations — دعوت‌نامه‌های ورود به سامانه.
 *
 * هر دعوت یک token یکتا دارد که در قالب لینک پیامک می‌شود. با کلیک کاربر،
 * در صورت نبودِ حساب، حساب ساخته شده و کد ورود (OTP) ارسال می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();

            $table->string('token', 64)->unique();
            $table->string('mobile', 20)->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();

            $table->foreignId('invited_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
