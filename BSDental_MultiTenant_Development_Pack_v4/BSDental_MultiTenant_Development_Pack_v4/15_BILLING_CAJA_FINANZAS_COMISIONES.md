# 15 — Billing, Caja, Finanzas y Comisiones

## Billing

```text
Charge
Payment
PaymentSplit
PaymentAllocation
Refund
CreditAdjustment
Receipt
```

## Reglas

```text
SUM(payment_splits) = payment.total
SUM(allocations) <= available payment amount
refund <= refundable balance
```

## CxC

Solo cargos válidos.
Nunca presupuesto por sí mismo.

Aging:
- 0-30
- 31-60
- 61-90
- +90

## Caja

CashSession:
```text
open → closing_review → closed
```

Closed no recibe movimiento.
Reopen requiere permiso + razón + audit.

Efectivo físico separado de:
- tarjeta;
- transferencia;
- cheque.

## Producción

Nace del procedimiento realizado según política económica.

## Cobrado neto

```text
gross_collected - confirmed_refunds
```

## Costos directos

```text
material_consumption_cost
+ recognized_laboratory_cost
+ direct_costs_configured
```

## Profesional

Reglas:
- percentage production;
- percentage collected;
- fixed procedure;
- manual.

Regla congelada en accrual.

## Utilidad gerencial

```text
contribution_margin
= production
- professional_accruals
- direct_costs
```

```text
operating_profit_estimate
= contribution_margin
- operating_expenses
```

## Flujo

```text
cash_inflows - cash_outflows
```

No igualar utilidad con flujo.

## Idempotencia

Pago/procedure/refund/accrual deben estar protegidos contra doble ejecución.
