# Baseline de integracion - 2026-08-24

## Clasificacion

- Producto: BSDental v4.
- Madurez: alpha avanzada.
- Fase: integracion y estabilizacion previa a Release 1.0.
- Lote actual: funcionalmente integrado, pero todavia no apto para release ni para un commit global mientras existan hallazgos de formato y analisis estatico.
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

## Deuda clasificada del lote

Estos hallazgos no pertenecen al aislamiento de bases, pero bloquean declarar el lote completo listo para release:

- Pint: 11 archivos requieren formato.
- PHPStan: 15 hallazgos en controladores y servicios ajenos al aislamiento.
- ESLint: 0 errores y 96 advertencias; el comando permite advertencias y completo el build.

## Decision

Las etapas 0 y 1 quedan cerradas. El lote amplio permanece en integracion y no esta listo para release.

El siguiente trabajo debe comenzar como una etapa nueva de saneamiento del lote, mantener el respaldo anterior y repetir los hashes de las bases despues de cualquier prueba que use tenancy.

