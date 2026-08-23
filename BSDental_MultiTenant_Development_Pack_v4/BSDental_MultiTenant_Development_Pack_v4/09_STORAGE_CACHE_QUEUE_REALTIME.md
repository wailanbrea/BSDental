# 09 — Storage, Cache, Queue y Tiempo Real

## Storage

Clinical files:
- object storage privado;
- SSE;
- tenant prefix;
- signed URL corta;
- auth antes de emitir URL;
- metadata tenant DB.

## Redis

Preferir PhpRedis.

Connections separadas cuando convenga:
- cache;
- session;
- queue;
- horizon.

## Queue taxonomy

```text
critical
notifications
default
reports
exports
marketing
maintenance
```

## Job middleware

Crear TenantAwareJob middleware propio además de capacidades del paquete.

Debe:
- capture original context;
- resolve tenant;
- make current;
- add log context;
- execute;
- cleanup in finally.

## Idempotency

Obligatoria en:
- PaymentConfirmed;
- ProcedureCompleted;
- webhook;
- inventory consumption;
- compensation accrual;
- reminder send;
- provisioning;
- imports.

## Scheduler

Un scheduler central.

Patrón:
```text
Schedule
→ active tenants batch
→ tenant-specific jobs
```

No proceso gigante que recorra todos los datos de todas las clínicas.

## Realtime

No añadir Reverb hasta requerimiento aprobado.

Cuando se agregue:
- channels tenant-scoped;
- private/presence auth;
- tenant in channel authorization;
- no PHI en nombre de channel.
