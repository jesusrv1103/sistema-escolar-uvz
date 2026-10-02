# Estado del proyecto — Sistema Escolar UVZ

> Actualizado al cierre de la **Fase 10** (02/10/2026). Fuente de verdad técnica: `docs/arquitectura-mvp.md` (v3). Orden de ejecución: `docs/plan-implementacion.md`.

> **`docs/reglas-desarrollo.md` es OBLIGATORIO para todas las fases futuras:** leerlo antes de trabajar en cualquier fase; ante contradicciones manda sobre el resto de los documentos (orden de prioridad en su §1).

## Fases

| Fase | Estado |
|---|---|
| Fase 1 — Base del proyecto | ✅ **COMPLETA** (app, login/logout, 11 pruebas, `npm run build`) |
| Fase 1.1 — Livewire 4 + identidad institucional | ✅ **COMPLETA** (15 pruebas, build correcto) |
| Fase 2 — Seguridad y autorización base | ✅ **COMPLETA** (26 pruebas, build correcto, `route:list` OK) |
| Fase 3 — Catálogos | ✅ **COMPLETA** (38 pruebas, build correcto, `route:list` OK) |
| Fase 4 — Expediente del alumno | ✅ **COMPLETA** (54 pruebas, build correcto, `route:list` OK) |
| Fase 5 — Núcleo del flujo de inscripción | ✅ **COMPLETA** (69 pruebas, build correcto, `route:list` OK) |
| Fase 6 — Wizard de inscripción | ✅ **COMPLETA** (85 pruebas, build correcto, 3 rutas nuevas) |
| Fase 7 — Convenios y generación de cargos | ✅ **COMPLETA** (113 pruebas, build correcto, sin rutas nuevas) |
| Fase 8 — Pagos | ✅ **COMPLETA** (153 pruebas, build correcto, 1 ruta nueva: comprobante privado) |
| Fase 9 — Comprobante de pago PDF | ✅ **COMPLETA** (163 pruebas, build correcto, 1 ruta nueva: `pagos.comprobante-pdf`) |
| Fase 10 — Documentos | ✅ **COMPLETA** (192 pruebas, build correcto, 2 rutas nuevas: `documentos.archivo`, `inscripciones.documentos`) |
| Fases 11–16 | ⬜ pendientes (esperan aprobación) |

## Fase 2 — seguridad y autorización (implementado)

- **Enums (10)** en `app/Enums/`: `RolUsuario`, `InscripcionEstatus`, `EstatusPago`, `EstatusDocumento`, `EstatusConvenio`, `TipoConcepto`, `TipoContacto`, `TipoConvenio`, `TipoObservacion`, `MetodoPago`.
- **Migración incremental** `2026_10_01_000003_add_seguridad_to_users_table`: `rol` (NOT NULL, backfill ADMIN), `activo`, `last_login_at`, `deleted_at` (soft deletes); la migración original de Laravel no se tocó.
- **User**: cast `rol => RolUsuario`, `activo => boolean`, `last_login_at => datetime`, `SoftDeletes`, `$fillable/$hidden` seguros (sin `$guarded = []`), helper `esAdmin()`.
- **Middleware** `EnsureUserHasRole` (alias `role` en `bootstrap/app.php`): rechaza no autenticados (redirect login), inactivos (403) y roles equivocados (403); ADMIN pasa en un único punto central.
- **Gates**: `AuthServiceProvider` con `Gate::before` (ADMIN pasa todo; inactivos no pasan nada) y el mapa único `CAPACIDADES` (fuente única, matriz §8.2) → 20 capacidades.
- **Policies (8)** en `app/Policies/`: Usuario, Alumno, InformacionMedica (RB-18 registrada desde ahora), Inscripcion, Convenio, Pago, Documento, Observacion — delegadoras de los gates; sin modelos futuros inventados (se resuelven por convención en sus fases).
- **AdminSeeder**: idempotente (`updateOrCreate`), credencial desde `ADMIN_EMAIL`/`ADMIN_PASSWORD` en `.env` (vacías en `.env.example`; sin secretos en `docs/`); `DatabaseSeeder` lo invoca.
- **Login**: rechaza `activo = false`, actualiza `last_login_at`, conserva rate limiting, regeneración de sesión y logout.
- **Pruebas nuevas**: `tests/Feature/AutorizacionTest.php` (8) + 3 en `tests/Feature/Auth/LoginTest.php`; `tests/Pest.php` aplica `RefreshDatabase` a `Feature` y `Unit`.

## Fase 3 — catálogos (implementado)

- **Ajuste previo de extensibilidad:** `programas.duracion_semestres` → **`duracion_periodos`** (`smallint unsigned`, **nullable**); mide la duración total en periodos académicos (no semestres). Sin `tipo_periodo`, planes de estudio, RVOE, modalidad ni campus. `docs/arquitectura-mvp.md` (§4.5, §4.6, §12.12, §23.4) y `docs/roadmap-sistema-escolar.md` actualizados.
- **Migraciones** `2026_10_01_000004`–`000007`: `programas`, `periodos` (con `cerrado_at`), `tipos_documento`, `convenios`; unique en `clave`, fechas `date`, soft deletes.
- **Modelos**: `Programa`, `Periodo`, `TipoDocumento` (`protected $table = 'tipos_documento'`), `Convenio` (`tipo` cast a `TipoConvenio`); fillables explícitos (sin `$guarded = []`).
- **Componentes Livewire 4** en `app/Livewire/Catalogos/`: base abstracta `CatalogoAdmin` (flujo crear/editar/guardar/alternar activo/eliminar + `Gate usuario.manage` en cada acción) y `Programas`, `Periodos` (acciones `cerrar`/`reabrir` sobre `cerrado_at`), `TiposDocumento`, `Convenios`; vistas en `resources/views/livewire/catalogos/`.
- **Rutas** `/admin/{programas,periodos,tipos-documento,convenios}` con `auth` + `role:ADMIN`; navegación mínima: enlace "Catálogos" en la cabecera y tarjetas en el dashboard, ambas tras `@can('usuario.manage')`.
- **Seeders idempotentes**: `CatalogosSeeder` (3 programas UVZ con claves internas `DER`, `SAI`, `CF`; `duracion_periodos = NULL` para no inventar datos; sin semilla de `periodos` por no existir fechas institucionales) y `TiposDocumentoSeeder` (solo `ACTA_NAC`, lo definido en arquitectura §4.11).
- **Validación server-side** (arquitectura §12.12): claves únicas y con formato, `fecha_fin >= fecha_inicio`, convenio `PORCENTAJE` 0 < v ≤ 100 / `MONTO` v > 0, campos obligatorios, autorización ADMIN.
- **Semántica documentada**: `cerrado_at` bloquea **solo** inscripciones (RB-13 / riesgo R2); la aprobación de un convenio no vive en el catálogo.

