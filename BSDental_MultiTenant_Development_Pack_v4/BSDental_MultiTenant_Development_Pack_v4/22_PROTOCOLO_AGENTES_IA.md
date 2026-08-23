# 22 — Protocolo para Agentes IA

## Lectura mínima

1. MASTER.
2. PROJECT_STATUS.
3. DECISIONS.
4. tarea exacta en Plan.
5. documento del dominio.
6. invariantes.

## Antes de código

Ejecutar:
- git status;
- tests relevantes;
- localizar implementación existente;
- confirmar dependencia/tarea.

## Una tarea a la vez

No implementar la fase completa salvo instrucción explícita.

## Handoff

Al terminar:
- listar archivos;
- comandos ejecutados;
- tests;
- errores pendientes;
- actualizar PROJECT_STATUS;
- CHANGELOG;
- marcar solo la tarea real.

## Prohibido

- marcar tests como skipped para pasar;
- borrar test que falla;
- cambiar versión stack sin ADR;
- instalar paquete sin comprobar mantenimiento/licencia/compatibilidad;
- generar código duplicado porque no buscó el existente;
- esconder `any`;
- crear SQL no tenant-aware;
- “arreglar” una invariante cambiando el test.
