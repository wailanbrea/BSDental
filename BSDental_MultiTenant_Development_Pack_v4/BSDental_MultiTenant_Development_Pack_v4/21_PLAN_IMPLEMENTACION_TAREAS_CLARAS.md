# 21 — Plan de Implementación v4 — Tareas Claras

> Regla: ejecutar por ID y en orden.
> No iniciar una fase posterior si su Gate anterior está rojo.

---

# FASE 0 — REPOSITORIO Y TOOLCHAIN

## F0-001 — Crear proyecto Laravel 13
**Objetivo:** obtener un proyecto ejecutable vacío.
**Dependencias:** ninguna.
**Acciones exactas:**
1. Crear Laravel 13.
2. Fijar PHP `^8.5`.
3. Crear `.env.example`.
4. Configurar timezone app UTC.
5. Confirmar APP_DEBUG=false en ejemplo de producción.
**Aceptación:**
- `php artisan about` reporta Laravel 13/PHP 8.5.
- página inicial responde.
**Pruebas:**
- `php artisan test`.

## F0-002 — Fijar Node y frontend
**Objetivo:** crear toolchain frontend reproducible.
**Dependencias:** F0-001.
**Acciones exactas:**
1. Fijar Node 24 LTS en archivo de versión.
2. Instalar Vue 3.5.x.
3. Instalar TS 5.9.x.
4. Instalar Vite 8.1.x.
5. Instalar Tailwind 4.3.x.
6. Instalar Inertia 3.x.
7. Habilitar TypeScript strict.
**Aceptación:**
- `npm ci` reproducible.
- app Vue/Inertia renderiza.
**Pruebas:**
- typecheck;
- build.

## F0-003 — Configurar calidad
**Objetivo:** impedir código roto desde primer commit.
**Dependencias:** F0-001/F0-002.
**Acciones:**
1. Pint.
2. Pest 5.
3. Larastan 3.10.
4. ESLint.
5. frontend test runner.
6. scripts `quality`.
**Aceptación:** un comando local ejecuta checks base.

## F0-004 — Crear CI
**Objetivo:** ejecutar quality gate en PR.
**Acciones:**
- PHP install/cache;
- Composer audit;
- Pint;
- Larastan;
- Pest;
- npm ci;
- npm audit policy;
- lint;
- typecheck;
- build;
- Gitleaks;
- Trivy.
**Aceptación:** PR no mergea si check requerido falla.

### GATE F0
Todos los checks verdes.

---

# FASE 1 — LANDLORD Y TENANCY

## MT-001 — Crear conexión landlord
**Objetivo:** separar control plane.
**Acciones:**
1. `landlord` connection.
2. migrations path landlord.
3. `tenants` table.
4. `tenant_domains`.
5. tests.
**Aceptación:** landlord funciona sin tenant DB.

## MT-002 — Instalar Spatie Multitenancy 4.2
**Objetivo:** disponer switching probado.
**Acciones:**
1. Composer install versión 4.2.x.
2. publicar config.
3. crear Tenant model adapter.
4. encapsular paquete en `Platform/Tenancy`.
**Aceptación:** ningún módulo de dominio importa Spatie directamente.

## MT-003 — Crear TenantContext
**Objetivo:** API única de contexto.
**Acciones:** implementar `current`, `requireCurrent`, `execute`, `forget`.
**Aceptación:** unit tests de set/reset/exceptions.

## MT-004 — Resolver tenant por host
**Objetivo:** host determina tenant.
**Acciones:**
1. normalize host;
2. query verified domain;
3. reject unknown;
4. activate tenant.
**Aceptación:** host A→A; B→B; unknown→404.

## MT-005 — Crear tenant DB de prueba A/B
**Objetivo:** comprobar aislamiento físico.
**Acciones:**
1. DB_A;
2. DB_B;
3. tenant migrations;
4. insertar patient id 1 distinto en ambas.
**Aceptación:** mismo código obtiene paciente correcto según host.

## MT-006 — Implementar cleanup de contexto
**Objetivo:** impedir state leak.
**Acciones:** middleware finally + tests secuenciales A/B/A.
**Aceptación:** al terminar request no queda tenant current.

## MT-007 — Namespace Redis
**Objetivo:** aislar cache/locks.
**Acciones:** TenantCachePrefixer + tests same logical key A/B.
**Aceptación:** valores no colisionan.

## MT-008 — Namespace Storage
**Objetivo:** aislar archivos.
**Acciones:** TenantStoragePath + private disk + tests same filename.
**Aceptación:** A URL/file no abre desde B.

