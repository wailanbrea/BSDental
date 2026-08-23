# 27 — Releases, Migraciones y Compatibilidad

## Code

Una versión global.

## Schema

Track por tenant:
```text
current_schema_version
target_schema_version
migration_status
last_error
```

## Strategy

Expand:
- add compatible structures.

Backfill:
- jobs bounded/tenant-aware.

Switch:
- code begins new structure.

Contract:
- remove old in later release.

## Canary

1. internal/demo;
2. pilot tenant;
3. small batch;
4. larger batch;
5. all.

## Stop conditions

Pausar rollout:
- tenant migration failure;
- elevated 5xx;
- cross-tenant anomaly;
- performance regression severe;
- data reconciliation failure.

## Dependency upgrades

Patch:
- automated PR possible;
- tests required.

Minor:
- review changelog.

Major:
- ADR + dedicated branch + full regression + benchmark.

No usar `composer update` directo en producción.
