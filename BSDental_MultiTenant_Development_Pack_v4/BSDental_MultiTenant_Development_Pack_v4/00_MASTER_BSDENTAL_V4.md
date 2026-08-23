# BSDental v4 — MASTER DE DESARROLLO

> Este archivo es la fuente principal de verdad del proyecto.
> Si otro documento contradice este MASTER, prevalece este archivo.
> Ninguna IA o desarrollador puede cambiar una decisión estructural sin registrar un ADR en `DECISIONS.md`.

---

# 1. Definición

**BSDental** es una plataforma odontológica SaaS multi-tenant, multiempresa, multisucursal y modular.

## Modelo operativo

```text
UNA SOLA APLICACIÓN
UNA SOLA BASE DE CÓDIGO
UN SOLO PIPELINE DE RELEASE
UNA SOLA VERSIÓN ACTIVA
            │
            ▼
      LANDLORD DATABASE
            │
   ┌────────┼─────────┐
   ▼        ▼         ▼
Tenant A  Tenant B  Tenant C
  DB_A      DB_B       DB_C
```

Cada tenant representa una empresa/organización odontológica.
Cada tenant puede tener múltiples sucursales.
Los datos clínicos y operativos de tenants distintos nunca comparten tablas.

# 2. Objetivos técnicos

BSDental debe ser:

- seguro por diseño;
- rápido en navegación y operaciones frecuentes;
- modular;
- observable;
- testeable;
- actualizable sin forks por cliente;
- automatizable para agentes IA;
- recuperable ante fallos;
- preparado para crecimiento horizontal;
- consistente financieramente;
- auditable clínicamente;
- accesible y usable.

# 3. Stack de producción fijado

Consultar `02_STACK_VERSIONES_2026_08.md`.

Base aprobada:

```text
PHP                       8.5.9
Laravel                   13.x
Vue                       3.5.41
TypeScript                5.9.x
Inertia                   3.x
Vite                      8.1.x
Tailwind CSS              4.3.x
Node.js                   24.19 LTS
MySQL                     8.4 LTS, último patch compatible
Redis Open Source         8.8.1 o último 8.8.x de seguridad
Spatie Multitenancy       4.2.x
Spatie Permission         8.3.x
Pest                      5.x
PHPUnit                    13.x
Larastan                  3.10.x
Playwright                versión estable fijada en lockfile
```

No utilizar prereleases en producción.
Por tanto:
- PHP 8.6 Beta: NO.
- Vue 3.6 RC: NO.
- Node 26 Current: NO como runtime de producción.

# 4. Arquitectura

```text
Platform/
├── Tenancy
├── Provisioning
├── Domains
├── Plans
├── Entitlements
├── FeatureFlags
├── Support
├── Health
└── ReleaseManagement

Core/
├── Auth
├── Users
├── Branches
├── Professionals
├── RBAC
├── Audit
├── Files
├── Notifications
└── Settings

Modules/
├── Patients
├── Appointments
├── Clinical
├── Consents
├── Odontogram
├── Quotes
├── Treatments
├── Inventory
├── Laboratory
├── Billing
├── CashManagement
├── ProfessionalCompensation
├── Finance
├── FollowUp
├── CRM
├── Marketing
├── Analytics
├── Reports
└── Integrations
```

# 5. Tenancy

Arquitectura obligatoria: **database-per-tenant**.

## Landlord DB contiene
- tenants;
- dominios;
- planes;
- suscripciones/estado comercial;
- entitlements;
- feature flags de rollout;
- provisioning;
- estado de schema;
- health;
- usuarios Platform;
- auditoría Platform.

## Tenant DB contiene
- usuarios de la clínica;
- pacientes;
- historia clínica;
- odontograma;
- documentos;
- agenda;
- tratamientos;
- inventario;
- laboratorio;
- billing;
- caja;
- finanzas;
- comisiones;
- CRM;
- marketing;
- auditoría local.

# 6. Frontera de seguridad

```text
TENANT A DATA != TENANT B DATA
```

Nunca confiar únicamente en:
- `tenant_id`;
- filtro de frontend;
- hidden field;
- global scope.

La barrera primaria es la conexión a una DB distinta.

# 7. Resolución de tenant

El tenant se determina por host validado:

```text
clinica-a.bsdental.app
sistema.cliente.com
```

La plataforma central usa:

```text
admin.bsdental.app
```

Nunca aceptar un `tenant_id` enviado por navegador como autoridad para seleccionar la DB.

# 8. Modelo de releases

Todos los tenants ejecutan la misma versión de código.

Variaciones permitidas:
- configuración;
- módulos;
- permisos;
- feature flags;
- adapters de integración.

Prohibido:
- branch permanente por cliente;
- fork por cliente;
- parche escondido por nombre de clínica.

