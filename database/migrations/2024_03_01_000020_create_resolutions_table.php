<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')->constrained('minutes')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations');

            // شماره مصوبه: ${org_code}-RES-${year}-####
            $table->string('resolution_number', 50)->unique();

            // مرجع به دستور جلسه (اختیاری)
            $table->foreignId('agenda_item_id')->nullable()
                ->constrained('meeting_agenda_items')->nullOnDelete();

            $table->string('title', 500);
            $table->longText('content');
            $table->longText('rationale')->nullable(); // دلیل/توجیه

            // نوع مصوبه
            $table->string('type', 30)->default('decision');
            // decision (تصمیم), directive (دستورالعمل), recommendation (توصیه),
            // policy_change (تغییر سیاست), budget (بودجه), other

            // اولویت
            $table->string('priority', 20)->default('normal');
            // critical, high, normal, low

            // وضعیت مصوبه
            // draft → voted → approved → in_execution → completed / cancelled / failed
            $table->string('status', 30)->default('draft')->index();

            // رأی‌گیری
            $table->boolean('requires_voting')->default(false);
            $table->string('voting_type', 30)->nullable(); // open, secret, weighted
            $table->unsignedInteger('quorum_required')->nullable();
            $table->unsignedInteger('majority_threshold_percent')->nullable(); // 50, 66, ...
            $table->timestamp('voting_opened_at')->nullable();
            $table->timestamp('voting_closed_at')->nullable();

            // نتایج رأی (cached)
            $table->unsignedInteger('votes_for')->default(0);
            $table->unsignedInteger('votes_against')->default(0);
            $table->unsignedInteger('votes_abstain')->default(0);
            $table->unsignedInteger('voters_total')->default(0);

            // مهلت اجرا
            $table->date('due_date')->nullable();

            // تاریخ‌های وضعیت
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            // متادیتا
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            // ابرداده
            $table->foreignId('creator_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('due_date');
            $table->index('priority');
        });

        // آرا — append-only، هر کاربر یک رأی در یک مصوبه
        Schema::create('resolution_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained('resolutions')->cascadeOnDelete();
            $table->foreignId('voter_employee_id')->constrained('employees');
            $table->foreignId('voter_user_id')->nullable()->constrained('users');

            // رأی: for, against, abstain
            $table->string('vote', 20);

            // وزن رأی (در voting_type=weighted)
            $table->decimal('weight', 6, 3)->default(1);

            // اگر تفویض شده (proxy voting)
            $table->foreignId('delegated_from_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()
                ->constrained('user_delegations')->nullOnDelete();

            $table->text('rationale')->nullable(); // توضیح اختیاری
            $table->ipAddress('voter_ip')->nullable();

            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(['resolution_id', 'voter_employee_id']);
            $table->index(['resolution_id', 'vote']);
        });

        // ذی‌ربطان مصوبه (مسئول اجرا، ناظر، ذی‌نفع)
        Schema::create('resolution_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained('resolutions')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->cascadeOnDelete();

            // نقش: executor (مجری), supervisor (ناظر), beneficiary (ذی‌نفع), observer
            $table->string('role', 30);
            $table->boolean('is_primary')->default(false); // مسئول اصلی

            $table->timestamps();

            $table->index(['resolution_id', 'role']);
            // یا employee یا org_unit — حداقل یکی
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolution_assignees');
        Schema::dropIfExists('resolution_votes');
        Schema::dropIfExists('resolutions');
    }
};
