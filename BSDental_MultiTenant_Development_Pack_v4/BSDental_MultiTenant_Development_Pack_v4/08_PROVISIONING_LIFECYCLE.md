# 08 — Provisioning y Lifecycle Tenant

## Estados

```text
provisioning
trial
active
past_due
suspended
cancelled
archived
deletion_pending
deleted
provisioning_failed
```

## Pipeline CreateTenant

1. ValidateTenantInput
2. CreateLandlordTenant
3. ReserveSlug
4. ReserveDomain
5. ProvisionDatabase
6. ProvisionDatabaseUser
7. TestDatabaseConnection
8. RunTenantMigrations
9. SeedTenantCore
10. CreateTenantOwner
11. ApplyPlanEntitlements
12. InitializeStoragePrefix
13. InitializeDefaultSettings
14. RunTenantHealthCheck
15. ActivateTenant

Cada step:
- tiene status;
- idempotency key;
- error;
- attempts.

## Failure

Si step falla:
- no activar;
- no ejecutar steps siguientes;
- permitir retry seguro;
- cleanup solo de recursos creados por run actual si es seguro.

## Domain

Default:
```text
{slug}.bsdental.app
```

Custom:
- add;
- DNS verify;
- TLS;
- mark verified;
- set primary.

## Suspension

No borra datos.

Política:
- bloquea writes normales;
- detiene marketing;
- deja Platform health;
- acceso owner/export según contrato.

## Deletion

Nunca inmediata.
Requiere:
- retención;
- export/backup;
- aprobación;
- audit;
- borrado storage;
- DB;
- credentials;
- domain mappings.
