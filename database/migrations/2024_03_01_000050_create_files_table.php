<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مرکز مدیریت فایل — یکپارچه با Spatie MediaLibrary در فاز ۴
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            // mapping اختیاری به entity مادر (polymorphic)
            $table->nullableMorphs('owner');

            $table->string('title', 300);
            $table->text('description')->nullable();

            // اطلاعات فیزیکی فایل
            $table->string('disk', 30)->default('local');
            $table->string('file_path', 500);
            $table->string('file_name', 300);
            $table->string('original_name', 300);
            $table->string('mime_type', 150);
            $table->string('extension', 20);
            $table->unsignedBigInteger('file_size_bytes');

            // hashing برای integrity
            $table->string('file_hash_sha256', 128)->index();
            $table->string('file_hash_md5', 64)->nullable();

            // امنیت
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_method', 30)->nullable(); // aes-256-cbc
            $table->boolean('has_watermark')->default(false);

            // دسته‌بندی
            $table->string('category', 50)->nullable()->index();
            // attachment, scan, photo, document, report, ...

            // محرمانگی
            $table->string('confidentiality_level', 20)->default('internal')->index();

            // versioning ساده — file_path اشاره به آخرین نسخه
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('previous_version_file_id')->nullable()
                ->constrained('files')->nullOnDelete();

            // OCR (اگر اعمال شده)
            $table->boolean('is_ocred')->default(false);
            $table->longText('ocr_text')->nullable();
            $table->timestamp('ocred_at')->nullable();

            // virus scan
            $table->string('virus_scan_status', 20)->default('pending');
            // pending, clean, infected, error
            $table->timestamp('virus_scanned_at')->nullable();

            // metadata extracted (EXIF, PDF metadata, ...)
            $table->json('extracted_metadata')->nullable();

            // expiration
            $table->timestamp('expires_at')->nullable();

            // tags
            $table->json('tags')->nullable();

            // creator
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'category']);
            // owner_type/owner_id index توسط nullableMorphs() خودکار ساخته می‌شود
            $table->index('virus_scan_status');
        });

        // مجوزهای دسترسی به فایل — مازاد بر confidentiality
        Schema::create('file_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();

            // یا user یا role یا org_unit
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreignId('org_unit_id')->nullable()
                ->constrained('org_units')->cascadeOnDelete();

            // دسترسی‌ها
            $table->boolean('can_view')->default(true);
            $table->boolean('can_download')->default(true);
            $table->boolean('can_share')->default(false);
            $table->boolean('can_delete')->default(false);

            // expiration
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('granted_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('file_id');
        });

        // لاگ دسترسی به فایل — append-only، برای ممیزی
        Schema::create('file_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // action: viewed, downloaded, shared, deleted, opened
            $table->string('action', 30);

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['file_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_access_logs');
        Schema::dropIfExists('file_permissions');
        Schema::dropIfExists('files');
    }
};
