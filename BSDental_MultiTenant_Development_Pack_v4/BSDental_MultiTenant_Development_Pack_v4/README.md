# BSDental Multi-Tenant v4

Paquete depurado para construir BSDental como una plataforma odontológica SaaS multiempresa, rápida y segura.

## Empieza aquí

1. `00_MASTER_BSDENTAL_V4.md`
2. `PROJECT_STATUS.md`
3. `DECISIONS.md`
4. `21_PLAN_IMPLEMENTACION_TAREAS_CLARAS.md`

## Cuando programe una IA

Leer además:
- archivo del dominio actual;
- `23_REGLAS_INVARIANTES.md`.

## Stack

Ver:
`02_STACK_VERSIONES_2026_08.md`

## Investigación

Ver:
`24_RESEARCH_BASELINE_2026_08.md`

## Regla crítica

No implementar Pacientes hasta aprobar `GATE-TENANCY-01`.

## Estructura

```text
00 MASTER
01 Architecture
02 Stack
03 Tenancy
04 Security
05 Auth
06 Performance
07 DevOps
08 Provisioning
09 Redis/Queue/Storage
10 Data Model
11-18 Business Domains
19 Testing
20 Observability/DR
21 Implementation Tasks
22 AI Protocol
23 Invariants
24 Research
25 UI Prompt
26 Platform Admin
27 Releases
```