## Fase 4 — expediente del alumno (implementado)

- **Enums nuevos (valores aprobados por el usuario en Fase 4):** `Sexo` (`MASCULINO` | `FEMENINO`) y `TipoSangre` (`A+`, `A-`, `B+`, `B-`, `AB+`, `AB-`, `O+`, `O-`); registrados en arquitectura §4.2/§4.4.
- **Migraciones** `2026_10_01_000008`–`000010`: `alumnos` (`numero_control` char(8) **nullable/unique y NULL en toda la Fase 4**; CURP unique; soft deletes), `contactos` (FK cascade, sin soft delete) e `informacion_medicas` (`alumno_id` unique ⇒ 1:1, cascade, sin soft delete).
- **Modelos:** `Alumno` (constante `REGEX_CURP`, normalizadores de CURP/correo, `scopeBuscar`, relaciones contactos/informacionMedica/creadoPor, **sin `numero_control` en `$fillable`**), `Contacto`, `InformacionMedica` (`$table = informacion_medicas`). Sin `$guarded = []`.
- **Componentes Livewire 4:** `ListaAlumnos` (búsqueda con `debounce`, listado con `select` explícito y **sin datos médicos**), `FormAlumno` (alta/edición, campos deshabilitados en solo lectura), `FormContactos` (TUTOR y EMERGENCIA independientes, `updateOrCreate` por tipo) y `FormInfoMedica`.
- **Rutas:** `/alumnos`, `/alumnos/nuevo`, `/alumnos/{alumno}`, `/alumnos/{alumno}/contactos`, `/alumnos/{alumno}/info-medica` (todas con `auth`; la autorización fina vive en cada componente vía Policy/Gate).
- **Autorización médica (RB-18):** pantalla y acciones exigen `informacionMedica.edit` → ADMIN ✔, ADMISIONES ✔, **ADMINISTRACION ✖ (403)**, **CONTROL_ESCOLAR ✖ en Fase 4** (sin pantalla general: su lectura contextual se habilitará con la revisión final; conserva `informacionMedica.view`). `@can` solo complementa.
- **CURP:** validación sintáctica local (18 caracteres + estructura + unicidad local, normalización a mayúsculas), sin consultas RENAPO ni afirmación de existencia oficial; factories/pruebas usan CURPs ficticias `ZZZZ…`.
- **Privacidad:** la información médica no aparece en listados, búsquedas ni dashboard; `actualizado_por` registra quién la editó; sin datos reales en seeders/pruebas.

## Fase 5 — núcleo del flujo de inscripción (implementado)

- **Migraciones** `2026_10_01_000011`–`000012`: `inscripciones` (estados, montos snapshot, campos de convenio, hitos, responsables, **`UNIQUE(alumno_id, periodo_id)`**, índices `estatus` / `(periodo_id, estatus)` / `convenio_estatus`, soft deletes) y `auditoria` (morph, **sin `updated_at`**, sin soft delete, índice `(auditable_type, auditable_id)`).
- **Modelos:** `Inscripcion` (`$table` explícito; `estatus` y responsables **fuera de `$fillable`** — solo los escribe el servicio; casts enum/decimal/datetime; 9 `belongsTo` + `morphMany` auditoría) y `Auditoria` (append-only: `update()`/`delete()`/`forceDelete()`/`restore()` lanza `LogicException`).
- **Máquina de estados:** única matriz `InscripcionEstatus::TRANSICIONES` (19 aristas de §7.2); helpers `puedeIrA`, `esRetroceso`, `requiereMotivo` (retrocesos y toda cancelación exigen motivo); `ENROLLED`/`CANCELLED` terminales. Las guardas de pagos/documentos/NC **no se simulan**.
- **Servicios:** `InscripcionService` (único punto que modifica `estatus`: valida → `DB::transaction` → estatus + hitos/responsables → auditoría; excepciones explícitas `TransicionInvalidaException` / `MotivoRequeridoException`) y `AuditoriaService` (único punto de escritura de `auditoria`; ip/agent solo con contexto HTTP; payload depurado de passwords/tokens/datos médicos). Trait `HasAudit` (relación + acceso al servicio).
- **Hitos en la transición:** `DATA_COMPLETE`→`datos_completados_at`, `PAYMENT_VALIDATED`→`pagos_validados_at`, `FINAL_REVIEW`→`documentos_validados_at`, `ENROLLED`→`finalizada_at`+`validada_por`, `CANCELLED`→`cancelada_por/at/motivo` (una sola vez; dentro de la transacción).
- **Sin alcance:** sin Wizard, sin `conceptos_pago`, pagos, documentos, observaciones, consecutivos ni número de control.
- **Pruebas:** 15 nuevas — `MaquinaEstadosTest` (U1, U2, U3 terminal + retrocesos con motivo), `AuditoriaTest` (F21 con las 19 aristas, transición inválida sin rastro, rollback si falla auditoría, append-only, payload sin datos sensibles) e `InscripcionTest` (UNIQUE de BD, `estatus` no mas-asignable, casts, relaciones, soft delete, `InscripcionPolicy` conectada al modelo).

## Stack y entorno

