# 10 — Modelo de Datos v4

## Landlord

```text
tenants
tenant_domains
tenant_database_connections
plans
plan_modules
tenant_module_overrides
feature_flags
feature_flag_tenants
provisioning_runs
provisioning_steps
tenant_schema_status
tenant_health_checks
platform_users
platform_audit_entries
platform_metrics
```

## Tenant Core

```text
settings
branches
rooms
users
roles
permissions
professionals
specialties
professional_branch
files
audit_entries
notifications
```

## Patients
```text
patients
patient_contacts
patient_addresses
patient_tags
patient_notes
patient_files
```

## Appointments
```text
appointment_types
appointments
appointment_status_history
professional_schedules
schedule_blocks
waiting_list_entries
```

## Clinical
```text
clinical_histories
medical_antecedents
allergies
medications
clinical_encounters
diagnoses
clinical_evolutions
vital_signs
prescriptions
clinical_files
```

## Consents
```text
consent_templates
consent_template_versions
consents
consent_signatures
consent_files
```

## Odontogram
```text
odontograms
tooth_conditions
odontogram_entries
tooth_surface_entries
odontogram_history
```

## Quotes/Treatments
```text
treatment_alternatives
treatment_alternative_items
procedure_catalog
procedure_prices
quotes
quote_versions
quote_items
quote_approvals
treatment_plans
treatment_phases
treatment_items
treatment_sessions
procedure_executions
```

## Inventory
```text
inventory_items
categories
units
warehouses
stock_lots
stock_movements
purchases
purchase_items
procedure_material_rules
procedure_execution_materials
```

## Laboratory
```text
laboratories
laboratory_orders
laboratory_order_items
laboratory_status_history
laboratory_payables
laboratory_payments
```

## Billing/Cash/Finance
```text
charges
payments
payment_splits
payment_allocations
payment_methods
receipts
refunds
credit_adjustments
cash_registers
cash_sessions
cash_movements
expenses
expense_categories
accounts_payable
accounts_payable_payments
professional_compensation_rules
professional_compensation_accruals
professional_settlements
professional_settlement_items
professional_payments
```

## CRM/Marketing
```text
follow_ups
follow_up_attempts
crm_stages
patient_crm_state
segments
campaigns
campaign_recipients
message_templates
message_deliveries
communication_preferences
```

## Money

DECIMAL.
No FLOAT.

## Time

UTC persistido.
Timezone tenant para presentación y reglas locales.

## IDs

Internal BIGINT permitido.
Public identifiers: UUID/ULID cuando eviten enumeración o faciliten integración.

No exponer IDs secuenciales sensibles sin authorization.

## Deleted data

Soft delete no sustituye política de retención.
Clinical finalized records usan enmiendas/versionado.
Financial ledgers usan reversos.
