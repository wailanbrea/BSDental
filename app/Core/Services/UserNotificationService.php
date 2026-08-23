<?php

namespace App\Core\Services;

use App\Core\Auth\Models\User;
use App\Core\Models\UserNotification;

class UserNotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(
        string $userId,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): UserNotification {
        return UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyActiveUsers(
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): int {
        $count = 0;

        User::query()->where('status', 'active')->pluck('id')->each(function (string $userId) use (
            &$count,
            $type,
            $severity,
            $title,
            $message,
            $actionUrl,
            $data
        ) {
            $this->notifyUser($userId, $type, $severity, $title, $message, $actionUrl, $data);
            $count++;
        });

        return $count;
    }
}