- Laravel 12.69.3, PHP 8.2.34, Composer 2.8.5, Node 22.
- **barryvdh/laravel-dompdf 3.1.2** (dompdf 3.1.6) — dependencia de PDF aprobada en **P1**, instalada en la Fase 9.
- **Livewire 4.4.7** (actualizado desde `^3.0` en Fase 1.1; la Fase 1 no tenía componentes Livewire).
- Tailwind CSS 4 + Vite 7; Pest 3.8 (`composer test`).
- MySQL 8.0.46 en **Docker solo para desarrollo local**: contenedor `sistema-escolar-mysql`, `127.0.0.1:3308`, usuario y contraseña definidos en `.env` (no se documentan secretos en `docs/`); BD `sistema_escolar` (desarrollo) y `sistema_escolar_testing` (pruebas) **separadas**.
- **Docker no es dependencia de arquitectura**: el despliegue final será hosting compartido con el MySQL del hosting (arquitectura §23.6).
- Locale `es_MX`, timezone `America/Mexico_City`, disco de archivos `private` (`serve=false`).

## Identidad institucional (Fase 1.1)

- Marca: **Universidad Villa de Zacatecas (UVZ)** · "Aumentamos la excelencia" · `#AumentamosLaExcelencia` · <https://universidadvilladezacatecas.edu.mx/>
- Fuente única: **`config/branding.php`** (nombre, slogan, contacto, logo/favicon, colores). Las vistas usan `config('branding.*')`; los colores viajan de config → variables `--brand-*` en el layout → tokens `@theme` de Tailwind 4 (`resources/css/app.css`).
- Colores verificados en el `styles.css` del sitio UVZ: primario `#72132e`, secundario `#4f0a1e`, acento `#e6ad34`, fondo `#fbfaf7`, texto `#171517` (+ highlight/surface/muted/border; ver arquitectura §23.3).
- Assets locales: `public/images/logo-uvz-{horizontal,vertical}.jpeg|png`, `public/images/favicon.png`; fuentes DM Sans y Playfair Display auto-alojadas en `public/fonts/`. **Sin hotlinking.**
- Login, cabecera y dashboard personalizados con la identidad UVZ (sin menú de módulos ni métricas todavía).

## Validación al cierre de la Fase 2

- `composer test` → **26 pruebas / 149 assertions, todas verdes** (15 previas intactas + 11 nuevas de Fase 2).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → `/dashboard` y `/logout` con middleware `auth`; `/login` con `guest`; alias `role` registrado (sin uso aún: no hay rutas de módulo hasta la Fase 3+).

## Validación al cierre de la Fase 3

- `composer test` → **38 pruebas / 227 assertions, todas verdes** (26 previas intactas + 12 nuevas de Fase 3 en `tests/Feature/CatalogosTest.php`).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → rutas `/admin/programas`, `/admin/periodos`, `/admin/tipos-documento`, `/admin/convenios` con middleware `auth` + `role:ADMIN`.
- Migraciones 000004–000007 y seeders ejecutados en la BD de desarrollo; las pruebas corren **solo** contra `sistema_escolar_testing`.
- Revisión de seguridad previa: la BD de desarrollo tenía **1 usuario** (`admin@uvz.local`, rol ADMIN, activo) — sin cuentas inesperadas heredadas del backfill de rol.

## Validación al cierre de la Fase 4

- `composer test` → **54 pruebas / 304 assertions, todas verdes** (38 previas intactas + 16 nuevas de Fase 4 en `tests/Feature/AlumnoTest.php`: F1, F2, F3, F27, CONTROL_ESCOLAR sin pantalla médica, roles con lectura, 1:1, soft delete, `numero_control` NULL y listado sin datos médicos).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → 5 rutas nuevas bajo `/alumnos…` con `auth`; autorización verificada server-side.
- Migraciones 000008–000010 ejecutadas en la BD de desarrollo; las pruebas corren **solo** contra `sistema_escolar_testing`.

## Validación al cierre de la Fase 5

- `composer test` → **69 pruebas / 609 assertions, todas verdes** (54 previas intactas + 15 nuevas de Fase 5: `tests/Unit/MaquinaEstadosTest.php` U1–U3 y motivo de retrocesos; `tests/Feature/AuditoriaTest.php` F21, atomicidad y append-only; `tests/Feature/InscripcionTest.php` UNIQUE, casts, relaciones, soft delete y policy).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **24 rutas** (sin rutas nuevas: la Fase 5 es dominio + persistencia, sin pantallas).
- Migraciones 000011–000012 ejecutadas en la BD de desarrollo; las pruebas corren **solo** contra `sistema_escolar_testing`.

## Fase 6 — wizard de inscripción (implementado)

