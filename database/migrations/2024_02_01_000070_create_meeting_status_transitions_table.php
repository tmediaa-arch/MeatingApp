<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // تاریخچه تغییر وضعیت جلسه — append-only
        // برای ردیابی دقیق هر transition با timestamp و user
        Schema::create('meeting_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('reason')->nullable();

            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('on_behalf_of_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('triggered_via', 50)->default('manual')
                ->comment('manual | api | scheduler | workflow');

            $table->json('snapshot')->nullable()
                ->comment('snapshot از فیلدهای کلیدی در زمان transition برای forensic analysis');

            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index('meeting_id');
            $table->index('occurred_at');
            $table->index(['meeting_id', 'occurred_at']);
        });

        // تخصیص فایل و پیوست به جلسه — placeholder برای ارتباط با Files domain فاز ۳
        // در اینجا فقط reference به path یا URL ذخیره می‌شود
        Schema::create('meeting_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->nullable()
                ->constrained('meeting_agenda_items')->cascadeOnDelete()
                ->comment('در صورت تعلق به یک دستور خاص');

            $table->string('title', 300);
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 300)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();

            $table->enum('attachment_type', [
                'agenda',         // دستور جلسه
                'background',     // پیش‌مطالعه
                'presentation',   // ارائه
                'supporting',     // مستندات پشتیبان
                'minutes',        // صورتجلسه (فاز ۳)
                'recording',      // ضبط (فاز ۵)
                'other',
            ])->default('background');

            $table->enum('visibility', [
                'all_participants',
                'voting_members',
                'chairperson_secretary',
                'specific_roles',
                'private',
            ])->default('all_participants');
            $table->json('visible_to_roles')->nullable();

            $table->enum('confidentiality_level', ['public', 'internal', 'confidential', 'secret'])
                ->nullable();

            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('uploaded_at');

            $table->boolean('is_circulated_before_meeting')->default(false);
            $table->dateTime('circulated_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('meeting_id');
            $table->index('agenda_item_id');
            $table->index('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attachments');
        Schema::dropIfExists('meeting_status_transitions');
    }
};
