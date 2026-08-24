# Baseline de integracion - 2026-08-24

## Clasificacion

- Producto: BSDental v4.
- Madurez: alpha avanzada.
- Fase: integracion y estabilizacion previa a Release 1.0.
- Lote actual: funcionalmente integrado y con todos los quality gates automatizados aprobados.
- Alcance del resguardo: 25 bases SQLite, 13 archivos no rastreados y un parche binario del arbol de trabajo.

## Etapa 0 - Resguardo y clasificacion

Estado: completada.

Respaldo externo:

`C:\xampp\php\www\BSDental_BACKUPS\STAGE_0_2026-08-24_001`

Evidencia preservada:

- Copia completa de 25 bases SQLite.
- Hash SHA-256 verificado para cada base.
- Copia y verificacion de 13 archivos no rastreados.
- `working-tree.patch` de 520320 bytes.
- Estado Git, historial, estadistica del diff y listado de archivos no rastreados.

Conclusion de la etapa:

- Existe un punto de recuperacion externo y verificable.
- El lote se conserva como una unidad de integracion; no debe dividirse ni revertirse sin revisar dependencias entre backend, frontend, migraciones y pruebas.
- No se creo commit, tag ni publicacion remota.

## Etapa 1 - Aislamiento de PHPUnit

Estado: completada y verificada.

Controles implementados:

- La conexion landlord de PHPUnit permanece en `:memory:` y `Tests\TestCase` rechaza otra configuracion durante testing.
- Cada prueba obtiene un directorio tenant aleatorio bajo `%TEMP%\bsdental-tests`.
- Las rutas tenant persistentes de las pruebas fueron reemplazadas por `tenantDatabasePath()`.
- `DatabaseSeeder` exige una ruta tenant aislada durante testing y bloquea `database\tenant_demo.sqlite`.
- `TenantProvisioningPipeline` usa el directorio temporal configurado cuando una prueba aprovisiona un tenant sin ruta explicita.
- `GatePlatformTest` comprueba la ruta temporal exacta del tenant aprovisionado.

Evidencia de validacion:

- Pruebas dirigidas: 16 aprobadas, 150 aserciones.
- Regresion PHP completa: 105 aprobadas, 1311 aserciones.
- Bases protegidas comparadas despues de la regresion: 25.
- Diferencias SHA-256 contra el respaldo: 0.
- Pint sobre los archivos de la etapa: aprobado.
- PHPStan sobre los archivos de la etapa: aprobado sin errores.
- TypeScript: aprobado.
- Build Vite de produccion: aprobado.

## Etapa 2 - Saneamiento de quality gates

Estado: completada y verificada.

Resultados:

- Pint: aprobado sin archivos pendientes.
- PHPStan: aprobado sin errores.
- ESLint: aprobado con 0 errores y 0 advertencias.
- TypeScript: aprobado.
- Build Vite de produccion: aprobado.
- Regresion PHP: 105 pruebas y 1311 aserciones aprobadas.
- Bases protegidas: 25 verificadas, 0 diferencias SHA-256.

## Decision

Las etapas 0, 1 y 2 quedan cerradas. El lote esta listo para continuar con validacion funcional o preparacion de release.

El respaldo externo debe conservarse y los hashes deben repetirse despues de cualquier prueba que use tenancy.