- **División aprobada de los 6 pasos** (los documentos solo fijan de forma inequívoca: paso 3 = "Datos de contacto" (tutor), paso 4 = "Contacto de emergencia", "pasos 2–6" = pantallas de alumno y "paso 6 validado → `datos_completados_at`"; la distribución completa fue propuesta y aprobada con el usuario): **1** selección y montos (crea el borrador `DRAFT`) · **2** datos personales · **3** contacto tutor · **4** contacto de emergencia · **5** información médica (solo estado del registro) · **6** revisión final → `DATA_COMPLETE`.
- **Componentes Livewire 4** en `app/Livewire/Inscripciones/`: `WizardInscripcion` (validación por paso, server-side, errores junto al campo) y `ListaInscripciones` (filtros `#[Url]`: búsqueda de alumno, periodo y estatus; máx. 50 filas; **sin información médica**). Vistas en `resources/views/livewire/inscripciones/`.
- **Rutas** `/inscripciones`, `/inscripciones/nueva`, `/inscripciones/{inscripcion}` (con `auth`; autorización fina vía `InscripcionPolicy`/gates en cada acción) + navegación mínima: enlace "Inscripciones" en la cabecera y tarjeta en el dashboard (sin rediseño global).
- **Borrador en BD, no en sesión (riesgo R8):** la creación del `DRAFT` pasa por `InscripcionService::crear()` (transacción + auditoría `inscripcion_creada`); al reabrir, el wizard se posiciona en el **primer paso incompleto** (reanudación derivada del estado real, sin columnas ni tablas nuevas de wizard); retroceder visualmente no borra datos ni duplica la inscripción.
- **Reutilización sin duplicar lógica:** alumno, contactos e información médica se capturan en los componentes de Fase 4 (el wizard enlaza a ellos); el wizard solo verifica **completitud por paso** (RB-2) contra BD. CURP única, alumnos no duplicados, `numero_control` sigue `NULL`.
- **Reglas aplicadas:** RB-13 (periodo con `cerrado_at`, inactivo o eliminado bloquea crear **y** modificar, con mensaje propio), RB-14 (duplicidad alumno+periodo validada antes de guardar + respaldo `UNIQUE` de BD), §12.4 (`required`, `numeric`, `min:0` y `decimal:0,2` — comportamiento aprobado con el usuario: acepta `1500` y `1500.50`, rechaza más de 2 decimales), solo programas/periodos activos y no eliminados, montos como **snapshots** (sin catálogo de tarifas, sin convenios ni descuentos: `monto_descuento` en 0).
- **Finalizar:** al completar los 6 pasos, `InscripcionService::transicionar(DATA_COMPLETE)` escribe `datos_completados_at` y la auditoría `estado_cambiado` en la misma transacción. **No** pasa a `PENDING_PAYMENT` (Fase 7).
- **Autorización:** ADMISIONES es el rol protagonista (`inscripcion.create`/`inscripcion.update`); ADMIN conserva el acceso global vía `Gate::before`; los demás roles abren el listado en lectura (matriz §8.2) y reciben **403** en acciones sensibles (server-side). Sin ampliar permisos médicos: ADMINISTRACION ✖, CONTROL_ESCOLAR sin pantalla médica; el wizard **nunca** carga ni muestra contenido médico (RB-18) y el listado tampoco.
- **Factories de prueba nuevas:** `ContactoFactory` e `InformacionMedicaFactory` (solo datos sintéticos) + `HasFactory` en `Contacto`/`InformacionMedica`; `InscripcionFactory::programaPrueba()/periodoPrueba()` ahora públicos para las pruebas.

## Validación al cierre de la Fase 6

- `composer test` → **85 pruebas / 756 assertions, todas verdes** (69 previas intactas + 16 nuevas: `WizardInscripcionTest` — F4, F5, creación `DRAFT` + snapshots, persistencia/reanudación/retroceso, programa inactivo, periodo inactivo, montos inválidos, transición final vía `InscripcionService` con auditoría, 403 de roles en wizard y acceso de lectura al listado, acciones sensibles server-side, listado sin información médica y filtros por alumno/periodo/estatus; `DuplicidadTest` — F22; `PeriodoTest` — F28).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **27 rutas** (24 previas + 3 nuevas: `inscripciones.lista`, `inscripciones.nueva`, `inscripciones.ver`).
- Pruebas ejecutadas **exclusivamente** contra `sistema_escolar_testing`.
- Ambigüedades detectadas y resueltas con el usuario **antes** de implementar: distribución de los 6 pasos (División A con enlaces al expediente Fase 4) y validación de montos (`decimal:0,2`).

## Fase 7 — convenios y generación de cargos (implementado)

- **Decisión UVZ registrada en arquitectura §4.9/RB-3/FC-4:** UVZ opera por **CUATRIMESTRES** → cada inscripción genera **1 concepto `INSCRIPCION` + exactamente 4 `MENSUALIDAD`** (los cuatro meses derivados de `periodos.fecha_inicio`: +0, +1, +2, +3), con vencimiento el **día 10** definido en **`config/escolar.php`** (`escolar.dia_vencimiento_mensualidad`, clamp al último día válido del mes; regla de negocio **sin** `.env` y sin hardcodeo en servicios/vistas); convenio/promoción **únicamente sobre `INSCRIPCION`**; mensualidades **sin descuento**; mensualidades pendientes **no bloquean** `ENROLLED`; `duracion_periodos` **se conserva**.
- **Migración** `2026_10_01_000013_create_conceptos_pago_table` (§4.9): FK `inscripcion_id` **cascade**, `tipo/numero/concepto/monto/descuento/monto_total/fecha_vencimiento/estatus/pagado_at`, **sin soft delete** (R15), **sin UNIQUE compuesto** (los genera el servicio en transacción). **Sin tabla `pagos`** (Fase 8).
- **Modelo/enum:** `ConceptoPago` (`$table = conceptos_pago`, casts enum/decimal/date, `belongsTo Inscripcion`) + relación `Inscripcion::conceptosPago()` (hasMany) + `ConceptoPagoFactory` (solo escenarios sintéticos); enum nuevo **`EstatusConceptoPago`** (`PENDIENTE|PAGADO|CANCELADO`) — no se reutiliza `EstatusPago` (semántica de `pagos`).
- **`ConvenioService`** (FC-3, punto único de autorización server-side vía gates `convenio.aplicar`/`convenio.aprobar`): `aplicar()` (ADMISIONES/ADMIN → `PENDIENTE_APROBACION`, `convenio_aplicado_por/at`, `monto_descuento` 0, exigencias: etapa previa a cargos, convenio activo/no eliminado/vigente, **máximo uno por inscripción**), `aprobar()` (ADMINISTRACION/ADMIN → `APROBADO`, `convenio_aprobado_por/at`, descuento congelado: `PORCENTAJE = monto × valor / 100`, `MONTO = min(valor, monto)`, redondeo a 2 decimales, nunca negativo ni mayor al monto) y `rechazar()` (`RECHAZADO`, motivo 5–2000 obligatorio, `monto_descuento = 0`); cada operación en `DB::transaction` + auditoría `convenio_aplicado/convenio_aprobado/convenio_rechazado` (RB-12).
- **`ModalAplicarConvenio`** (Livewire 4, hijo del wizard): lista solo convenios **activos, no eliminados y vigentes** con nombre/tipo/valor; muestra el estado (pendiente/aprobado/rechazado + descuento + motivo); acciones aplicar/aprobar/rechazar; lógica en `ConvenioService` (el componente solo autoriza lectura y traduce excepciones a errores); **sin información médica** (RB-18).
- **Four-eyes estricto (matriz §8.2):** ADMISIONES aplica y **no aprueba ni rechaza** (F24 = 403 server-side); ADMINISTRACION aprueba/rechaza y **no asume el papel de ADMISIONES**; ADMIN vía `Gate::before`.
- **`InscripcionService::generarCargos()`** (RB-3/FC-4, UNA transacción): valida estatus `DATA_COMPLETE` (fila con `lockForUpdate`), convenio resuelto (`PENDIENTE_APROBACION` ⇒ **bloqueo F23**), **idempotencia** (si ya hay cargos ⇒ rechazo explícito sin duplicar); crea 1 `INSCRIPCION` (vence en `periodos.fecha_inicio`; `descuento` solo si convenio `APROBADO`; `monto_total = max(monto − descuento, 0)`; **total 0 ⇒ nace `PAGADO` con `pagado_at`**) + 4 `MENSUALIDAD` (`numero` 1–4, concepto legible "Mensualidad N - mes YYYY", `descuento` 0, vencimientos el día configurado), transiciona a `PENDING_PAYMENT` (auditoría `estado_cambiado`) y registra `cargos_generados`; **cualquier fallo ⇒ ROLLBACK TOTAL** (probado con fallo real de FK, sin mocks). **No adelanta** el flujo posterior (Fase 8).
- **UI en el wizard:** acciones `generarCargos` (gate `inscripcion.generarCargos` = ADMISIONES + ADMIN) con errores junto al control, listado de cargos generados y tarjeta "Convenio y cargos"; sin rutas nuevas.
- **Pruebas nuevas (28):** `CalculoConvenioTest` (U4 porcentaje con redondeo, U5 monto limitado al monto de inscripción, U6 pendiente ⇒ descuento 0), `ConvenioTest` (F23 bloqueo + resolución, F24 403, ADMINISTRACION no aplica, inactivo/eliminado, vigencia, un solo convenio, etapa previa, snapshot inmutable, rechazo con motivo ⇒ descuento 0, auditoría con responsable, listado del modal solo vigentes, modal sin datos médicos) y `GeneracionCargosTest` (5 conceptos 1+4, vencimientos con día configurable y clamp, concepto legible, descuento solo en INSCRIPCION, rechazado sin descuento, $0 ⇒ PAGADO + mensualidades PENDIENTE, segunda generación sin duplicados, duplicado previo rechazado, ROLLBACK total real, autorización server-side, 2 integraciones completas).

