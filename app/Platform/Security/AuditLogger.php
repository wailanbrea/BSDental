<?php

namespace App\Platform\Security;

use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Security\Models\LandlordAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Keys that must never be recorded in audit log metadata.
     *
     * @var list<string>
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'credit_card',
        'card_number',
        'cvv',
        'note_body',
        'clinical_note',
    ];

    public function __construct(
        protected TenantContext $tenantContext,
        protected ?Request $request = null
    ) {}

    /**
     * Record an audit log entry in the Landlord platform plane.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logPlatform(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $metadata = [],
        ?Tenant $tenant = null
    ): LandlordAuditLog {
        /** @var PlatformUser|null $user */
        $user = Auth::guard('platform')->user();

        return LandlordAuditLog::create([
            'platform_user_id' => $user?->id,
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'metadata' => $this->sanitizeMetadata($metadata),
            'created_at' => now(),
        ]);
    }

    /**
     * Record an audit log entry in the current Tenant database plane.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logTenant(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $metadata = []
    ): TenantAuditLog {
        $user = Auth::guard('web')->user();

        return TenantAuditLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'metadata' => $this->sanitizeMetadata($metadata),
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively sanitize metadata to remove sensitive information.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeMetadata(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
