# 17 — Seguimiento, WhatsApp, CRM y Marketing

## Notification Engine

Canales:
- internal;
- email;
- WhatsApp;
- SMS futuro.

Agenda no llama directamente a Meta.

## WhatsApp

Adapter:
```text
WhatsAppProvider
```

Implementación futura:
- Meta WhatsApp Business Platform u otro BSP autorizado.

## Appointment reminders

Configuración inicial sugerida:
- 48h: pedir confirmación;
- 24h: solo si no confirmó;
- 2h: recordatorio final.

AppointmentRescheduled:
- cancelar reminders pendientes;
- generar nuevos.

AppointmentCancelled:
- cancelar reminders.

## Message lifecycle

```text
scheduled
queued
sent
delivered
read
failed
responded
cancelled
```

## Webhooks

- signature validation;
- idempotency;
- retry;
- provider message id;
- tenant mapping seguro.

## Follow-up

- post-op;
- no-show;
- quote pending;
- treatment incomplete;
- periodic control.

## CRM

Stages:
- lead;
- contacted;
- appointment;
- evaluated;
- quoted;
- accepted;
- treatment;
- recurrent;
- inactive.

## Marketing

- consent/preference;
- segments;
- campaigns;
- attribution.

No enviar PHI clínica en campañas.