## Validación al cierre de la Fase 7

- `composer test` → **113 pruebas / 917 assertions, todas verdes** (85 previas intactas + 28 nuevas de Fase 7).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **27 rutas** (sin rutas nuevas: el modal es componente hijo del wizard).
- Migración 000013 ejecutada en la BD de desarrollo; las pruebas corren **exclusivamente** contra `sistema_escolar_testing`.
- Ambigüedad resuelta sin inventar reglas: la arquitectura no fija fecha de vencimiento para el concepto `INSCRIPCION` → se usó `periodos.fecha_inicio` (solución mínima documentada en §4.9); no hubo contradicción con la máquina de estados ($0 nace `PAGADO` pero el flujo NO adelanta Fase 8).

## Fase 8 — pagos (implementado)

- **Decisiones complementarias registradas en arquitectura (§4.10/RB-4/RB-10/§10):** folio **`PAG-YYYYMMDD-########`** (ej. `PAG-20261001-00000127`) generado **solo al validar**, con la fecha/hora de **validación** en el timezone de la aplicación y el `pago.id` con padding a 8 dígitos; unicidad lógica por ID + `UNIQUE(folio)` como defensa final; **sin** tabla `consecutivos`, **sin** `MAX()`, **sin** reutilizar `NumeroControlService`; folio **inmutable** y un `RECHAZADO` **jamás** recibe folio. **Reintentos:** un concepto se liquida con UN solo pago completo (sin parciales/anticipos), admite **a lo sumo UN intento `PENDIENTE` activo** a la vez (`lockForUpdate()`) o UN `VALIDADO`, y conserva sus intentos `RECHAZADO` como **historial intacto** (sin `UNIQUE(concepto_pago_id)`; tras un rechazo se permite un nuevo intento).
- **Alcance aclarado:** `conceptos_pago` **NO se recreó** (ya existía desde la Fase 7); la única infraestructura nueva es la tabla **`pagos`**.
- **Migración** `2026_10_01_000014_create_pagos_table` (§4.10): `concepto_pago_id` (FK restrict), `monto decimal(10,2)`, `fecha_pago`, `metodo(20)`, `referencia(100)` nullable, `comprobante_ruta`/`comprobante_nombre_original` nullable, `estatus(20)` default `PENDIENTE`, `folio(30)` **unique** nullable, `validado_por`/`registrado_por` (FK a `users`), `validado_at`, `motivo_rechazo`, `notas`; **sin soft delete** (R15).
- **Modelo/relaciones:** `Pago` (`fillable` explícito **sin** estatus/folio/validado_*/motivo — los escribe el servicio; casts `MetodoPago`/`EstatusPago`/`decimal:2`/`date`/`datetime`; scopes `pendiente/validado/rechazado`; `belongsTo ConceptoPago`, `registradoPor`, `validadoPor`; `HasAudit`) + `ConceptoPago::pagos()` (hasMany con el historial) y accessor `saldoPendiente`; `PagoFactory` (solo escenarios).
- **`FolioPagoService`**: método único `generar(Pago)` con el formato exacto del folio (método pequeño y reutilizable, sin sobrearquitectura).
- **`PagoService`** (único punto de negocio; la UI no toca estatus/folio/estados):
  - `registrar()` — gates `pago.registrar` server-side (ADMIN ✔, ADMISIONES ✔, ADMINISTRACION ✔, CONTROL_ESCOLAR ✖); transacción con `lockForUpdate()` sobre concepto e intentos: rechaza concepto ≠ `PENDIENTE` (incluye el caso `$0` nacido `PAGADO`), concepto ya liquidado y segundo intento `PENDIENTE`; **permite** nuevo intento si solo hay `RECHAZADO`; valida monto > 0 **exactamente** = `monto_total`, fecha no futura, método válido y referencia obligatoria para `TRANSFERENCIA`/`TARJETA`; comprobante opcional validado con extensión + **MIME real (`finfo`)** + ≤ 10 MB, guardado en **disco `private`** con ruta UUID generada por el sistema y nombre original solo como metadato; **compensación**: si la transacción falla después de escribir el archivo, éste se elimina; efecto de proceso **solo `INSCRIPCION`** → `PAYMENT_REVIEW` vía `InscripcionService::transicionar()`; auditoría `pago_registrado`.
  - `validar()` — gates `pago.validar` (ADMINISTRACION + ADMIN); locks de pago y concepto, monto exacto, **folio**, pago `VALIDADO` + `validado_por/at`, concepto `PAGADO` + `pagado_at`, auditoría `pago_validado` y, **solo `INSCRIPCION`**, `PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` (dos transiciones vía máquina de estados; `pagos_validados_at`); **ROLLBACK TOTAL** ante cualquier fallo (probado con transición imposible y con FK real de usuario fantasma).
  - `rechazar()` — gates `pago.validar`; motivo obligatorio (no vacío ni solo espacios, ≤ 2000); pago `RECHAZADO` con `folio/validado_* = null` y motivo; auditoría `pago_rechazado`; **solo `INSCRIPCION`** → `PAYMENT_REVIEW → PENDING_PAYMENT` con el **motivo del rechazo** como motivo de la transición; `MENSUALIDAD` no toca la inscripción.
