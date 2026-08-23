# 18 — PROMPT PARA GENERAR LAS PANTALLAS INICIALES DE BSDental

## Uso

Entregar este prompt a la IA que creará el frontend visual/prototipo inicial. La IA debe leer también:

1. `00_MASTER_BSDENTAL.md`
2. `04_UI_UX_DESIGN_SYSTEM.md`

---

# PROMPT

Actúa como **Senior Product Designer + Senior Frontend Developer especializado en sistemas clínicos, ERP y aplicaciones Vue**.

Vas a diseñar e implementar la **base visual inicial de BSDental**, un producto odontológico modular single-tenant de BSolutions.

## 1. Contexto del producto

BSDental NO es una landing page.

Es una aplicación web profesional para la operación diaria de clínicas odontológicas.

El flujo principal del producto es:

```text
Paciente
→ Cita
→ Consulta
→ Historia clínica / Odontograma
→ Diagnóstico
→ Presupuesto
→ Aprobación
→ Plan de tratamiento
→ Procedimientos
→ Cobros
→ Seguimiento
→ Indicadores
```

Debe sentirse rápido, clínico, moderno, premium y funcional.

NO debe verse como:
- una plantilla genérica de administración,
- un dashboard lleno de tarjetas sin jerarquía,
- una app infantil,
- una web con exceso de gradientes,
- un diseño con dientes decorativos por todas partes.

## 2. Stack obligatorio

Usar el stack ya instalado en el repositorio. Objetivo:

```text
Laravel 13
Vue 3
TypeScript
Inertia.js 2
Tailwind CSS 4
shadcn-vue / Reka UI cuando corresponda
Lucide Vue Next
FullCalendar
Apache ECharts
```

No cambiar el stack.

No instalar un framework visual adicional completo.

Antes de programar:
1. inspecciona `package.json`,
2. inspecciona componentes existentes,
3. reutiliza lo que ya exista,
4. no dupliques primitives.

## 3. Identidad visual

Usar como base:

```text
Background:       #F8FAFC
Surface:          #FFFFFF
Surface muted:    #F1F5F9
Text:             #0F172A
Text muted:       #64748B
Border:           #E2E8F0

Primary:          #0F766E
Primary hover:    #115E59
Primary soft:     #CCFBF1

Info:             #2563EB
Success:          #16A34A
Warning:          #D97706
Danger:           #DC2626
```

Crear tokens/clases semánticas. No repetir colores hardcodeados por todo el proyecto.

Tipografía:
- Inter si está disponible.
- Sans-serif profesional en fallback.
- Excelente legibilidad.
- Tabular numbers para métricas/finanzas donde sea posible.

Bordes:
- 1px discretos.
- Radius medio, aproximadamente 8–12px.
- Sombras muy suaves.
- No tarjetas flotantes gigantes.

## 4. Layout principal

Crear un `AppLayout` reutilizable.

### Sidebar izquierda

Ancho aproximado:
- 260px expandida.
- versión colapsada para pantallas medianas.

Header:
- logo BSDental provisional tipográfico si no existe logo oficial.
- texto `BSDental`.
- etiqueta pequeña con versión/edición solo si visualmente funciona.

Navegación agrupada:

```text
GENERAL
- Dashboard

OPERACIÓN
- Pacientes
- Agenda

CLÍNICA
- Historia clínica
- Odontograma
- Presupuestos
- Tratamientos

ADMINISTRACIÓN
- Cobros
- Finanzas
- Inventario

RELACIÓN
- Seguimiento
- Marketing

ANÁLISIS
- Indicadores
- Reportes

SISTEMA
- Configuración
```

No mostrar módulos deshabilitados cuando luego se conecte Module Registry.

Usar Lucide icons coherentes.

### Topbar

Incluir:
- breadcrumb/contexto,
- selector de sucursal,
- botón/área de notificaciones,
- menú de usuario,
- search global visual preparado, sin inventar backend.

Evitar una topbar demasiado alta.

## 5. Componentes base a crear/reutilizar

Crear componentes reutilizables, no HTML diferente en cada pantalla:

```text
PageHeader
PageActions
StatCard
DataTable
DataToolbar
StatusBadge
EmptyState
FilterBar
SearchInput
PatientAvatar
Money
DateDisplay
SectionCard
ConfirmDialog
```

Solo crear los que se utilicen realmente en las pantallas iniciales.

