# 26 — Platform Admin BSolutions

## Objetivo

Operar BSDental sin entrar manualmente a servidores.

## Dashboard

- tenants;
- active/trial/suspended;
- provisioning;
- schema;
- app version;
- backups;
- queue;
- integrations;
- storage;
- domains;
- health.

## Tenant detail

Mostrar:
- tenant UUID;
- name;
- plan;
- modules;
- domains;
- DB health;
- schema version;
- backup freshness;
- queue failures;
- integration health.

No mostrar patient list por defecto.

## Actions

Privileged:
- provision;
- suspend;
- resume;
- verify domain;
- migrate selected;
- trigger backup;
- health check.

Todos auditados.

## Support access

Si posteriormente se permite impersonation:
- explicit permission;
- reason;
- TTL;
- banner;
- tenant+platform audit;
- no invisible mode.