- **UI Livewire 4:** `ModalRegistrarPago` (conceptos `PENDIENTE` de la inscripción, fecha/método/referencia/notas/comprobante; **monto NO editable** — se toma del servidor) y `ModalValidarPago` (datos de alumno/programa/concepto/monto/método/referencia/fecha/comprobante, acciones validar/rechazar con motivo); integración mínima en la tarjeta de cargos del wizard; autorización server-side en cada acción (`@can` solo complementa); **sin información médica** (RB-18).
- **Comprobante cargado (evidencia privada):** 1 ruta nueva `GET /pagos/{pago}/comprobante` → `ComprobantePagoController` mínimo: `auth` + policy `descargarComprobante`, ruta resuelta **siempre desde BD**, verificación de contenido dentro del disco privado (path traversal) y 404 si no existe. **No** es el PDF institucional de Fase 9.
- **Pruebas nuevas (40):** `GeneracionFolioTest` (U9 formato con fecha de validación y timezone; U10 folios distintos, rechazo sin folio, inmutabilidad, `UNIQUE` de BD), `PagoTest` (F11 transacción completa con doble transición + `pagos_validados_at` + auditorías; F25 mensualidad conserva el estado EXACTO; monto exacto; roles — ADMISIONES/CONTROL_ESCOLAR no validan (servicio y componente 403) y ADMIN sí; pago ya `VALIDADO`/`RECHAZADO` no re-valida; ROLLBACK TOTAL por transición imposible y por FK real de responsable/auditoría), `RechazoPagoTest` (F12 completo con motivo en la transición de retroceso; motivo obligatorio/no vacío/≤2000; componente exige motivo; four-eyes del rechazo; nuevo intento tras rechazo con historial intacto; dos rechazos históricos; mensualidad rechazada no cambia el estado), `RegistroPagoTest` (CONTROL_ESCOLAR no registra, ADMISIONES/ADMINISTRACION sí; monto distinto/cero; fecha futura; referencia obligatoria en TRANSFERENCIA y TARJETA; método inválido; comprobante >10 MB, MIME inválido y extensión inválida rechazados; guardado en `private` con ruta interna y nombre original separado; **fallo de BD posterior elimina el archivo**; un solo intento `PENDIENTE`; `$0` no admite pago; `INSCRIPCION` exige `PENDING_PAYMENT`; `CANCELLED` no acepta pagos; comprobante requiere auth; policy + sin traversal + 404 sin archivo; modales sin datos médicos).

## Validación al cierre de la Fase 8

- `composer test` → **153 pruebas / 1134 assertions, todas verdes** (113 previas intactas + 40 nuevas de Fase 8).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **28 rutas** (27 previas + 1 nueva: `pagos.comprobante`).
- Migración 000014 ejecutada también en la BD de desarrollo; las pruebas corren **exclusivamente** contra `sistema_escolar_testing`.
- Sin mocks para servicios, estados, auditoría ni restricciones de BD: los rollbacks se probaron con fallos reales (FK de usuario fantasma y transición imposible).

## Fase 9 — comprobante de pago PDF (implementado)

