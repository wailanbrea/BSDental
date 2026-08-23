# 12 — Pacientes y Agenda

## Paciente

Funciones:
- create/edit;
- code;
- contactos;
- documentos;
- tags;
- origen;
- posibles duplicados;
- búsqueda;
- perfil 360.

Búsqueda server-side:
- nombre;
- código;
- teléfono;
- documento.

Nunca descargar todos los pacientes para filtrar en Vue.

## Perfil 360

Tabs:
- Resumen
- Historia
- Odontograma
- Citas
- Tratamientos
- Presupuestos
- Pagos
- Documentos
- Consentimientos
- Seguimiento
- Actividad

Cada tab respeta permisos.

## Agenda

Vistas:
- day;
- week;
- month;
- professional;
- room.

Estados:
- scheduled
- confirmed
- checked_in
- waiting
- in_progress
- completed
- cancelled
- no_show
- rescheduled

## Conflictos

Validar server-side:
- professional overlap;
- room overlap;
- blocking schedule.

Usar transacción/constraint/lock apropiado para evitar dos reservas simultáneas.

## Reschedule

- conserva historial;
- cancela reminders pendientes;
- programa nuevos reminders.

## FullCalendar

Lazy-load únicamente en Agenda.
No empaquetarlo globalmente.
