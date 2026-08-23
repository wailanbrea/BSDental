# 13 — Historia, Odontograma y Consentimientos

## Historia

- antecedentes;
- alergias;
- medicamentos;
- enfermedades;
- signos vitales;
- evaluaciones;
- diagnósticos;
- evoluciones;
- prescripciones;
- adjuntos.

## Alertas

Alergias/condiciones importantes visibles al profesional autorizado.

No exponerlas en roles administrativos innecesariamente.

## Files

- fotos intraorales;
- fotos extraorales;
- radiografías;
- documentos.

Storage privado.

## Odontograma

Fuente de verdad estructurada.
No JSON monolítico como única fuente.

Diferenciar:
- condición;
- diagnóstico;
- planificado;
- aprobado;
- realizado.

## Consentimientos

```text
template
→ template_version
→ consent
→ signature
→ final snapshot/PDF
```

Consentimiento firmado:
- inmutable;
- hash opcional;
- timestamp;
- template version;
- signer context;
- audit.

Validez legal debe revisarse según jurisdicción.
