# 20 — Observabilidad, Backups y Disaster Recovery

## Laravel Pulse

Usar para:
- slow requests;
- slow jobs;
- slow queries;
- app performance.

## Horizon

- queue throughput;
- wait;
- failures;
- runtime.

## Structured logs

Campos:
- environment;
- tenant_uuid;
- correlation_id;
- user_id;
- module;
- action.

## Health

Platform dashboard:
- tenant status;
- DB;
- schema version;
- storage;
- queue;
- scheduler;
- mail;
- WhatsApp;
- backup freshness.

## Alerting

Alertar:
- repeated 5xx;
- queue backlog critical;
- database unreachable;
- backup overdue;
- tenant migration failed;
- scheduler heartbeat missing;
- disk/object storage issue;
- integration error rate.

## Backup

Por tenant:
- MySQL backup;
- files;
- encrypted;
- off-host.

Landlord:
- separate backup.

## Restore drill

No considerar backup válido hasta restaurarlo.

Procedimiento:
1. choose tenant test backup;
2. restore isolated environment;
3. migrate/check schema;
4. smoke;
5. record RTO/RPO achieved;
6. destroy test restore safely.

## DR

Documentar:
- secrets recovery;
- DNS;
- object storage;
- MySQL;
- Redis;
- application image;
- queues.

Redis no debe ser necesario para reconstruir datos clínicos fuente.
