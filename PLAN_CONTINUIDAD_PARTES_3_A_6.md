# BSDental v4 — Plan de continuidad para otra IA (partes 3 a 6)

## 1. Propósito y estado de partida

Este documento es el relevo técnico para continuar BSDental v4 después de completar:

- **Parte 1 — Seguridad operacional y RBAC:** permisos aplicados a rutas, navegación filtrada, alcance explícito por sucursal, administración de usuarios/roles/sucursales y auditoría.
- **Parte 2 — Clinical Precision:** todas las páginas clínicas usan `ClinicLayout`; las diez vistas operativas heredadas comparten la piel `clinical-precision-page` y los tokens institucionales.

No asumir que `PROJECT_STATUS.md` representa el estado real: el código, las rutas, las pruebas y este documento son la fuente vigente.

### Línea base que debe permanecer verde

```powershell
php artisan test --compact
vendor\bin\pint --test
vendor\bin\phpstan analyse --no-progress
npm run quality
```

Última validación al redactar este relevo: **82 pruebas, 1,015 aserciones**, PHPStan sin errores, TypeScript/ESLint sin errores ni advertencias y build de producción correcto.

Entorno de demostración local: `http://demo.localhost:8000`.

## 2. Reglas obligatorias para continuar

1. Preservar el aislamiento database-per-tenant y resolver siempre `TenantContext` antes de acceder a modelos tenant.
2. No introducir rutas clínicas sin `auth:web`, usuario activo, 2FA, permiso RBAC y, cuando corresponda, alcance de sucursal.
3. Toda operación financiera, clínica inmutable, reapertura, ajuste o cambio de permisos debe producir auditoría tenant.
4. No borrar datos de negocio para “corregir” estados. Usar reversos, ajustes, anulaciones o soft delete según el dominio.
5. No ejecutar limpieza recursiva ni comandos destructivos sobre raíces, perfiles o carpetas amplias. Seguir las reglas de seguridad del `AGENTS.md` suministrado por el usuario.
6. Editar con `apply_patch`, verificar el diff antes de cada commit y mantener cambios ajenos intactos.
7. Consultar primero `graphify-out/graph.json` mediante `graphify query` y ejecutar `graphify update .` al cerrar un bloque.
8. Validar los flujos importantes en el navegador real sin modificar datos demo salvo que la prueba requiera explícitamente una escritura controlada.

---

# Parte 3 — Facturación y caja avanzada

## FIN-01 — Reapertura controlada de sesión de caja

### Estado actual

`CashRegisterService::reopenSession()` existe, pero no tiene controlador, ruta ni interfaz.

### Implementación

- Añadir `CashRegisterController::reopen()`.
- Crear `POST /cash-sessions/{id}/reopen` con permiso `cash.reopen`.
- Validar motivo obligatorio de 10–500 caracteres.
- Impedir reapertura si ya existe otra sesión abierta para la misma caja.
- Guardar quién reabre, cuándo y por qué. Si el schema actual no lo permite, crear una migración tenant aditiva.
- Registrar `cash.session_reopened` en auditoría.
- Exponer la acción únicamente en sesiones cerradas y para usuarios autorizados.

### Criterios de aceptación

- Usuario sin `cash.reopen`: `403`.
- Motivo vacío: `422`.
- Reapertura válida conserva los valores del cierre anterior y deja trazabilidad completa.
- No pueden quedar dos sesiones abiertas en la misma caja.

## FIN-02 — Múltiples cajas y sesiones simultáneas

### Problema actual

`CashRegisterController::index()` obtiene una única sesión activa con `first()`. Esto oculta otras cajas abiertas de distintas sucursales.

### Implementación

- Devolver la sesión activa de cada caja, no una sesión global.
- Filtrar cajas por las sucursales permitidas al usuario.
- Mostrar tarjetas independientes con esperado, entradas, salidas y estado.
- Hacer que cobros en efectivo seleccionen la caja abierta adecuada cuando haya más de una.
- Validar que la caja elegida pertenezca a una sucursal autorizada.

### Criterios de aceptación

- Dos cajas de sedes distintas pueden estar abiertas simultáneamente.
- Un usuario restringido solo ve y usa sus cajas asignadas.
- El movimiento termina en la caja seleccionada, nunca en la primera encontrada.

## FIN-03 — Detalle histórico y reporte de arqueo

- Crear pantalla `/cash-sessions/{id}`.
- Mostrar apertura, cierre, usuarios, movimientos por método, efectivo esperado, contado y diferencia.
- Incluir filtros y totales por efectivo, tarjeta, transferencia, Zelle, cheque y seguro.
- Añadir impresión y exportación CSV auditada.
- No exponer datos de sucursales no autorizadas.

## FIN-04 — Idempotencia financiera

