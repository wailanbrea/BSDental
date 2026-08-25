<?php

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantFinder\DomainTenantFinder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;

return [
    /*
     * This class is responsible for determining which tenant should be current
     * for the given request.
     */
    'tenant_finder' => DomainTenantFinder::class,

    /*
     * These fields are used by tenant:artisan command to match one or more tenant.
     */
    'tenant_artisan_search_fields' => [
        'id',
        'slug',
    ],

    /*
     * These tasks will be performed when switching tenants.
     */
    'switch_tenant_tasks' => [
        SwitchTenantDatabaseTask::class,
    ],

    /*
     * This class is the model used for storing configuration on tenants.
     */
    'tenant_model' => Tenant::class,

    /*
     * If there is a current tenant when dispatching a job, the id of the current tenant
     * will be automatically set on the job.
     */
    'queues_are_tenant_aware_by_default' => true,

    /*
     * The connection name to reach the tenant database.
     */
    'tenant_database_connection_name' => 'tenant',

    /*
     * The connection name to reach the landlord database.
     */
    'landlord_database_connection_name' => 'landlord',

    /*
     * Primary domain assigned to the seeded demo tenant.
     */
    'demo_tenant_domain' => env('DEMO_TENANT_DOMAIN', 'demo.localhost'),

    /*
     * This key will be used to associate the current tenant in the context
     */
    'current_tenant_context_key' => 'tenantId',

    /*
     * This key will be used to bind the current tenant in the container.
     */
    'current_tenant_container_key' => 'currentTenant',

    /*
     * Set it to true if you like to cache the tenant(s) routes
     */
    'shared_routes_cache' => false,

    /*
     * You can customize some of the behavior of this package by using your own custom action.
     */
    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    /*
     * You can customize the way in which the package resolves the queueable to a job.
     */
    'queueable_to_job' => [
        CallQueuedListener::class => 'class',
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        BroadcastEvent::class => 'event',
        CallQueuedClosure::class => 'closure',
    ],

    /*
     * Jobs that should not be tenant aware.
     */
    'not_tenant_aware_jobs' => [
        //
    ],

    /*
     * Jobs that should be tenant aware even if queues_are_tenant_aware_by_default is false.
     */
    'tenant_aware_jobs' => [
        //
    ],
];
