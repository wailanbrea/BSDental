# 06 — Rendimiento y Escalabilidad

## Principio

Medir antes de optimizar, pero diseñar para no crear cuellos obvios.

## HTTP

Target:
```text
FrankenPHP + Laravel Octane
```

Gate:
`PERF-OCTANE-01`.

## Laravel optimization

Producción:
- config cache;
- route cache;
- view cache;
- event cache cuando aplique;
- OPcache;
- APP_DEBUG=false.

## DB

Reglas:
- seleccionar columnas necesarias;
- eager loading explícito;
- prohibir N+1 en tests/desarrollo;
- server pagination;
- indexes basados en filtros/sorts/joins;
- EXPLAIN para consultas pesadas;
- transacciones cortas;
- evitar locks innecesarios;
- no guardar blobs clínicos en MySQL.

## Query budgets

Cada pantalla crítica documenta:
- número de queries;
- p95;
- filas examinadas en consultas principales.

No fijar “10 queries” como dogma; corregir regresiones contra baseline medido.

## Inertia 3

- lazy page loading;
- optional/deferred props;
- prefetch en navegación predecible;
- partial reloads;
- once/shared props para catálogos apropiados;
- no enviar 5 MB de props.

## Frontend

- page-level code splitting;
- chart library solo en páginas analytics;
- FullCalendar solo en Agenda;
- lazy images;
- virtualización solo si dataset visual lo requiere;
- no mantener arrays de miles de pacientes client-side.

## Redis

Usar para:
- cache;
- sessions;
- queues;
- locks;
- rate limiting.

No usar Redis como fuente principal de datos clínicos.

## Cache policy

Cachear:
- tenant settings;
- module entitlements;
- permisos;
- catálogos;
- métricas agregadas.

Evitar cachear PHI completa salvo necesidad justificada.

Toda key:
```text
bsdental:{tenant_uuid}:...
```

## Jobs

Mover fuera del request:
- PDF;
- WhatsApp;
- email;
- export;
- import;
- campaign;
- thumbnail;
- analytics costoso;
- backup orchestration.

## Queue isolation

Separar critical de bulk.

Campaña de 100,000 mensajes no puede retrasar:
- pago;
- recordatorio de cita;
- cierre;
- provisioning.

## Performance budgets iniciales

Objetivos, no garantía contractual:

- interacción CRUD normal p95 backend <= 500 ms en carga nominal;
- dashboard inicial útil <= 1 s percibido con deferred sections;
- búsquedas frecuentes <= 500 ms p95;
- jobs críticos queue wait <= 10 s en carga nominal;
- Lighthouse landing: Performance >= 90 en condiciones de prueba definidas.

Todo budget se valida en staging con dataset representativo.
