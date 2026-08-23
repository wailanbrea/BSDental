<?php

namespace App\Http\Middleware;

use App\Core\Models\UserNotification;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () use ($request): ?array {
                    if ($request->routeIs('platform.*')) {
                        return Auth::guard('platform')->user()?->only(['id', 'name', 'email']);
                    }

                    if (! app(TenantContext::class)->check()) {
                        return null;
                    }

                    $user = Auth::guard('web')->user();
                    if ($user === null) {
                        return null;
                    }

                    return [
                        ...$user->only(['id', 'name', 'email']),
                        'roles' => $user->getRoleNames()->values(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                        'branch_ids' => $user->branches()->pluck('branches.id')->values(),
                    ];
                },
            ],
            'clinic' => function (): ?array {
                $context = app(TenantContext::class);
                if (! $context->check()) {
                    return null;
                }

                $profile = ClinicProfile::query()->first(['clinic_name', 'trade_name']);

                return [
                    'name' => $profile !== null
                        ? $profile->clinic_name
                        : $context->requireCurrent()->name,
                    'trade_name' => $profile !== null ? $profile->trade_name : null,
                ];
            },
            'notifications' => function (): array {
                if (! app(TenantContext::class)->check()) {
                    return ['items' => [], 'unread_count' => 0];
                }

                $userId = Auth::guard('web')->id();
                if (! is_string($userId)) {
                    return ['items' => [], 'unread_count' => 0];
                }

                $query = UserNotification::query()->where('user_id', $userId);

                return [
                    'items' => (clone $query)->latest()->limit(6)->get([
                        'id',
                        'type',
                        'severity',
                        'title',
                        'message',
                        'action_url',
                        'read_at',
                        'created_at',
                    ]),
                    'unread_count' => (clone $query)->whereNull('read_at')->count(),
                ];
            },
            'flash' => [
                'success' => fn (): mixed => $request->session()->get('success'),
                'error' => fn (): mixed => $request->session()->get('error'),
                'warning' => fn (): mixed => $request->session()->get('warning'),
                'info' => fn (): mixed => $request->session()->get('info'),
            ],
        ];
    }
}