# 9. Seguridad mínima

Objetivo de verificación:
- OWASP ASVS 5.0 Level 2 como baseline;
- controles seleccionados de Level 3 para tenancy, autenticación, administración, criptografía y soporte.

Obligatorio:
- TLS;
- Argon2id;
- 2FA;
- RBAC server-side;
- CSP;
- HSTS;
- CSRF;
- rate limiting;
- auditoría;
- uploads privados y validados;
- secretos fuera del repositorio;
- dependencias auditadas;
- CI con SAST/dependency/secret/container scanning;
- backups cifrados;
- restauración probada;
- tenant isolation tests.

# 10. Rendimiento

Objetivo: rapidez basada en medición, no optimización especulativa.

Reglas:
- evitar N+1;
- paginación server-side;
- indexes basados en consultas reales;
- Inertia 3 con lazy page chunks, deferred/optional props y prefetch selectivo;
- queries de dashboards agregadas;
- jobs pesados fuera de request;
- PhpRedis;
- Horizon;
- cache tenant-aware;
- Octane + FrankenPHP tras pasar gate de seguridad de estado persistente;
- Vite code splitting;
- no enviar datasets masivos al browser.

# 11. Runtime HTTP

Target de producción:

```text
FrankenPHP
+
Laravel Octane
```

Octane se activa únicamente después de aprobar la suite `OCTANE-TENANT-STATE`.

Motivo: Octane mantiene Laravel en memoria; cualquier singleton/contexto tenant mal diseñado puede filtrar estado entre requests.

No se libera a producción con Octane hasta demostrar que:
- TenantContext se reinicia;
- permisos se reinician;
- DB connection se reinicia;
- cache context se reinicia;
- request scoped state no persiste.

# 12. Invariantes clínicas

```text
DIAGNÓSTICO
!= PLANIFICADO
!= APROBADO
!= PROGRAMADO
!= REALIZADO
```

Una cita no prueba que un tratamiento fue realizado.
Un presupuesto no crea producción.
Una aprobación no crea un procedimiento realizado.

# 13. Invariantes financieras

```text
PRODUCCIÓN
!= CARGO
!= COBRADO
!= DINERO ATRIBUIBLE A CLÍNICA
!= UTILIDAD
!= FLUJO DE CAJA
```

Además:

```text
COMPRA DE MATERIAL != CONSUMO
COSTO LABORATORIO != PAGO LABORATORIO
COMISIÓN DEVENGADA != COMISIÓN PAGADA
PRESUPUESTO != CUENTA POR COBRAR
```

# 14. Files

Storage privado.

Path:

```text
tenants/{tenant_uuid}/...
```

Nunca:
```text
public/patients/...
```

# 15. Cache / locks

Toda key tenant:

```text
bsdental:{tenant_uuid}:...
```

# 16. Jobs

Todo job tenant:
1. recibe tenant UUID;
2. resuelve tenant;
3. activa tenant;
4. ejecuta;
5. limpia contexto en `finally`;
6. es idempotente cuando puede reintentarse.

# 17. Calidad

Ninguna tarea se marca terminada sin:
- tests;
- análisis estático;
- formatter/linter;
- typecheck;
- build;
- seguridad relevante;
- documentación;
- aceptación definida.

# 18. Protocolo IA

Antes de trabajar:
1. leer este MASTER;
2. leer `PROJECT_STATUS.md`;
3. leer `DECISIONS.md`;
4. leer el documento del dominio actual;
5. leer `23_REGLAS_INVARIANTES.md`;
6. inspeccionar código real;
7. ejecutar tests existentes;
8. implementar una tarea con ID;
9. validar criterios;
10. actualizar status/changelog.

# 19. Regla de tareas

Una tarea válida debe contener:

```text
ID
Objetivo
Dependencias
Acciones exactas
Archivos/áreas esperadas
Criterios de aceptación
Pruebas/comandos
Resultado esperado
```

Tareas como:
- “mejorar seguridad”;
- “hacer multi-tenancy”;
- “optimizar DB”;
- “crear pacientes”;

son inválidas si no se descomponen.

# 20. Gate fundamental

No comenzar el módulo Patients hasta completar el gate:

```text
GATE-TENANCY-01
```

que demuestra mediante tests que:
- A no ve B;
- B no ve A;
- jobs no filtran contexto;
- cache no cruza;
- storage no cruza;
- Octane no retiene tenant;
- central domain no accede accidentalmente a tenant DB.

# 21. Resultado esperado

BSDental debe poder servir 1, 10, 100 o más clínicas desde una sola aplicación, manteniendo aislamiento, claridad funcional, alta seguridad, velocidad y un camino de escalado sin reescribir el producto.
