# 03 — Multi-Tenancy y Aislamiento

## Modelo

Database-per-tenant.

## Landlord connection

Connection name:
```text
landlord
```

## Tenant connection

Connection name:
```text
tenant
```

El nombre de la DB se resuelve en runtime desde TenantContext.

## TenantContext

Crear servicio propio:

```text
App\Platform\Tenancy\TenantContext
```

Responsabilidades:
- current();
- requireCurrent();
- makeCurrent(Tenant);
- forgetCurrent();
- execute(Tenant, Closure).

El dominio no importa directamente APIs de Spatie.

## Middleware order

Tenant routes:

1. ResolveTenantFromHost
2. EnsureTenantStatusAllowsAccess
3. MakeTenantCurrent
4. StartSession/Auth
5. EnsureModuleEntitlement
6. Authorization

## Host validation

- normalized hostname;
- exact match en landlord;
- dominio verificado;
- no wildcard arbitrary host;
- unknown host = 404.

## Tenant DB credentials

Preferencia de máxima seguridad:
- usuario MySQL distinto por tenant;
- grants limitados a su DB;
- secreto cifrado/secret manager;
- nunca loggear credenciales.

## Cross-tenant administration

No hacer queries ad-hoc uniendo DBs clínicas.

Para platform analytics:
- producir métricas agregadas;
- publicar sin PHI;
- persistir en landlord.

## Context cleanup

Request:
- middleware `finally`.

Jobs:
- middleware/job wrapper `finally`.

Octane:
- listeners/tasks de reset + tests.

## Tenant ID

El UUID tenant puede aparecer en:
- logs;
- job payload;
- storage prefix;
- cache prefix;
- audit context.

No es una autorización en sí mismo.

## Tests obligatorios

Crear Tenant A y Tenant B con:
- DB distintas;
- mismo patient id posible;
- mismo filename posible;
- mismo cache key lógico posible.

Comprobar aislamiento en todos los subsistemas.
