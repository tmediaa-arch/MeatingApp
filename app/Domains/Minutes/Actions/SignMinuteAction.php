<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Minutes\Models\MinuteSignature;
use Illuminate\Support\Facades\DB;

/**
 * ثبت امضای دیجیتال روی صورتجلسه.
 *
 * منطق امضا:
 * 1. صورتجلسه باید در وضعیت Review باشد
 * 2. کاربر باید secretary یا chairperson (یا other_signer) جلسه باشد
 * 3. content_hash لحظه امضا ذخیره می‌شود
 * 4. اگر هر دو امضا کامل شد، status به Signed منتقل می‌شود
 *
 * در این فاز signature_method = 'simple' است.
 * در آینده با PKI/OTP/TBT اضافه می‌شود.
 */
class SignMinuteAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionMinuteStatusAction $transitionAction,
    ) {
    }

    /**
     * @param string $signerRole 'secretary' | 'chairperson' | 'other'
     */
    public function execute(
        Minute $minute,
        User $signer,
        string $signerRole = 'secretary',
        string $signatureMethod = 'simple',
        ?string $signatureData = null,
        array $metadata = [],
    ): MinuteSignature {
        // اعتبارسنجی وضعیت
        if (!in_array($minute->status, [
            MinuteStatus::Review,
            MinuteStatus::Signed,
        ], true)) {
            throw MinuteException::cannotSignInStatus($minute->status);
        }

        // اعتبارسنجی مجوز امضا
        $this->validateSignerRole($minute, $signer, $signerRole);

        // اگر قبلاً امضا شده
        if ($this->hasAlreadySigned($minute, $signer, $signerRole)) {
            throw MinuteException::alreadySigned($signerRole);
        }

        return DB::transaction(function () use (
            $minute, $signer, $signerRole, $signatureMethod, $signatureData, $metadata
        ) {
            // hash محتوای فعلی
            $contentHash = $minute->getContentHash();

            // ثبت امضا (append-only)
            $signature = MinuteSignature::create([
                'minute_id' => $minute->id,
                'signer_user_id' => $signer->id,
                'signer_employee_id' => $signer->employee_id,
                'signer_role' => $signerRole,
                'content_hash' => $contentHash,
                'signature_method' => $signatureMethod,
                'signature_data' => $signatureData,
                'signer_ip' => request()?->ip(),
                'signer_user_agent' => request()?->userAgent(),
                'metadata' => $metadata,
                'signed_at' => now(),
            ]);

            // به‌روزرسانی فیلدهای summary روی minute
            if ($signerRole === 'secretary') {
                $minute->update(['secretary_signed_at' => now()]);
            } elseif ($signerRole === 'chairperson') {
                $minute->update(['chairperson_signed_at' => now()]);
            }

            // اگر هر دو امضا کامل، transition به Signed
            $minute->refresh();
            if ($minute->isFullySigned() && $minute->status === MinuteStatus::Review) {
                $this->transitionAction->execute(
                    minute: $minute,
                    newStatus: MinuteStatus::Signed,
                    reason: 'تمام امضاهای الزامی ثبت شد',
                );
            }

            // audit
            $this->auditService->log(
                event: 'minute_signed',
                auditable: $minute,
                description: sprintf(
                    "صورتجلسه '%s' توسط '%s' به‌عنوان '%s' امضا شد",
                    $minute->minute_number,
                    $signer->name,
                    $signerRole,
                ),
                context: [
                    'signer_role' => $signerRole,
                    'method' => $signatureMethod,
                    'content_hash' => $contentHash,
                ],
                severity: 'notice',
            );

            return $signature;
        });
    }

    private function validateSignerRole(Minute $minute, User $signer, string $signerRole): void
    {
        if ($signer->hasRole('super-admin')) return;

        match ($signerRole) {
            'secretary' => $this->ensureSecretary($minute, $signer),
            'chairperson' => $this->ensureChairperson($minute, $signer),
            'other' => $this->ensureOtherSignerPermission($signer),
            default => throw new \InvalidArgumentException("Unknown signer role: {$signerRole}"),
        };
    }

    private function ensureSecretary(Minute $minute, User $signer): void
    {
        if ($minute->secretary_employee_id !== $signer->employee_id) {
            throw MinuteException::notAuthorizedToSignAsRole('دبیر');
        }
    }

    private function ensureChairperson(Minute $minute, User $signer): void
    {
        if ($minute->chairperson_employee_id !== $signer->employee_id) {
            throw MinuteException::notAuthorizedToSignAsRole('رئیس');
        }
    }

    private function ensureOtherSignerPermission(User $signer): void
    {
        if (!$signer->hasPermissionTo('minute.sign-other')) {
            throw MinuteException::notAuthorizedToSignAsRole('امضاکننده اضافه');
        }
    }

    private function hasAlreadySigned(Minute $minute, User $signer, string $role): bool
    {
        return $minute->signatures()
            ->where('signer_user_id', $signer->id)
            ->where('signer_role', $role)
            ->exists();
    }
}
