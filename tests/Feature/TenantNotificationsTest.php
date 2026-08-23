<?php

use App\Core\Auth\Models\User;
use App\Core\Models\UserNotification;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->notificationDbPath = database_path('tenant_notifications_test.sqlite');
    if (! file_exists($this->notificationDbPath)) {
        touch($this->notificationDbPath);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Notificaciones',
        'slug' => 'clinica-notificaciones',
        'database_name' => $this->notificationDbPath,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'notificaciones.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    app(TenantContext::class)->execute($this->tenant, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $this->user = User::create([
            'name' => 'Usuario Notificado',
            'email' => 'notificado@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->otherUser = User::create([
            'name' => 'Otro Usuario',
            'email' => 'otro@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->criticalNotification = UserNotification::create([
            'user_id' => $this->user->id,
            'type' => 'inventory',
            'severity' => 'critical',
            'title' => 'Stock crítico',
            'message' => 'Material por debajo del mínimo.',
            'action_url' => '/inventory',
        ]);

        UserNotification::create([
            'user_id' => $this->user->id,
            'type' => 'appointment',
            'severity' => 'info',
            'title' => 'Agenda actualizada',
            'message' => 'Nueva cita confirmada.',
            'action_url' => '/appointments',
            'read_at' => now(),
        ]);

        $this->foreignNotification = UserNotification::create([
            'user_id' => $this->otherUser->id,
            'type' => 'system',
            'severity' => 'warning',
            'title' => 'Privada',
            'message' => 'No debe ser visible para otro usuario.',
        ]);
    });
});

test('[GATE NOT] notification center is user-scoped and exposes unread shared props', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->get('http://notificaciones.bsdental.test/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Notifications/Index')
            ->has('notificationPage.data', 2)
            ->where('notifications.unread_count', 1)
            ->where('notifications.items.0.id', $this->criticalNotification->id)
            ->where('notificationPage.data.0.title', 'Stock crítico'));
});

test('[GATE NOT] user can mark own notifications but cannot mutate another inbox', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->patch("http://notificaciones.bsdental.test/notifications/{$this->foreignNotification->id}/read")
        ->assertNotFound();

    $this->patch("http://notificaciones.bsdental.test/notifications/{$this->criticalNotification->id}/read")
        ->assertRedirect();

    $context->makeCurrent($this->tenant);
    expect($this->criticalNotification->fresh()->read_at)->not->toBeNull()
        ->and($this->foreignNotification->fresh()->read_at)->toBeNull();

    UserNotification::create([
        'user_id' => $this->user->id,
        'type' => 'follow_up',
        'severity' => 'warning',
        'title' => 'Seguimiento',
        'message' => 'Tarea pendiente.',
    ]);

    $this->patch('http://notificaciones.bsdental.test/notifications/read-all')
        ->assertRedirect();

    $context->makeCurrent($this->tenant);
    expect(UserNotification::where('user_id', $this->user->id)->whereNull('read_at')->count())->toBe(0)
        ->and(UserNotification::where('user_id', $this->otherUser->id)->whereNull('read_at')->count())->toBe(1);
});
