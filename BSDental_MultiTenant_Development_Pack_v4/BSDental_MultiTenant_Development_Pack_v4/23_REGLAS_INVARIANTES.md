# 23 — Invariantes v4

## Tenancy
1. Request tenant pertenece a un único tenant.
2. Host desconocido no obtiene tenant.
3. Central domain no usa tenant DB.
4. A nunca lee/escribe B.
5. Context se limpia después request/job.
6. Job retry conserva tenant.
7. Cache namespace tenant.
8. Storage namespace tenant.
9. Signed URL tenant-bound.
10. Export tenant-only.
11. Provisioning fallido no activa tenant.
12. Un dominio pertenece a un tenant.
13. Todos ejecutan misma versión de código.

## Auth/Security
14. Platform account != tenant account.
15. Entitlement != permission.
16. Feature flag != permission.
17. Usuario inactivo no accede.
18. 2FA obligatorio para roles definidos.
19. Acción crítica requiere autorización server-side.
20. Secrets no logs.

## Clinical
21. Diagnosis != planned.
22. Planned != approved.
23. Approved != completed.
24. Appointment != performed.
25. Finalized clinical note no se sobrescribe.
26. Consent signed no se sobrescribe.

## Treatment
27. CompleteProcedure es idempotente.
28. Retry no duplica production.
29. Clinical progress != payment progress.

## Billing
30. Quote != receivable.
31. Payment splits suman total.
32. Allocations no exceden disponible.
33. Refund no excede reembolsable.
34. Confirmed payment no se edita destructivamente.
35. Refund reduce collected; no es expense por defecto.

## Cash
36. Closed session no recibe movimientos.
37. Reopen requiere audit.
38. Cash counted excluye tarjeta/transferencia.
39. Diferencia queda registrada.

## Inventory
40. Stock cambia por movement.
41. Purchase != consumption.
42. Transfer tiene out/in enlazados.
43. Consumption retry no duplica.
44. Cost usa política documentada.

## Lab
45. Lab order != cost != payable payment.
46. Pagar payable no reconoce costo de nuevo.

## Compensation
47. Accrual usa rule snapshot.
48. Accrual != settlement != payment.
49. Collected-based accrual es incremental, no recalcula total por cada pago.

## Finance
50. Production != collected.
51. Collected != clinic-attributable cash.
52. Profit != cash flow.
53. Purchase + consumption no se duplican en profit.
54. KPI usa fecha/fuente definida.
55. KPI financiero reconcilia.

## Integrations
56. WhatsApp failure no revierte cita.
57. Webhook idempotente.
58. Campaign de tenant A no satura critical queue global sin límites.

## Runtime
59. Octane request B no hereda tenant A.
60. Permission cache cambia correctamente entre tenants.
