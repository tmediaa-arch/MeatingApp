<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserInvitation;
use App\Domains\Notifications\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * ساخت و پذیرش دعوت‌نامه‌های ورود به سامانه.
 */
class UserInvitationService
{
    /**
     * ساخت دعوت‌نامه و ارسال لینک آن از طریق پیامک.
     *
     * الگوی کاوه‌نگار باید آدرس پایه را در متن خود داشته باشد و token همان
     * شناسهٔ دعوت است؛ مثال متن الگو:  example.com/invite/%token
     */
    public function createAndSend(string $mobile, ?string $firstName, ?string $lastName, ?User $invitedBy): UserInvitation
    {
        $invitation = UserInvitation::create([
            'token' => Str::random(48),
            'mobile' => $mobile,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'invited_by_user_id' => $invitedBy?->id,
            'expires_at' => now()->addDays((int) config('services.invitation.ttl_days', 7)),
        ]);

        SendSmsJob::dispatch(
            $mobile,
            $invitation->token,
            (string) config('services.kavenegar.invite_template'),
        );

        return $invitation;
    }

    /**
     * یافتن کاربرِ متناظر با دعوت — در صورت نبود، حساب جدید ساخته می‌شود.
     */
    public function resolveUser(UserInvitation $invitation): User
    {
        $user = User::query()->where('mobile', $invitation->mobile)->first();

        if ($user === null) {
            $user = $this->createInvitedUser($invitation);
        }

        if ($invitation->isPending()) {
            $invitation->update([
                'user_id' => $user->id,
                'accepted_at' => now(),
            ]);
        }

        return $user;
    }

    private function createInvitedUser(UserInvitation $invitation): User
    {
        $user = User::create([
            'username' => $invitation->mobile,
            'first_name' => $invitation->first_name ?: 'کاربر',
            'last_name' => $invitation->last_name ?: 'مهمان',
            'mobile' => $invitation->mobile,
            'password' => Hash::make(Str::random(40)),
            'status' => UserStatus::Active,
            'is_external' => true,
        ]);

        $role = (string) config('services.invitation.default_role', 'invitee');

        if ($role !== '' && Role::where('name', $role)->where('guard_name', 'web')->exists()) {
            $user->assignRole($role);
        } else {
            Log::warning("Invitation default role '{$role}' not found; invited user has no role.");
        }

        return $user;
    }
}
