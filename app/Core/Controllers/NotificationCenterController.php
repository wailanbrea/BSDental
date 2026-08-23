<?php

namespace App\Core\Controllers;

use App\Core\Models\UserNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (string) $request->user('web')->getAuthIdentifier();
        $status = $request->string('status')->toString();
        $severity = $request->string('severity')->toString();

        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->latest();

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if (in_array($severity, ['info', 'success', 'warning', 'critical'], true)) {
            $query->where('severity', $severity);
        }

        return Inertia::render('Clinic/Notifications/Index', [
            'notificationPage' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'status' => $status,
                'severity' => $severity,
            ],
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = UserNotification::query()
            ->where('user_id', (string) $request->user('web')->getAuthIdentifier())
            ->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        UserNotification::query()
            ->where('user_id', (string) $request->user('web')->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->back();
    }
}