- Añadir `idempotency_key` único por tenant a cobros, reembolsos y movimientos manuales.
- Aceptar una clave generada por frontend por cada intención de envío.
- Si se repite la misma clave y payload, devolver el resultado anterior.
- Si se repite la clave con payload diferente, rechazar con conflicto.
- Cubrir doble clic, reintento HTTP y solicitudes concurrentes.

## FIN-05 — Notas de crédito y ajustes

- Crear `credit_adjustments` o modelo equivalente inmutable.
- Definir tipos: descuento posterior, corrección, incobrable, crédito a favor y reverso.
- Nunca reducir directamente el cargo original sin una entrada de ajuste.
- Recalcular saldo del cargo desde cargos, asignaciones, reembolsos y ajustes.
- Crear listado y detalle imprimible con numeración correlativa.
- Auditar creación, aplicación y reverso.

## FIN-06 — Estado de cuenta, CxC e invoices

- Crear estado de cuenta imprimible por paciente con cargos, pagos, asignaciones, reembolsos, créditos y saldo.
- Crear listado CxC navegable por antigüedad: 0–30, 31–60, 61–90 y +90 días.
- Permitir abrir el paciente/cargo desde cada bucket y exportar con permiso `finance.reports`.
- Separar “cargo clínico”, “recibo de pago” y “factura fiscal”.
- No implementar facturación electrónica hasta decidir jurisdicción, secuencias fiscales, impuestos y proveedor.

Archivos de entrada principales:

- `app/Core/Services/BillingPaymentService.php`
- `app/Core/Services/CashRegisterService.php`
- `app/Core/Controllers/BillingController.php`
- `app/Core/Controllers/CashRegisterController.php`
- `resources/js/Pages/Clinic/Billing/`
- `resources/js/Pages/Clinic/Cash/Index.vue`
- `database/migrations/tenant/2026_08_22_000012_create_tenant_billing_cash_tables.php`
- `tests/Feature/TenantBillingAndCashTest.php`

---

# Parte 4 — Inventario, laboratorio y CRM

## INV-01 — Kardex completo

- Añadir movimientos manuales de ajuste con motivo y permiso `inventory.adjust`.
- Añadir transferencias entre almacenes como salida y entrada enlazadas dentro de una transacción.
- Implementar conteo físico y ajuste de diferencia sin alterar movimientos históricos.
- Mostrar Kardex filtrable por artículo, almacén, lote, tipo, fecha y usuario.
- Exportar CSV con auditoría.

## INV-02 — Lotes, vencimientos y reglas de consumo

- CRUD de lotes, proveedor, costo, vencimiento y cantidad disponible.
- Alertas de vencimiento configurable.
- UI para `procedure_material_rules` con cantidad y unidad por procedimiento.
- Vista previa del consumo antes de completar un tratamiento.
- Mantener FIFO/FEFO e idempotencia existentes en `InventoryStockService`.

## LAB-01 — Catálogo y ciclo de órdenes

- CRUD de laboratorios y contactos.
- Fechas prometida, enviada, recibida y entregada.
- Archivos, color, material, pieza dental y notas por orden.
- Transiciones de estado válidas únicamente; no permitir saltos arbitrarios.
- Alertas de atraso y navegación al paciente/plan relacionado.

## LAB-02 — Costos y cuentas por pagar

- Mantener separados costo estimado, costo final y pago.
- Registrar liquidaciones parciales al laboratorio.
- Crear reporte de saldos por laboratorio.
- No contabilizar una compra o costo estimado como pago realizado.

## CRM-01 — Etapas y segmentos

- UI para configurar etapas CRM.
- Segmentos dinámicos por última visita, presupuesto pendiente, tratamiento incompleto, no-show y recall.
- Vista del historial de contacto por paciente.

## CRM-02 — Campañas y WhatsApp real

- CRUD de plantillas y campañas.
- Exclusión obligatoria por consentimiento/opt-out.
- Selección de segmento con conteo previo.
- Encolado por tenant, reintentos limitados y registro de proveedor.
- Estados: queued, sent, delivered, read, failed, cancelled.
- Mantener firma e idempotencia del webhook.
- No conectar un BSP real hasta contar con credenciales y autorización del usuario.

Archivos de entrada:

- `app/Core/Controllers/InventoryController.php`
- `app/Core/Services/InventoryStockService.php`
- `app/Core/Controllers/DentalLabController.php`
- `app/Core/Controllers/CrmFollowUpController.php`
- `app/Core/Controllers/WhatsAppWebhookController.php`
- `resources/js/Pages/Clinic/Inventory/Index.vue`
- `resources/js/Pages/Clinic/Lab/Index.vue`
- `resources/js/Pages/Clinic/Crm/Index.vue`
- `tests/Feature/TenantInventoryAndLabTest.php`
- `tests/Feature/TenantFollowUpAndWhatsAppTest.php`

---

