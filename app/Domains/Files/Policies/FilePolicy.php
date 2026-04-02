<?php

declare(strict_types=1);

namespace App\Domains\Files\Policies;

use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('file.view');
    }

    public function view(User $user, File $file): bool
    {
        return $file->canBeAccessedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('file.upload');
    }

    public function download(User $user, File $file): bool
    {
        if (!$file->canBeAccessedBy($user)) return false;
        if ($file->isExpired()) return false;
        if ($file->virus_scan_status === 'infected') return false;

        // مجوز download جداگانه
        $hasPermission = $file->permissions()
            ->where('user_id', $user->id)
            ->where('can_download', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        return $hasPermission
            || $file->uploaded_by_user_id === $user->id
            || $user->hasPermissionTo('file.view_all');
    }

    public function delete(User $user, File $file): bool
    {
        if ($user->hasPermissionTo('file.delete_any')) return true;
        return $file->uploaded_by_user_id === $user->id;
    }
}
