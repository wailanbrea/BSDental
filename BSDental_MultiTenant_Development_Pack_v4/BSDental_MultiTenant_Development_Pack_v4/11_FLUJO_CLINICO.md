# 11 — Flujo Clínico Maestro

```text
Paciente
→ Cita
→ Check-in
→ Encuentro clínico
→ Diagnóstico
→ Odontograma
→ Alternativas
→ Presupuesto
→ Aprobación/Firma
→ Plan de tratamiento
→ Sesiones
→ Procedimientos
→ Seguimiento
```

## Estados conceptuales separados

```text
existing_condition
diagnosis
planned_treatment
approved_treatment
scheduled_treatment
completed_treatment
```

## Finalización clínica

ClinicalEncounter:
```text
draft → finalized → amended
```

No editar finalizado.

## Procedure

```text
pending
→ scheduled
→ in_progress
→ completed
```

Cancel/suspend según reglas.

## CompleteProcedure

Transacción:
1. lock item;
2. validate current state;
3. persist execution;
4. update item;
5. create clinical link;
6. register production snapshot;
7. commit.

Después:
- inventory job/action;
- compensation;
- follow-up;
- analytics.

Si un efecto es obligatorio para consistencia, debe estar en la transacción, no en job.