# Parte 5 — Flujos clínicos y administrativos

## CLN-01 — Fusión auditada de duplicados

- El detector de duplicados ya existe; falta resolverlos.
- Seleccionar paciente maestro y candidato.
- Mostrar una comparación previa campo por campo.
- Reasignar relaciones dentro de una transacción.
- Conservar alias e identificadores del registro fusionado.
- Soft-delete del duplicado y auditoría con ambos UUID.
- Bloquear fusión si produce conflictos financieros o clínicos sin resolver.

## CLN-02 — Plantillas y firma de consentimientos

- Administración versionada de plantillas.
- Variables permitidas explícitas; rechazar variables desconocidas.
- Captura de firma, identidad del firmante y relación con el paciente.
- Snapshot inmutable, hash y entrega/impresión del documento firmado.
- Nunca modificar una versión que ya tenga consentimientos firmados.

## CLN-03 — Presupuestos y planes

- Editar y duplicar presupuestos en borrador.
- Nueva versión al cambiar un presupuesto presentado.
- Impresión/PDF con identidad de clínica, vigencia y condiciones.
- Edición controlada de fases del plan antes de ejecutar procedimientos.
- Registrar cancelación o reverso de un procedimiento sin borrar ejecución clínica.

## CLN-04 — Archivos, prescripciones y evoluciones

- Clasificación de archivos por radiografía, fotografía, laboratorio, consentimiento y otros.
- Archivo/reemplazo auditado; no sobrescribir el objeto original.
- Impresión de prescripciones con profesional, licencia e instrucciones.
- Exportación de evolución clínica respetando inmutabilidad y enmiendas.

Archivos de entrada:

- `app/Core/Controllers/PatientController.php`
- `app/Core/Controllers/ConsentController.php`
- `app/Core/Controllers/QuoteController.php`
- `app/Core/Controllers/TreatmentPlanController.php`
- `app/Core/Controllers/ClinicalEncounterController.php`
- `resources/js/Pages/Clinic/Patients/`
- `resources/js/Pages/Clinic/Consents/`
- `resources/js/Pages/Clinic/Quotes/`
- `resources/js/Pages/Clinic/TreatmentPlans/`

---

# Parte 6 — Preparación de entrega

## REL-01 — E2E por rol

Crear recorridos para Owner, ClinicDirector, GeneralDentist, Receptionist, Cashier, LabTechnician e InventoryManager.

Cada rol debe validar:

- enlaces visibles;
- enlaces ocultos;
- acceso directo permitido;
- acceso directo prohibido con `403`;
- alcance por sucursal;
- acciones críticas y auditoría.

## REL-02 — Accesibilidad y responsive

- Navegación completa por teclado.
- Foco visible y retorno de foco al cerrar modales.
- Etiquetas accesibles en inputs y botones iconográficos.
- Contraste WCAG AA.
- Tablas utilizables en móvil.
- Pruebas en 375, 768, 1280 y 1440 px.

## REL-03 — Calidad continua

La línea base se entrega con ESLint sin errores ni advertencias. No aceptar nuevos warnings ni desactivar reglas para ocultarlos. Mantener especialmente la salida escapada del paginador de notificaciones y los nombres camelCase compartidos por el dashboard.

## REL-04 — Concurrencia y seguridad

- Cobro y reembolso concurrente.
- Doble cierre/reapertura de caja.
- Dos citas intentando el mismo sillón/profesional.
- Consumo concurrente del mismo lote.
- Archivos con MIME falso y contenido inválido.
- Pruebas de aislamiento entre tenants y sucursales en todas las nuevas rutas.

## REL-05 — Decisiones externas bloqueantes

Solicitar al usuario antes de implementar:

- dominio final y certificados;
- proveedor/cuenta WhatsApp BSP;
- jurisdicción y proveedor de facturación electrónica;
- política legal de retención y eliminación;
- integraciones de seguros;
- precios comerciales y módulos por plan.

## REL-06 — Cierre de cada bloque

1. Ejecutar pruebas específicas.
2. Ejecutar la regresión completa.
3. Recorrer las pantallas afectadas en navegador.
4. Ejecutar `graphify update .` y diagnóstico del grafo.
5. Revisar `git diff --check` y `git status`.
6. Crear un commit pequeño y descriptivo.
7. Actualizar este documento: marcar tareas cerradas, registrar decisiones y anotar el commit.

## Orden recomendado

1. FIN-01 y FIN-02.
2. FIN-03 y FIN-04.
3. FIN-05 y FIN-06.
4. INV-01, INV-02, LAB-01 y LAB-02.
5. CRM-01 y CRM-02.
6. CLN-01 a CLN-04.
7. REL-01 a REL-06.

No avanzar a facturación electrónica, WhatsApp real o despliegue productivo mientras las decisiones externas correspondientes sigan abiertas.
