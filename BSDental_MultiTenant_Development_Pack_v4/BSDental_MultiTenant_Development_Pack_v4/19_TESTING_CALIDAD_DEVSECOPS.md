# 19 — Testing, Calidad y DevSecOps

## Test stack

- Pest 5;
- PHPUnit 13;
- Pest Browser + Playwright;
- Larastan/PHPStan;
- Vue Test Utils/Vitest donde aporte valor.

## Pyramid

### Unit
- formulas;
- state transitions;
- value objects.

### Feature
Mayor cobertura:
- policies;
- actions;
- transactions;
- DB.

### Browser
Critical workflows.

## Required suites

```text
tests/Feature/Platform
tests/Feature/Tenancy
tests/Feature/Auth
tests/Feature/Modules
tests/Unit/Domain
tests/Browser
```

## Tenant isolation suite

Debe cubrir:
- DB;
- auth;
- storage;
- cache;
- jobs;
- signed URLs;
- exports;
- search;
- analytics;
- permissions;
- Octane reset.

## Financial invariants

Tests explícitos:
- no duplicate payment;
- no duplicate material;
- no duplicate compensation;
- refund bounds;
- split sum;
- allocations.

## Static analysis

Objetivo:
- Larastan/PHPStan nivel alto.
- Sin baseline para código nuevo.
- Excepciones documentadas y mínimas.

## Mutation testing

No obligatorio inicial.
Evaluar para módulos financieros cuando suite madura.

## CI fail conditions

- test fail;
- type error;
- lint;
- static analysis;
- high/critical dependency policy;
- secret found;
- container critical CVE without exception;
- tenant isolation fail.

## Coverage

No perseguir porcentaje cosmético.
Exigir cobertura de:
- invariants;
- states;
- permissions;
- tenancy;
- money.
