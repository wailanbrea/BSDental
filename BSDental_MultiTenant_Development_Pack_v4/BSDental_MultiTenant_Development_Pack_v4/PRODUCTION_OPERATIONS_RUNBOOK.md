# BSDental v4 — Production Operations Runbook

## 1. Arquitectura de Despliegue en Producción

- **Runtime**: FrankenPHP / PHP 8.5+ con Laravel Octane (Worker mode).
- **Aislamiento**: Database-per-tenant (`mysql` en prod, `sqlite` en dev/test local).
- **Procesamiento Asíncrono**: Laravel Horizon gestionando colas Redis `high`, `default`, `low`.
- **Caché y Bloqueos**: Redis 8.8+ con aislamiento por prefijos `bsdental:{tenant_uuid}:*`.
- **Storage Privado**: MinIO / S3 con rutas físicas aisladas `tenants/{tenant_uuid}/*`.

---

## 2. Protocolo de Despliegue y Migraciones Canarias

1. **Despliegue de Landlord**:
   ```bash
   php artisan migrate --database=landlord --path=database/migrations/landlord --force
   ```
2. **Health Check y Migración Canaria**:
   ```bash
   # Verificar salud antes del rollout
   php artisan tenants:migrate-canary
   ```
3. **Migración en Lote de Tenants**:
   ```bash
   php artisan tenants:artisan "migrate --database=tenant --path=database/migrations/tenant --force"
   ```
4. **Recarga de Workers de Octane y Horizon**:
   ```bash
   php artisan octane:reload
   php artisan horizon:terminate
   ```

---

## 3. Política de Respaldo y Recuperación ante Desastres (DR)

- **Landlord DB**: Respaldo diario cifrado de base central.
- **Tenant DBs**: Respaldo individual por clínica antes de cada migración y diario a medianoche.
- **Archivos Clínicos**: Replicación continua multi-región de S3 bucket con versionado inmutable.
- **Comando de Respaldo bajo Demanda**:
  - Disponible desde el panel de Platform Admin (`/platform/operations`) o vía `PlatformOperationsService::triggerTenantBackup()`.
- **Prueba de Restauración Periódica**:
  1. Descargar respaldo del tenant a ambiente de staging aislado.
  2. Verificar integridad de esquemas con `tenants:migrate-canary --tenant={slug}`.
  3. Ejecutar suite de smoke tests.
  4. Destruir entorno temporal de prueba de forma segura.

---

## 4. Respuesta a Incidentes de Seguridad

- **Compromiso de Credenciales**: Invalidar sesiones inmediatamente y rotar secretos en el gestor de secretos de la nube.
- **Suspensión de Tenant Comprometido**:
  - Ejecutar `SuspendTenantAction` desde `/platform/tenants/{id}`.
  - La suspensión es inmediata, purga la caché de Redis y rechaza cualquier solicitud HTTP con código `403 Tenant Suspended`.
- **Auditoría Forense**:
  - Consultar `landlord_audit_logs` para operaciones globales y `tenant_audit_logs` para eventos locales.