## 6. Pantallas iniciales que debes implementar

### 6.1 Login

Diseño:
- limpio,
- profesional,
- sin ilustración genérica enorme,
- marca BSDental,
- correo,
- contraseña,
- recordar,
- olvidé contraseña,
- botón iniciar sesión.

Desktop: card/panel centrado o split muy sobrio.
Móvil: formulario completo y cómodo.

### 6.2 Dashboard

Título:
`Buenos días, [Nombre]`

Subtítulo:
`Resumen de la clínica · [fecha]`

Filtro:
- período,
- sucursal.

Primera fila de KPIs:
- Citas hoy
- Pacientes atendidos
- Cobrado hoy
- Pendiente por cobrar

Segunda zona:
- gráfica de ingresos/cobros últimos meses,
- agenda de hoy compacta.

Tercera:
- conversión de presupuestos,
- tratamientos pendientes,
- alertas/acciones.

Agregar:
- `Ver detalles` donde tenga sentido.
- estados de tendencia sin exagerar.

Usar datos mock tipados centralizados, NO escribir arrays arbitrarios dentro de cada componente.

### 6.3 Pacientes — Lista

Header:
`Pacientes`
Botón:
`Nuevo paciente`

Toolbar:
- Buscar por nombre/código/teléfono/documento.
- Estado.
- Última visita.
- Tags.
- Más filtros.

Tabla:
- Paciente + avatar/iniciales
- Código
- Teléfono
- Última visita
- Próxima cita
- Balance
- Estado
- Acciones

Añadir:
- paginación,
- empty state,
- loading skeleton.

### 6.4 Paciente — Perfil 360

Header del paciente:

```text
[Avatar] Juan Pérez                 [Activo]
         P-001245
         38 años · (809) 555-0123

[Agendar cita] [Nuevo presupuesto] [Más]
```

Tabs:

```text
Resumen
Historia clínica
Odontograma
Citas
Tratamientos
Presupuestos
Pagos
Documentos
Seguimiento
Actividad
```

`Resumen`:
- alertas clínicas destacadas con discreción,
- próxima cita,
- tratamiento activo,
- balance,
- últimos eventos,
- datos de contacto.

No mostrar información clínica sensible a roles sin permiso cuando se conecte el backend.

### 6.5 Agenda

Usar FullCalendar.

Toolbar:
- Hoy
- anterior/siguiente
- Día/Semana/Mes
- sucursal
- profesional
- consultorio

Cards de citas:
- hora,
- paciente,
- tipo,
- profesional,
- estado.

Estados visuales:
- programada,
- confirmada,
- llegó,
- en consulta,
- completada,
- cancelada,
- no-show.

Al seleccionar cita:
- abrir drawer lateral con resumen,
- acciones principales.

Crear botón `Nueva cita`.

### 6.6 Historia clínica

Dentro del paciente.

Diseño en dos áreas:
- navegación/resumen lateral o superior,
- contenido principal.

Secciones:
- Alertas
- Antecedentes
- Alergias
- Medicamentos
- Últimas evoluciones
- Encuentros

Botón:
`Nueva evolución`

Las alertas clínicas importantes deben ser visibles sin utilizar rojo para todo.

### 6.7 Odontograma

Crear una **primera representación funcional/prototipo**.

Debe incluir:
- arcada superior,
- arcada inferior,
- números de dientes,
- piezas seleccionables,
- leyenda,
- panel derecho/contextual.

Panel:
- pieza,
- estado,
- superficies,
- diagnóstico,
- tratamiento sugerido/planificado,
- historial breve.

NO hacer un dibujo decorativo. La estructura debe poder evolucionar al odontograma real.

### 6.8 Presupuestos

Lista:
- número,
- paciente,
- fecha,
- total,
- aprobado,
- estado,
- profesional.

Página detalle:
- paciente,
- items,
- pieza,
- procedimiento,
- cantidad,
- precio,
- descuento,
- subtotal,
- total,
- estado.

Acciones:
- Editar borrador
- Presentar
- Aprobar
- Exportar PDF

### 6.9 Tratamientos

Vista de plan:
- progreso total,
- fases,
- procedimientos,
- estado,
- profesional,
- próxima cita,
- importe relacionado.

Fases tipo timeline/accordion sobrio.

### 6.10 Cobros

Pantalla de paciente:
- saldo,
- cargos,
- pagos,
- balance.