## MT-009 — Queue tenant-aware
**Objetivo:** jobs seguros.
**Acciones:** TenantAwareJob middleware, `finally` cleanup, retry tests.
**Aceptación:** retry mantiene tenant original.

## MT-010 — Central domain isolation
**Objetivo:** admin central no usar tenant DB accidentalmente.
**Acciones:** central middleware/guard y test.
**Aceptación:** model tenant sin context lanza excepción controlada.

### GATE-TENANCY-01
Aprobar tests:
- request A/B;
- cache;
- storage;
- queue/retry;
- central domain;
- no tenant context fallback.

No continuar a pacientes si falla.

---

# FASE 2 — AUTH Y PLATFORM

## PL-001 — Auth Platform
Crear guard/provider separado, login y 2FA obligatorio.

## PL-002 — Auth Tenant
Fortify tenant-local, Argon2id, verification, reset, 2FA.

## PL-003 — RBAC tenant
Instalar Permission 8.3; seeds roles/perms; policies.

## PL-004 — Entitlements
Landlord plan/modules + middleware server-side.

## PL-005 — Feature flags
Pennant para rollouts técnicos; no confundir con entitlement.

## PL-006 — Platform Admin tenants list
Lista/status/domain/plan/schema/health.

## PL-007 — Provisioning pipeline
Implementar steps idempotentes definidos en documento 08.

## PL-008 — Suspend/resume
Implementar estados y middleware.

### GATE PL
Crear Tenant C desde Platform UI y poder autenticar owner sin pasos manuales de DB.

---

# FASE 3 — SEGURIDAD HARDENING BASE

## SEC-001 — Security headers
CSP nonce, HSTS, nosniff, referrer, permissions.

## SEC-002 — Session policy
Secure/HttpOnly/SameSite, regeneration, timeout, revoke.

## SEC-003 — Rate limits
Login/reset/public booking/API/webhooks/export.

## SEC-004 — Private upload pipeline
MIME/magic/size/random key/private access.

## SEC-005 — Audit framework
Platform + tenant audit.

## SEC-006 — Secrets strategy
Documentar y configurar producción sin secretos en repo/image.

## SEC-007 — ASVS checklist
Crear matriz ASVS 5.0 aplicable y estado Pass/Fail/NA con justificación.

### GATE SEC
No Critical/High conocidas sin mitigación; cross-tenant suite verde.

---

# FASE 4 — RUNTIME Y PERFORMANCE FOUNDATION

## PERF-001 — Redis 8.8 + PhpRedis
Configurar cache/session/queue.

## PERF-002 — Horizon
Configurar queues y dashboard protegido.

## PERF-003 — Pulse
Instalar y proteger; slow queries/requests/jobs.

## PERF-004 — FrankenPHP
Crear production container y health route.

## PERF-005 — Octane
Instalar Octane + FrankenPHP.

## PERF-006 — OCTANE-TENANT-STATE suite
Ejecutar secuencia A/B/A bajo worker persistente y comprobar:
- TenantContext;
- DB;
- Permission cache;
- Redis prefix;
- locale/timezone;
- singleton state.

**Aceptación:** cero contaminación.

### GATE PERF-OCTANE-01
Solo si PERF-006 verde se activa Octane en producción.

---

# FASE 5 — CORE TENANT

## CORE-001 — Settings
Datos clínica, timezone, currency, branding.

## CORE-002 — Branches
CRUD + status + branch user scope.

## CORE-003 — Rooms
CRUD consultorios/sillones por branch.

## CORE-004 — Professionals
Profesionales, specialties, branches.

## CORE-005 — Files service
Metadata + authorization + signed downloads.

### GATE CORE
Owner configura clínica/sucursales/profesionales con RBAC y audit.

---

# FASE 6 — PATIENTS

## PAT-001 — Schema Patients
Migraciones, indexes, factories.

## PAT-002 — Create/Update
Form Requests + Action + Policy.

## PAT-003 — Search
Server-side nombre/código/teléfono/documento, paginado.

## PAT-004 — Duplicate candidates
Advertencia por coincidencias configuradas; no merge automático.

## PAT-005 — Profile 360
Shell de tabs con permisos.

## PAT-006 — Patient files
Private upload/download.

### GATE PAT
CRUD/search/profile/files + tenant isolation + policies verdes.

---

# FASE 7 — AGENDA

## APP-001 — Appointment types.
## APP-002 — Professional schedules.
## APP-003 — Schedule blocks.
## APP-004 — Appointment CRUD/state machine.
## APP-005 — Conflict detection.
## APP-006 — Day/week/month UI.
## APP-007 — Check-in/waiting/in-progress.
## APP-008 — Cancel/no-show/reschedule history.
## APP-009 — Reminder domain events.

