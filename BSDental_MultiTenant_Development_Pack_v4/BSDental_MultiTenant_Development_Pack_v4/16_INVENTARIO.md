# 16 — Inventario

## Control

```text
material
quantity
unit
supplier
purchase
lot
expiry
warehouse
stock
cost
```

## Ledger

Toda variación genera StockMovement.

No hacer:
```text
item.stock -= 2
```
sin movimiento.

## Purchase

- incrementa inventory;
- genera payable/cash effect.

## Consumption

- reduce inventory;
- imputa costo directo.

```text
PURCHASE != CONSUMPTION
```

## Cost method

Elegir y documentar:
- FEFO/FIFO para selección física;
- costo por lote/costo promedio para valorización según decisión de producto.

No implementar una mezcla implícita.

## Expiry alerts

- low stock;
- critical;
- near expiry;
- expired.

## Procedure consumption

Complete procedure:
- load material rule;
- propose lots;
- confirm actual consumption;
- movements;
- execution material snapshot;
- cost.

Retry no duplica.
