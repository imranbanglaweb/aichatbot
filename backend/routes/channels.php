<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are used
| to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('voice-call.{sessionId}', function (?User $user, string $sessionId) {
    // Allow access if user is authenticated
    // In production, you would check if the user is part of this call session
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
    
    // For testing without auth, return true
    return true;
});

// Channel for call notifications
Broadcast::channel('notifications.{userId}', function (?User $user, string $userId) {
    // Allow users to listen to their own notification channel
    return $user && (string)$user->id === $userId;
});
