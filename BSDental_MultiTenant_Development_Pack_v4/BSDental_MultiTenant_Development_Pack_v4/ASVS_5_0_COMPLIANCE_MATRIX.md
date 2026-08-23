# Matriz de Cumplimiento de Seguridad — OWASP ASVS 5.0

**Proyecto:** BSDental v4 (Multi-Tenant Clinical Platform)  
**Nivel Objetivo:** ASVS 5.0 Level 2 + Controles Level 3 seleccionados (Tenancy, Platform Admin, Criptografía, Auditoría).  
**Fecha de Evaluación:** 2026-08-23  
**Estado:** PASS (Todos los controles base verificados y automatizados)

---

## 1. Arquitectura y Tenancy (V1)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V1.1.1** | Separación física o lógica estricta entre tenants | **PASS** | `Database-per-tenant` físico (`TenantContext`, `TenantModel`), `tenants/{uuid}/` storage y `bsdental:{uuid}:` cache keys. |
| **V1.4.1** | Control de acceso centralizado sin bypass | **PASS** | Middlewares `PreventCentralDomainFromAccessingTenantDb`, `ResolveTenantFromHost`, `RequirePlatformAdmin`. |
| **V1.5.1** | Aprovisionamiento e inicialización segura | **PASS** | `TenantProvisioningPipeline` con 12 pasos automatizados e idempotentes. |

---

## 2. Autenticación y Gestión de Sesiones (V2 & V3)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V2.1.1** | Hashing robusto de contraseñas | **PASS** | `Argon2id` / `Bcrypt` en `PlatformUser` y `User`. |
| **V2.8.1** | Autenticación Multi-Factor (MFA / 2FA) | **PASS** | TOTP 2FA obligatorio en Platform Admin (`RequirePlatformTwoFactor`) y soporte en Tenant (`RequireTenantTwoFactor`). |
| **V2.2.1** | Rate limiting contra ataques de fuerza bruta | **PASS** | 5 intentos/minuto por email+IP en login, 5/min en 2FA, 3/hora en password reset. |
| **V3.2.1** | Cookies de sesión seguras | **PASS** | `HttpOnly: true`, `SameSite: Lax`, `Secure` en HTTPS, `lifetime: 120min`. |
| **V3.3.1** | Regeneración de identificador de sesión | **PASS** | `session()->regenerate()` ejecutado tras autenticación exitosa. |

---

## 3. Control de Acceso y Entitlements (V4)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V4.1.1** | Principio de menor privilegio por rol | **PASS** | `TenantRbacSeeder` con 9 roles clínicos y permisos granulares (`spatie/laravel-permission`). |
| **V4.2.1** | Control de acceso server-side a módulos comerciales | **PASS** | `EnsureModuleEntitlement` middleware (`Plan` y `module_overrides`). |

---

## 4. Criptografía y Protección de Datos (V6 & V8)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V6.2.1** | Cifrado en reposo para secretos y credenciales | **PASS** | Cast `encrypted` en `two_factor_secret`, credenciales y tokens de integración. |
| **V8.2.1** | Protección de datos sensibles en auditoría | **PASS** | `AuditLogger` con enmascaramiento recursivo automático (`[REDACTED]`) para contraseñas, tokens y tarjetas. |

---

## 5. Subida Segura de Archivos (V12)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V12.1.1** | Allow-list estricta de tipos MIME | **PASS** | `SecureUploadService` restringido a imágenes (`jpeg`, `png`, `webp`), documentos (`pdf`) y `dicom`. |
| **V12.1.2** | Verificación de firma/magic bytes | **PASS** | Inspección vía `finfo(FILEINFO_MIME_TYPE)` antes de almacenar. |
| **V12.2.1** | Nombres de archivo aleatorizados e inejecutables | **PASS** | UUID v4 aleatorio para nombres de archivo en almacenamiento privado (`tenants/{uuid}/...`). |

---

## 6. Cabeceras HTTP y Protección del Navegador (V14)

| Control ASVS | Descripción | Estado | Implementación / Referencia en Código |
| :--- | :--- | :--- | :--- |
| **V14.4.1** | Content Security Policy (CSP) | **PASS** | `SetSecurityHeaders` con `nonce` criptográfico aleatorio por request. |
| **V14.4.2** | HSTS (Strict-Transport-Security) | **PASS** | `max-age=31536000; includeSubDomains`. |
| **V14.4.3** | Prevención de MIME sniffing y Clickjacking | **PASS** | `X-Content-Type-Options: nosniff` y `X-Frame-Options: SAMEORIGIN`. |
| **V14.4.4** | Privacidad de navegación y permisos de hardware | **PASS** | `Referrer-Policy: strict-origin-when-cross-origin` y `Permissions-Policy`. |