# 07 — Infraestructura y DevOps

## Producción recomendada

Linux:
```text
Ubuntu Server 26.04 LTS
```

No usar XAMPP/Windows como target principal de BSDental Cloud.

## Containerization

Docker/OCI.

Servicios lógicos:
- app/web;
- queue workers;
- scheduler;
- MySQL;
- Redis;
- object storage externo o compatible;
- backup agent.

MySQL/Redis pueden ser managed services.

## Docker security

- imagen mínima;
- multi-stage build;
- usuario no-root;
- no compilers/dev deps en runtime;
- healthcheck;
- read-only filesystem donde sea compatible;
- capabilities mínimas;
- no secrets baked into image.

## Environments

```text
local
ci
staging
production
```

No usar staging con datos reales no anonimizados.

## Git workflow

- protected main;
- PR requerido;
- checks requeridos;
- no force push main;
- CODEOWNERS para seguridad/infra si equipo crece.

## CI pipeline

### PHP
1. composer validate
2. composer install --no-interaction
3. composer audit
4. Pint check
5. Larastan
6. Pest unit/feature
7. tenancy isolation suite

### JS
1. npm ci
2. npm audit policy
3. ESLint
4. typecheck
5. frontend tests
6. Vite build

### Security
1. Gitleaks
2. Trivy filesystem/dependency
3. image scan
4. SBOM release artifact
5. ZAP staging on deploy candidate

### Browser
Pest Browser/Playwright smoke on critical flows.

## CD

```text
Build immutable image
→ scan
→ deploy staging
→ migrations canary
→ smoke
→ security smoke
→ approval/release policy
→ deploy production
→ landlord migrations
→ tenant migrations batches
→ health
```

## Zero/low downtime

Schema expand/contract.
No destructive migration in same release que cambia código consumidor.

## Rollback

Cada release declara:
- code rollback;
- schema rollback;
- data rollback;
- backup requirement.
