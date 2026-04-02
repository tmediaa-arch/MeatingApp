<?php

declare(strict_types=1);

namespace App\Domains\Files\Actions;

use App\Domains\Files\Exceptions\FileException;
use App\Domains\Files\Models\File;
use App\Domains\Files\Models\FileAccessLog;
use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * LogFileAccessAction — ثبت دسترسی به فایل در access log (append-only).
 *
 * این Action در نقاط ورودی view/download فراخوانی می‌شود تا
 * یک trail قابل ممیزی از دسترسی‌ها داشته باشیم.
 */
class LogFileAccessAction
{
    /**
     * @param  string  $action  view|download|preview|share|delete_attempt
     */
    public function execute(
        File $file,
        User $user,
        string $action = 'view',
        ?Request $request = null,
    ): FileAccessLog {
        // اعتبار سنجی دسترسی پیش از log کردن
        if (!$file->canBeAccessedBy($user) && $action !== 'delete_attempt') {
            throw FileException::notAccessible();
        }

        $request ??= request();

        return FileAccessLog::create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'accessed_at' => now(),
        ]);
    }
}
