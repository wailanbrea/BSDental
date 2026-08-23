# 05 — Autenticación, RBAC y Sesiones

## Guards separados

### Platform
Usuarios BSolutions.

### Tenant
Usuarios de cada clínica.

Nunca una cuenta Platform es automáticamente tenant user.

## Fortify

Usar Laravel Fortify como backend headless para:
- login;
- reset;
- email verification;
- 2FA;
- recovery.

UI Vue propia.

## Sanctum

Solo para:
- API first-party futura;
- aplicación móvil;
- integraciones autorizadas.

El panel Inertia usa sesión/cookie, no tokens guardados en localStorage.

## RBAC

Spatie Permission 8.3 dentro de tenant DB.

Roles son presets.
Permisos son fuente de autorización funcional.

Ejemplos:
```text
patients.view
patients.create
clinical.view
clinical.write
clinical.finalize
quotes.create
quotes.approve
payments.create
payments.refund
cash.close
cash.reopen
finance.view
inventory.adjust
professional_settlements.close
```

## Entitlement + permission

Acceso:

```text
tenant active
AND module enabled
AND user active
AND permission allowed
AND object/branch scope allowed
```

## Branch scope

Un usuario puede:
- all branches;
- selected branches.

No duplicar usuario por sucursal.

## Session controls

- listar sesiones activas si es viable;
- logout other sessions;
- idle timeout configurable;
- absolute max session configurable;
- privileged action re-auth.

## Rate limits

Separar:
- login IP;
- login account;
- password reset;
- public booking;
- API;
- WhatsApp webhooks;
- exports.

No usar un único límite global.
