# Architecture Decision Records

## ADR-001 — Modern monolith
Accepted.

Laravel + Inertia + Vue. No microservices initially.

## ADR-002 — Database-per-tenant
Accepted.

Landlord control DB + DB per dental organization.

## ADR-003 — One app version
Accepted.

No per-client code versions.

## ADR-004 — Stable-over-prerelease
Accepted.

Use latest stable production-supported versions, not beta/RC merely because they are newer.

## ADR-005 — Inertia 3
Accepted.

v4 upgrades plan from Inertia 2 to current stable major 3.

## ADR-006 — PHP 8.5
Accepted.

PHP 8.5 current stable branch. PHP 8.6 beta prohibited production.

## ADR-007 — Node LTS
Accepted.

Node 24 LTS for production/build tooling instead of Node Current.

## ADR-008 — Octane + FrankenPHP gated
Accepted.

Production target after tenant-state isolation tests pass.

## ADR-009 — ASVS 5.0
Accepted.

Level 2 baseline + selected Level 3 controls.

## ADR-010 — Pest 5
Accepted.

Pest 5/PHPUnit 13 baseline.

## ADR-011 — No Windows/XAMPP production target
Accepted.

BSDental Cloud production baseline is Linux LTS/containerized. Windows/XAMPP can remain local/legacy tooling.
