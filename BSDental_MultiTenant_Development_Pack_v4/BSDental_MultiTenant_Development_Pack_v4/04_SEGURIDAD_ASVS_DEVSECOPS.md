# 04 — Seguridad: ASVS 5.0 + DevSecOps

## Baseline

Objetivo contractual/técnico:
- OWASP ASVS 5.0 Level 2.
- Controles Level 3 seleccionados para tenancy, Platform Admin, autenticación, criptografía, datos clínicos y soporte.

No declarar “HIPAA compliant”, “GDPR compliant” u otra certificación sin evaluación legal/técnica formal.

## Auth

- Argon2id.
- email verification.
- password reset token corto y single-use.
- 2FA TOTP.
- recovery codes.
- session regeneration on login/elevation.
- session revocation.
- inactivity policy configurable.
- re-authentication para acciones críticas.

2FA obligatorio:
- Platform Admin;
- Tenant Owner/Admin;
- Finance/Cash privileged users.

## Cookies

- Secure;
- HttpOnly;
- SameSite=Lax por defecto;
- domain scope mínimo;
- HTTPS only.

## Browser headers

- HSTS;
- CSP con nonce;
- X-Content-Type-Options;
- Referrer-Policy;
- Permissions-Policy;
- frame-ancestors vía CSP.

## Authorization

- Policies/Gates backend.
- RBAC.
- module entitlement.
- tenant status.
- branch scope cuando aplique.
- object-level authorization.

## Sensitive actions

Requerir motivo/audit/re-auth cuando aplique:
- refund;
- reopen cash session;
- delete/archive records;
- role change;
- support access;
- export masivo;
- restore;
- tenant suspension;
- professional settlement changes.

## Encryption

### At transit
TLS.

### At rest
- disk/database encryption según hosting;
- object storage server-side encryption;
- backup encryption.

### Field-level
Usar selectivamente para:
- provider credentials;
- integration tokens;
- secretos;
- identifiers de alta sensibilidad si requisitos lo exigen.

No cifrar indiscriminadamente columnas que deban indexarse/consultarse.

## Uploads

- allow-list MIME;
- magic bytes;
- max size;
- randomized object keys;
- no executable storage;
- private;
- optional malware scan pipeline;
- image re-encoding cuando sea seguro;
- signed temporary download.

## Logging

No:
- password;
- access token;
- full medical note;
- full document body;
- payment card data.

Sí:
- tenant UUID;
- user ID;
- action;
- correlation ID;
- resource ID;
- error class.

## Secrets

- `.env` nunca Git.
- secret manager en producción.
- rotation.
- scoped credentials.
- GitHub Actions OIDC cuando sea posible.

## Supply chain

CI:
- `composer audit`;
- npm audit high/critical;
- Dependabot;
- Gitleaks;
- Trivy;
- pinned GitHub Actions by commit SHA para workflows críticos;
- lockfiles obligatorios;
- release tags firmadas si infraestructura lo permite.

## DAST

Staging:
- OWASP ZAP baseline.
- pruebas manuales de tenant isolation antes de release mayor.

## CSP

Implementar desde temprano.
No esperar al final para descubrir que librerías UI requieren `unsafe-inline`.

## Security gate

No release si:
- vulnerability Critical abierta;
- High explotable sin mitigación;
- cross-tenant test falla;
- backup restore no validado;
- secrets scan falla.