### GATE APP
Recepción puede operar un día completo y dos requests concurrentes no crean doble reserva inválida.

---

# FASE 8 — CLINICAL

## CL-001 — History schema.
## CL-002 — Antecedents/allergies/medications.
## CL-003 — Encounter drafts.
## CL-004 — Finalize encounter.
## CL-005 — Amendment flow.
## CL-006 — Clinical files.
## CL-007 — Diagnoses/evolutions.

### GATE CL
Finalized record no se sobreescribe; amendment mantiene historia.

---

# FASE 9 — CONSENTS / ODONTOGRAM

## CON-001 — Consent templates/versioning.
## CON-002 — Consent signing/snapshot.
## ODO-001 — Tooth/surface model.
## ODO-002 — Condition/diagnosis entries.
## ODO-003 — Planned/approved/completed entries.
## ODO-004 — Interactive UI.
## ODO-005 — Historical view.

### GATE
Planificado y realizado son inequívocos y reconstruibles históricamente.

---

# FASE 10 — QUOTES / TREATMENTS

## QUO-001 — Procedure catalog/prices.
## QUO-002 — Treatment alternatives.
## QUO-003 — Quote draft/items.
## QUO-004 — Quote versions.
## QUO-005 — Present/approve/partial/reject.
## QUO-006 — Signature/PDF.
## TRT-001 — Generate treatment plan.
## TRT-002 — Phases/items.
## TRT-003 — Schedule treatment item.
## TRT-004 — CompleteProcedureAction idempotente.
## TRT-005 — Progress metrics.

### GATE
Quote→approval→plan→procedure sin duplicación.

---

# FASE 11 — INVENTORY / LAB

## INV-001 — Items/categories/units.
## INV-002 — Warehouses.
## INV-003 — Purchases/lots.
## INV-004 — StockMovement ledger.
## INV-005 — Transfers.
## INV-006 — Expiry/low-stock alerts.
## INV-007 — Procedure material rules.
## INV-008 — Procedure consumption idempotency.
## LAB-001 — Laboratories.
## LAB-002 — Orders/state machine.
## LAB-003 — Cost/payable/payment.

### GATE
Stock reconciliable; purchase != consumption; lab cost != payment.

---

# FASE 12 — BILLING / CASH / FINANCE

## BIL-001 — Charges.
## BIL-002 — Payments/splits.
## BIL-003 — Allocations.
## BIL-004 — Refunds.
## BIL-005 — Receivables/aging.
## CASH-001 — Registers/sessions.
## CASH-002 — Movements.
## CASH-003 — Close/reopen controls.
## COMP-001 — Compensation rules.
## COMP-002 — Accruals.
## COMP-003 — Settlements/payments.
## FIN-001 — Expenses.
## FIN-002 — Accounts payable.
## FIN-003 — Margin/profit/cash-flow queries.

### GATE-FIN
Ejecutar todas las invariantes financieras de `23_REGLAS_INVARIANTES.md`.

---

# FASE 13 — FOLLOWUP / WHATSAPP / CRM

## FUP-001 — Follow-up tasks.
## NOT-001 — Notification engine.
## WA-001 — WhatsApp provider contract.
## WA-002 — Template mapping.
## WA-003 — Reminder scheduler.
## WA-004 — Signed/idempotent webhook.
## CRM-001 — Stages.
## CRM-002 — Segments.
## MKT-001 — Campaigns/preferences.

### GATE
Reprogramar/cancelar cita nunca deja reminder obsoleto activo.

---

# FASE 14 — ANALYTICS

## ANA-001 — KPI registry con fórmulas.
## ANA-002 — Operational dashboard.
## ANA-003 — Finance dashboard.
## ANA-004 — Professional dashboard.
## ANA-005 — Inventory/lab dashboard.
## ANA-006 — Drill-down reconciliation.
## REP-001 — Queued exports.

### GATE
Cada total financiero reconcilia con registros fuente.

---

# FASE 15 — RELEASE 1.0

## REL-001 — Full browser regression.
## REL-002 — ASVS verification.
## REL-003 — Tenant isolation security review.
## REL-004 — Load/performance test.
## REL-005 — Backup restore drill.
## REL-006 — Tenant migration canary drill.
## REL-007 — Production runbook.
## REL-008 — Signed/tagged release.
## REL-009 — Deploy staging.
## REL-010 — Production go/no-go checklist.

### GATE RELEASE
No release si cualquier gate obligatorio está rojo.
