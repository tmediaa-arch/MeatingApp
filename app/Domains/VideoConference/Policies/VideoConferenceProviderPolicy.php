<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\VideoConference\Models\VideoConferenceProvider;

class VideoConferenceProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vc_provider.view');
    }

    public function view(User $user, VideoConferenceProvider $provider): bool
    {
        return $user->can('vc_provider.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vc_provider.manage');
    }

    public function update(User $user, VideoConferenceProvider $provider): bool
    {
        return $user->can('vc_provider.manage');
    }

    public function delete(User $user, VideoConferenceProvider $provider): bool
    {
        return $user->can('vc_provider.manage') && $provider->rooms()->count() === 0;
    }

    public function checkHealth(User $user, VideoConferenceProvider $provider): bool
    {
        return $user->can('vc_provider.manage') || $user->can('vc_provider.health_check');
    }
}
