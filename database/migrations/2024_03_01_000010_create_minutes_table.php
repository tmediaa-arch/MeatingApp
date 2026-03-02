<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations');

            // شماره صورتجلسه — تولید خودکار: ${org_code}-MIN-${year}-####
            $table->string('minute_number', 50)->unique();

            $table->string('title', 500);

            // محتوای فعلی (live editing) — برای تاریخچه به minute_versions می‌رود
            $table->longText('content_html')->nullable();
            $table->longText('content_text')->nullable(); // plain برای جستجو

            // خلاصه و تصمیم‌های اصلی برای نمایش سریع
            $table->text('summary')->nullable();
            $table->json('key_decisions')->nullable();

            // وضعیت چرخه عمر صورتجلسه:
            // draft → review → signed → published → archived
            // مسیر تنبیهی: revoked (پس از published)
            $table->string('status', 30)->default('draft')->index();

            // امضاهای الزامی
            $table->foreignId('secretary_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('secretary_signed_at')->nullable();

            $table->foreignId('chairperson_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('chairperson_signed_at')->nullable();

            // ابرداده تأیید نهایی
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users');

            // PDF تولید شده
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_hash', 128)->nullable(); // sha256 برای integrity
            $table->timestamp('pdf_generated_at')->nullable();

            // نسخه فعلی (در minute_versions نسخه‌ها نگه‌داری می‌شوند)
            $table->unsignedInteger('current_version')->default(1);

            // محرمانگی
            $table->string('confidentiality_level', 20)->default('internal')->index();

            // متادیتای آزاد
            $table->json('metadata')->nullable();

            // audit
            $table->foreignId('creator_user_id')->constrained('users');
            $table->foreignId('updater_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('minute_number');
        });

        // نسخه‌بندی append-only — هر ویرایش یک snapshot
        Schema::create('minute_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            $table->longText('content_html');
            $table->longText('content_text')->nullable();
            $table->string('change_summary', 1000)->nullable();

            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('created_at');

            // append-only
            $table->unique(['minute_id', 'version_number']);
            $table->index('created_at');
        });

        // امضاهای دیجیتال — append-only
        Schema::create('minute_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('signer_user_id')->constrained('users');
            $table->foreignId('signer_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // role: secretary, chairperson, other_signer
            $table->string('signer_role', 50);

            // hash محتوای امضا شده (sha256 از content_html در آن لحظه)
            $table->string('content_hash', 128);

            // داده‌های امضا (در فاز ۳ ساده — TBT/PKCS#7 در آینده)
            $table->string('signature_method', 30)->default('simple'); // simple | otp | pki
            $table->text('signature_data')->nullable();

            // metadata امضا
            $table->ipAddress('signer_ip')->nullable();
            $table->string('signer_user_agent', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(['minute_id', 'signer_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_signatures');
        Schema::dropIfExists('minute_versions');
        Schema::dropIfExists('minutes');
    }
};