Botón:
`Registrar pago`

Modal/drawer:
- monto,
- método,
- referencia,
- distribución,
- nota.

Dashboard caja:
- abierta/cerrada,
- recibido hoy,
- movimientos.

### 6.11 Finanzas

KPIs:
- producción,
- cobrado,
- gastos,
- cuentas por cobrar.

Gráficas:
- tendencia mensual,
- ingresos vs gastos.

Tabla:
- movimientos recientes.

No implementar contabilidad fiscal inventada.

### 6.12 Inventario

KPIs:
- stock bajo,
- próximos a vencer,
- valor estimado de inventario,
- movimientos hoy.

Tabla:
- producto,
- SKU,
- categoría,
- stock,
- unidad,
- vencimiento próximo,
- almacén,
- estado.

### 6.13 Seguimiento

Vista tipo lista de trabajo:
- vencidos,
- hoy,
- próximos.

Cada card/fila:
- paciente,
- motivo,
- responsable,
- fecha,
- prioridad,
- último intento,
- acción `Registrar contacto`.

### 6.14 Marketing

Diseño inicial:
- campañas,
- segmentos,
- plantillas.

KPIs moderados:
- campañas activas,
- pacientes objetivo,
- citas atribuidas.

No construir editor visual complejo todavía.

### 6.15 Indicadores

Dashboard mensual:
- selector período,
- sucursal,
- profesional.

Secciones:
- Pacientes
- Agenda
- Presupuestos
- Tratamientos
- Finanzas
- Profesionales

Usar ECharts con gráficos sencillos.

Cada gráfico debe tener:
- título,
- contexto,
- período,
- tooltip,
- no-data state.

### 6.16 Configuración

Página con navegación secundaria:

```text
Clínica
Sucursales
Usuarios
Profesionales
Especialidades
Catálogo
Métodos de pago
Módulos
Notificaciones
Sistema
```

No crear un formulario de 200 campos.

## 7. Estados UI obligatorios

Para listas/pantallas principales implementar visualmente:

- loading,
- empty,
- error,
- populated.

Crear datos mock realistas en español, pero claramente de demostración.

No utilizar lorem ipsum.

Ejemplos:
- Juan Pérez
- María Rodríguez
- Clínica Principal
- Dra. Laura Gómez

No usar información real.

## 8. Responsive

Desktop-first.

### >=1280
Sidebar fija.

### 768–1279
Sidebar colapsable.

### <768
Drawer navigation.
Ocultar columnas secundarias.
Acciones accesibles.
No permitir overflow horizontal innecesario.

Agenda y odontograma pueden requerir una experiencia móvil específica; no comprimirlos ilegiblemente.

## 9. Calidad de código

- Vue `<script setup lang="ts">`.
- TypeScript estricto.
- Props/emits tipados.
- No usar `any` salvo caso excepcional documentado.
- Datos mock tipados.
- Componentes pequeños.
- No duplicar markup.
- Composables para comportamiento reutilizable.
- No poner lógica de negocio clínica en componentes.
- Mantener pages organizadas por módulo.

## 10. Restricción de esta tarea

En esta primera ejecución, el objetivo es:

**crear el shell visual, sistema de diseño y pantallas iniciales coherentes.**

No implementar aún:
- toda la base de datos,
- lógica clínica completa,
- pasarelas,
- WhatsApp real,
- cálculos financieros finales,
- permisos finales.

Si el backend ya contiene funcionalidad, NO romperla. Integrar gradualmente.

## 11. Resultado esperado

Al iniciar BSDental debe ser posible navegar por un prototipo coherente de las principales áreas y entender inmediatamente:

- cómo se verá el producto,
- cómo se navega,
- cómo se presenta un paciente,
- cómo se operará la agenda,
- cómo se visualizará la clínica,
- cómo se mostrarán cobros e indicadores.

La interfaz debe parecer parte del mismo producto en todas las páginas.

## 12. Validación antes de terminar

Ejecuta:

```text
typecheck
lint
tests existentes
build
```

Corrige errores.

Después:
1. actualiza `PROJECT_STATUS.md`,
2. marca las tareas correspondientes,
3. actualiza `CHANGELOG.md`,
4. describe brevemente los componentes creados,
5. no marques como funcional aquello que sea solo mock/prototipo.

# FIN DEL PROMPT
