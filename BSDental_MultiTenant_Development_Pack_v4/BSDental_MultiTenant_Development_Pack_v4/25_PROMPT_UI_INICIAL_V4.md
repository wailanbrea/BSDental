# 25 — Prompt UI Inicial BSDental v4

Actúa como Senior Product Designer + Senior Vue Developer.

Lee:
- `00_MASTER_BSDENTAL_V4.md`
- `02_STACK_VERSIONES_2026_08.md`
- `04_SEGURIDAD_ASVS_DEVSECOPS.md`
- documentos funcionales.

Stack obligatorio:
- Vue 3.5 stable;
- TypeScript 5.9 strict;
- Inertia 3;
- Vite 8.1;
- Tailwind 4.3;
- Lucide;
- FullCalendar solo Agenda;
- ECharts solo Analytics.

Objetivo:
crear interfaz clínica premium, rápida, accessible y coherente.

No crear:
- lógica clínica fake;
- cálculos financieros en Vue;
- datasets gigantes;
- módulos mezclados;
- imports globales de librerías pesadas.

Pantallas:
- login/2FA;
- dashboard;
- patients;
- patient 360;
- agenda;
- clinical;
- odontogram;
- consents;
- quotes;
- treatment plan;
- lab;
- inventory;
- billing;
- cash;
- finance;
- professionals;
- follow-up;
- analytics;
- settings;
- Platform Admin separado.

Performance:
- lazy page chunks;
- skeletons;
- deferred props;
- server pagination;
- prefetch solo navegación predecible.

Security UI:
- no confiar en hidden buttons;
- history protection;
- no sensitive values en localStorage;
- logout limpia client state.

Validar:
- 360/390/430/768/1024/1440;
- keyboard;
- contrast;
- no horizontal overflow;
- typecheck;
- lint;
- tests;
- build.
