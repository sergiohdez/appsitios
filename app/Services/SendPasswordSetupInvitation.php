<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class SendPasswordSetupInvitation
{
    public function send(User $user): bool
    {
        try {
            $token = Password::broker()->createToken($user);
            $user->notify(new SetPasswordInvitation($token));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Could not send account setup invitation.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