- **Dependencia P1 aprobada instalada:** `barryvdh/laravel-dompdf` **3.1.2** (dompdf 3.1.6, compatible con Laravel 12 + PHP 8.2). Sin otros paquetes PDF.
- **`ComprobantePagoService`** (`app/Services/`): `datos(Pago)` verifica que el pago esté `VALIDADO` y con integridad mínima (folio, `validado_at`, `validado_por`, concepto → inscripción → alumno/programa, monto > 0) y prepara el array de la vista; `generar(Pago)` renderiza con DomPDF **bajo demanda**. Operación de lectura: **no** modifica `Pago`/`ConceptoPago`/`Inscripcion`, **no** escribe auditoría (la arquitectura no audita descargas) y **no** persiste el PDF en BD ni en disco. incumplir las garantías ⇒ `PagoInvalidoException`.
- **Vista** `resources/views/pdf/comprobante-pago.blade.php`: HTML independiente con CSS propio compatible con DomPDF (sin Tailwind/Vite); encabezado institucional (logo + "Universidad Villa de Zacatecas" + "Comprobante de pago" + folio), datos de identificación (folio, fechas de pago/validación, alumno, número de control —`NULL` ⇒ "Por asignar"—, programa, tipo), detalle (concepto, método, referencia si existe, monto en MXN) y pie con contacto institucional + "Comprobante administrativo de pago / No constituye factura fiscal". Sin QR, sello, CFDI ni datos fiscales inventados; **sin CURP, domicilio del alumno, información médica, contactos, notas ni motivo de rechazo**; todo dato dinámico escapado con `{{ }}` (sin `{!! !!}` sobre datos de BD).
- **Branding:** nombre, slogan, logo, colores (primario/secundario/acento/texto/tenue/borde/fondo) y contacto salen **únicamente** de `config('branding.*')` (§23.2/§23.5). El logo se resuelve como **data URI local** verificada dentro de `public/` (sin URLs públicas, sin internet y sin exponer rutas del servidor en el PDF); si no existe, el documento se genera sin logo.
- **Controlador y ruta:** `Pagos\ComprobantePdfController` invokable (nombre elegido por el usuario tras detectar la colisión con el controlador de evidencia de Fase 8, cuya ruta/semántica **no se tocó**): `auth` + `Gate::authorize('descargarComprobantePdf')`, delega al servicio y devuelve `download()` con nombre determinista `comprobante-<FOLIO>.pdf` (folio sanitizado, nunca el nombre del alumno); pago no `VALIDADO` ⇒ **404**. Ruta nueva `GET /pagos/{pago}/comprobante-pdf` (`pagos.comprobante-pdf`), separada de `/pagos/{pago}/comprobante` (evidencia de Fase 8).
- **Autorización:** `PagoPolicy::descargarComprobantePdf` delega en `view()` → los 4 roles con lectura del pago (ADMIN/ADMISIONES/ADMINISTRACION/CONTROL_ESCOLAR, matriz §8.2); sin URLs firmadas públicas; cuenta inactiva ⇒ 403 (RB-21).
- **Pruebas nuevas (10)** en `tests/Feature/ComprobantePagoTest.php`: **F26** (`test_comprobante_pdf_solo_para_pago_validado` cubierto por los tests `F26:` — VALIDADO ⇒ `application/pdf` + cabecera `%PDF-` + descarga por folio; PENDIENTE/RECHAZADO ⇒ 404 y excepción del servicio), los 4 roles descargan, `auth` obligatorio + inactivos 403, datos del comprobante (folio, monto, método MXN, branding y logo data URI), `referencia`/`numero_control` `NULL` sin romper el PDF, escapado de contenido y **ausencia de información médica**, PDF sin persistencia ni modificación de pago/auditoría, independencia de la ruta de evidencia de Fase 8 e integridad incompleta (VALIDADO sin folio ⇒ 404).

## Validación al cierre de la Fase 9

- `composer test` → **163 pruebas / 1203 assertions, todas verdes** (153 previas intactas + 10 nuevas de Fase 9).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **29 rutas** (28 previas + 1 nueva: `pagos.comprobante-pdf`).
- Pruebas ejecutadas **exclusivamente** contra `sistema_escolar_testing`.
- Sin nuevos artefactos en `storage` (el PDF solo viaja en la respuesta), sin cambios en `PagoService` ni en el módulo de la Fase 8.

## Fase 10 — documentos (implementado)

- **Interpretación aprobada de reemplazo (§12.8 vs RB-7/FC-6, registrada en arquitectura §12.8):** UN solo registro por `(inscripcion_id, tipo_documento_id)`; la primera carga **crea** el `Documento` y la recarga **actualiza ESA MISMA fila** (vuelve `PENDIENTE`, limpia `validado_por/validado_at/motivo_rechazo`, conserva `UNIQUE`); el reemplazo está permitido en las etapas del flujo de documentos (especialmente tras un `RECHAZADO`), la trazabilidad vive en `auditoria` (acciones `documento_subido` / `documento_reemplazado` con `estado_anterior`) y el archivo anterior se elimina **solo después del COMMIT**. Sin tabla de versiones.
- **Migración** `2026_10_01_000015_create_documentos_table` (§4.12): `inscripcion_id` (FK **cascade**), `tipo_documento_id` (FK **restrict**), `ruta`, `nombre_original`, `mime`, `tamano_kb` (≤ 10240), `estatus(20)` default `PENDIENTE`, `validado_por`/`subido_por` (FK a `users`), `validado_at`, `motivo_rechazo`, `timestamps`; **UNIQUE `(inscripcion_id, tipo_documento_id)`**; **sin soft delete**; sin observaciones (Fase 11).
- **Modelo/relaciones:** `Documento` (`fillable` explícito **sin** `estatus`/`validado_*`/`motivo_rechazo` — los escribe solo el servicio; cast `EstatusDocumento`; `belongsTo Inscripcion/TipoDocumento/subidoPor/validadoPor`) + `Inscripcion::documentos()` y `TipoDocumento::documentos()` (hasMany); `DocumentoPolicy` registrada en `AuthServiceProvider`.
- **`DocumentoService`** (único punto de negocio; la UI no escribe ruta, metadata, estatus ni `inscripciones.estatus`):
  - `subir()` — gates `documento.cargar` (los 4 roles; operativo ADMISIONES) + rate limiting 30 cargas/min; precondiciones: inscripción existente en `PENDING_DOCUMENTS`/`DOCUMENT_REVIEW`/`FINAL_REVIEW` (nunca `ENROLLED`/`CANCELLED` ni etapas previas al pago) y tipo existente/activo/no eliminado; archivo obligatorio PDF/JPG/JPEG/PNG ≤ 10 MB con **extensión + MIME real `finfo`**; disco **`private`** con nombre interno UUID (el nombre original solo es metadato); **upsert por clave lógica**; **patrón de compensación** (archivo nuevo antes de la transacción → fallo de BD ⇒ se borra el nuevo y el anterior jamás se toca → COMMIT ⇒ se retira el reemplazado); tras cada carga, si la inscripción está en `PENDING_DOCUMENTS` y **TODOS los obligatorios activos** están cargados ⇒ `DOCUMENT_REVIEW` **vía `InscripcionService::transicionar()`**.
  - `aceptar()` — gates `documento.validar` (CONTROL_ESCOLAR + ADMIN); documento `PENDIENTE` + inscripción en `DOCUMENT_REVIEW` + archivo presente en disco; `ACEPTADO` con `validado_por/at`; auditoría `documento_aceptado`; si **todos los obligatorios** quedan `ACEPTADO` ⇒ `FINAL_REVIEW` vía `transicionar()` (escribe `documentos_validados_at`); los **opcionales no bloquean**.
  - `rechazar()` — gates `documento.validar`; motivo `required` 5–2000; `RECHAZADO` conservando el archivo (evidencia) + auditoría `documento_rechazado`; si la inscripción está en `DOCUMENT_REVIEW` ⇒ retroceso a `PENDING_DOCUMENTS` **con el motivo como motivo explícito**; después ADMISIONES puede reemplazarlo.
