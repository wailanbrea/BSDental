# 18 — Analytics y Reportes

## KPI rule

Cada KPI define:
- fórmula;
- source tables;
- date field;
- filters;
- permissions;
- drill-down.

No usar “Ingresos” ambiguo.

## KPIs

### Operation
- appointments;
- no-show;
- occupancy;
- patients.

### Clinical
- treatments;
- completed;
- pending.

### Commercial
- quoted;
- approved;
- conversion.

### Financial
- production;
- gross collected;
- refunds;
- net collected;
- receivables;
- direct costs;
- professional accrual;
- expenses;
- margin;
- operating profit estimate;
- cash flow.

### Inventory
- stock;
- expiries;
- consumption.

### Lab
- open orders;
- payables;
- turnaround.

## Query strategy

Primero query optimizada.
Luego cache.
Luego aggregate table si métricas demuestran necesidad.

No crear data warehouse prematuramente.

## Platform analytics

Tenant publica agregados no PHI.
Landlord no recopila historia clínica.

## Exports

Queued.
Private.
Expiring download.
Audited.
Tenant scoped.
