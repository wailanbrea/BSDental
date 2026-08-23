# 01 — Producto y Arquitectura

## Producto

BSDental no es solo CRM.

Es un **Dental Practice Management Platform + Clinical ERP + CRM**.

Dominios:
- operación;
- expediente clínico;
- producción;
- administración;
- finanzas;
- supply chain;
- relación con paciente;
- business intelligence.

## Flujo

```text
Lead
→ Paciente
→ Cita
→ Historia
→ Diagnóstico/Odontograma
→ Alternativas
→ Presupuesto
→ Aprobación/Firma
→ Plan de tratamiento
→ Sesiones
→ Procedimientos
→ Inventario/Laboratorio
→ Cargos
→ Pagos
→ Caja/Comisiones/Finanzas
→ Seguimiento
→ Retención
→ Analytics
```

## Multiempresa

Tenant = una entidad comercial odontológica.
Branch = sucursal dentro del tenant.

Nunca modelar sucursal como tenant si pertenece a la misma empresa.

## Single codebase

```text
main
→ release
→ production
```

No ramas por cliente.

## Modern monolith

Laravel + Inertia permiten mantener:
- lógica de dominio server-side;
- UI SPA-like;
- menos duplicación API;
- menor superficie de ataque que dos aplicaciones separadas;
- API futura sin reescribir domain actions.

## Boundaries

Cada módulo expone Actions/Queries/Events.
Evitar controladores con reglas de negocio.

Ejemplo:

```text
CompleteProcedureAction
```

orquesta:
- validar estado;
- persistir ejecución;
- actualizar odontograma;
- emitir efectos;
- registrar auditoría.

## Cross-module side effects

Usar transacción para consistencia obligatoria.
Usar Events/Jobs para efectos secundarios reintentables.

No esconder una regla crítica detrás de un event listener silencioso.