- **UI:** `PanelDocumentos` (Livewire, ruta `GET /inscripciones/{inscripcion}/documentos` → `inscripciones.documentos`, enlazado desde el wizard y el listado): tipo, obligatorio/opcional, estatus, nombre original, fecha/responsable de carga, motivo de rechazo, descargar y acciones subir/reemplazar/aceptar/rechazar **según permisos server-side**; muestra el **histórico** de tipos desactivados/eliminados (§20); **sin información médica** (RB-18) y sin dashboard nuevo.
- **Descarga privada:** `Documentos\DescargaArchivosController` (1 ruta nueva `GET /documentos/{documento}/archivo` → `documentos.archivo`): `auth` + `DocumentoPolicy::view` (4 roles), ruta resuelta **solo desde BD**, verificación de pertenencia real al disco `private` (anti path traversal), 404 si no existe y nombre de descarga sanitizado desde el nombre original. **No** toca `/pagos/{pago}/comprobante{,-pdf}`.
- **Pruebas nuevas (29)** en `tests/Feature/DocumentoTest.php`: **F13** carga completa ⇒ `DOCUMENT_REVIEW` con archivos/metadata/auditoría; **F14** obligatorio faltante bloquea (`PENDING_DOCUMENTS` y sin `FINAL_REVIEW`) + un obligatorio no `ACEPTADO` no cierra la revisión; **F15** rechazo con motivo ⇒ `RECHAZADO` + retroceso + archivo conservado (motivo vacío/corto rechazado); los 4 formatos con su MIME real; extensión prohibida / MIME falso / >10 MB; disco `private` con ruta UUID y nombre original no usado como path; misma fila en reemplazo + limpieza de `RECHAZADO` + borrado del archivo viejo tras COMMIT; **compensación real** (FK de usuario fantasma: sin filas ni huérfanos al subir; al reemplazar se conserva el archivo viejo y se elimina el nuevo); tipos inactivos/eliminados sin carga e histórico visible; opcional no bloquea (`documentos_validados_at` al cerrar); 4 roles cargan; ADMISIONES/ADMINISTRACION 403 en aceptar/rechazar (servicio y panel); CONTROL_ESCOLAR/ADMIN validan; panel (recibe archivo, estados/motivo, sin carga fuera de etapas, sin datos médicos); descarga por los 4 roles con nombre original, auth/inactivo, traversal/inexistente, independencia de rutas de pagos; ciclo completo con auditoría.

## Validación al cierre de la Fase 10

- `composer test` → **192 pruebas / 1378 assertions, todas verdes** (163 previas intactas + 29 nuevas de Fase 10).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **31 rutas** (29 previas + 2 nuevas: `documentos.archivo`, `inscripciones.documentos`).
- Migración 000015 ejecutada también en la BD de desarrollo; las pruebas corren **exclusivamente** contra `sistema_escolar_testing`.
- Sin mocks: los rollback/compensación se probaron con **fallo real de FK** (usuario fantasma) sobre `Storage::fake('private')`, con servicios, policies y transiciones reales; sin escritura en `public/` ni binarios en BD.
- NO se crearon: observaciones, consecutivos, número de control, `ENROLLED`, cancelación, dashboard ni versionado documental.

## Pendientes / registrados

- **Fase 11 no iniciar** hasta aprobación del usuario.
- **`docs/reglas-desarrollo.md`**: documento permanente de reglas (fuentes de verdad, alcance, stack, seguridad, BD, Git, cierre de fase, pruebas, reglas críticas, branding, cambios no previstos) — obligatorio en todas las fases futuras.
- Cuenta ADMIN de desarrollo: `ADMIN_EMAIL`/`ADMIN_PASSWORD` en `.env` (valores no versionados ni documentados; `.env.example` solo con las claves vacías; **`DB_PASSWORD` también vacía en `.env.example` y `phpunit.xml` — credenciales solo en `.env`**).
- Policies de modelos de fases futuras: las 8 clases ya existen; Laravel las asocia por convención cuando los modelos se creen (sin código artificial).
- Logo variante para fondos oscuros: **PENDIENTE** (el sitio no la publica; `branding.logo_dark` reutiliza el horizontal).
- `duracion_periodos`, `periodos` iniciales y más tipos de documento: **PENDIENTES de fuente institucional** (se capturan/seedan cuando UVZ confirme datos; nada inventado).
- Lectura **contextual** de información médica para CONTROL_ESCOLAR: **PENDIENTE** del flujo de revisión final (Fase futura; en Fase 4 no existe pantalla general).
- Guardas de negocio pendientes en la máquina de estados (observaciones, número de control): **PENDIENTES** de sus fases; en Fase 5 **no se simularon** con datos ficticios (§7.3.5). Las guardas de **pagos `INSCRIPCION`** y de **documentos obligatorios** quedaron implementadas en las Fases 8 y 10.
- Comprobante PDF con branding UVZ → **Fase 9 ✅ COMPLETA** (bajo demanda, solo pagos `VALIDADO`).
- Documentos: recepción/reemplazo/validación con `DocumentoService`, `PanelDocumentos` y descarga privada → **Fase 10 ✅ COMPLETA** (UNIQUE por tipo, compensación de archivos, F13–F15).
- Git local activo: `master` acumula las fases aprobadas (merges `--ff-only` de Fase 5 en `b8965e4`→`2d19d0d`, de Fase 6 en `2d19d0d`→`fe6c977`, de Fase 7 en `fe6c977`→`d8337f0`, de Fase 8 en `d8337f0`→`87bb211` y de Fase 9 en `87bb211`→`2f13e84`) y el trabajo por fase se hace en ramas `feat/fase-*` (actualmente `feat/fase-10-documentos`, **sin merge a master**; se conservan `feat/fase-9-comprobante-pdf`, `feat/fase-8-pagos`, `feat/fase-7-convenios-cargos`, `feat/fase-6-wizard-inscripcion`, `feat/fase-5-nucleo-inscripcion` y `feat/fase-4-expediente-alumno`); sin remotos ni push.
