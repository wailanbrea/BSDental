# 24 — Research Baseline — 22 Agosto 2026

Este documento registra por qué se seleccionó el stack.

## PHP
PHP 8.5.9 es stable/security release.
PHP 8.6 está en Beta y no se usa en producción.

Fuentes:
- https://www.php.net/
- https://www.php.net/ChangeLog-8.php

## Laravel
Laravel 13:
- PHP 8.3–8.5 soportado;
- release 17 Mar 2026;
- security fixes hasta 17 Mar 2028.

Fuente:
- https://laravel.com/docs/13.x/releases

## Vue
Vue 3.5.41 stable: 5 Aug 2026.
Vue 3.6 RC sigue prerelease.

Fuente:
- https://github.com/vuejs/core/blob/main/CHANGELOG.md

## Inertia
Inertia v3 release: 26 Mar 2026.
Es major actual.
Incluye plugin Vite oficial, smaller built-in XHR client, optimistic updates, layout props y SSR simplificado.

Fuente:
- https://inertiajs.com/docs/v3/getting-started

## Vite
Vite 8 estable: Mar 2026.
Vite 8.1: 23 Jun 2026.
Rolldown Rust unifica bundling.

Fuente:
- https://vite.dev/blog/announcing-vite8-1

## Tailwind
Tailwind CSS 4.3: 8 May 2026.

Fuente:
- https://tailwindcss.com/blog/tailwindcss-v4-3

## TypeScript
TypeScript 5.9 docs actualizadas 27 Jul 2026.

Fuente:
- https://www.typescriptlang.org/docs/handbook/release-notes/typescript-5-9.html

## Node
24.19.0 es Latest LTS.
26.x es Current.

Fuente:
- https://nodejs.org/en/download

## MySQL
Usar MySQL 8.4 LTS, último patch disponible/compatible.
8.4 es track LTS; preferido a Innovation para plataforma clínica.

Fuente:
- https://dev.mysql.com/doc/relnotes/mysql/8.4/en/

## Redis
Redis OSS 8.8 GA; 8.8.1 incluye security fix (Jul 2026).

Fuente:
- https://redis.io/docs/latest/operate/oss_and_stack/stack-with-enterprise/release-notes/redisce/redisos-8.8-release-notes/

## Spatie Multitenancy
4.2.0, 7 Aug 2026.
Incluye corrección importante de restauración de tenant alrededor de jobs sync/retry.

Fuente:
- https://github.com/spatie/laravel-multitenancy/releases

## Spatie Permission
8.3.0, 3 Jul 2026.
Incluye fixes de cache al cambiar tenant y tests del Octane reset listener.

Fuente:
- https://github.com/spatie/laravel-permission/releases

## Pest
Pest 5:
- PHP >= 8.4;
- PHPUnit 13;
- TIA;
- Browser/Agent plugins.

Fuente:
- https://pestphp.com/docs/pest5-now-available

## Larastan
3.10.0:
- PHPStan 2.2 compatibility;
- PHPUnit 13 compatibility.

Fuente:
- https://github.com/larastan/larastan/releases

## Security
OWASP ASVS latest stable: 5.0.0.
OWASP Top 10 current: 2025.

Fuentes:
- https://owasp.org/www-project-application-security-verification-standard/
- https://owasp.org/www-project-top-ten/

## Laravel production
Laravel documenta FrankenPHP para producción.
Octane mantiene la app en memoria y debe tratarse con especial cuidado en multi-tenancy.

Fuentes:
- https://laravel.com/docs/13.x/deployment
- https://laravel.com/docs/12.x/octane

## Política de actualización

No “actualizar por ser nuevo”.

Antes de un major:
1. verificar stable;
2. soporte framework;
3. soporte packages;
4. changelog;
5. security;
6. staging;
7. regression;
8. benchmark;
9. ADR.
