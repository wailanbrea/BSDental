# 02 — Stack y Versiones — Baseline 2026-08-22

> Este archivo debe revisarse antes de iniciar una nueva release mayor.
> Los patch versions finales se fijan en lockfiles.

| Capa | Elección v4 | Política |
|---|---|---|
| PHP | 8.5.9 | Stable security patch |
| Framework | Laravel 13.x | Latest supported major |
| Frontend | Vue 3.5.41 | Latest stable; no 3.6 RC |
| Bridge | Inertia.js 3.x | Latest stable major |
| Types | TypeScript 5.9.x | Strict |
| Bundler | Vite 8.1.x | Stable |
| CSS | Tailwind CSS 4.3.x | Stable |
| Node build/SSR | Node 24.19 LTS | LTS, no Current |
| DB | MySQL 8.4 LTS | Latest compatible LTS patch |
| Redis | Redis OSS 8.8.1+ security patch | GA |
| Redis client PHP | PhpRedis | Preferido por rendimiento |
| Multi-tenancy | spatie/laravel-multitenancy 4.2.x | Encapsulado |
| RBAC | spatie/laravel-permission 8.3.x | Tenant-local |
| Testing | Pest 5.x + PHPUnit 13 | Baseline |
| Browser testing | Pest Browser + Playwright | E2E |
| Static analysis | Larastan 3.10.x + PHPStan 2.2+ | Level alto |
| Queue dashboard | Laravel Horizon | Redis queues |
| Perf insight | Laravel Pulse | APM interno |
| Feature flags | Laravel Pennant | Rollout técnico |
| Runtime HTTP | FrankenPHP + Octane | Tras gate |
| OS Prod | Ubuntu Server 26.04 LTS | Linux LTS |
| Containers | Docker/OCI | Reproducible deploy |
| CI/CD | GitHub Actions | Required checks |

## Inertia 3

Inertia v3 sustituye v2 en v4.

Aprovechar:
- Vite plugin oficial;
- lazy page chunks por defecto;
- HTTP client integrado;
- optimistic updates con rollback solo donde sean seguras;
- layout props;
- improved exception handling;
- SSR simplificado.

No usar optimistic updates para:
- pagos;
- cierre de caja;
- historia clínica finalizada;
- inventario irreversible.

Sí puede evaluarse para:
- filtros;
- preferencias UI;
- acciones reversibles no críticas.

## SSR

No es obligatorio para el panel privado.
Activar SSR principalmente para:
- landing pública;
- páginas indexables públicas;
- portal público futuro.

El ERP autenticado prioriza velocidad/seguridad sobre SEO.

## Octane

Paquete:
```text
laravel/octane
```

Servidor:
```text
FrankenPHP
```

Activar tras pruebas de state reset multi-tenant.

## Horizon

Queues separadas:

```text
critical
notifications
default
reports
exports
marketing
maintenance
```

No poner campañas masivas en la misma cola que confirmaciones de pago o recordatorios urgentes.

## Reverb

No obligatorio en MVP.

Agregar solo si existe requerimiento real:
- agenda multiusuario live;
- notificaciones instantáneas;
- tablero de recepción live.

No añadir WebSocket por moda.

## Laravel AI SDK

No dependencia funcional del Core.

Puede añadirse en módulo AI futuro para:
- resumen clínico asistido;
- consultas administrativas;
- OCR/visión;
- clasificación.

Nunca permitir decisiones diagnósticas autónomas sin diseño específico y validación clínica/legal.
