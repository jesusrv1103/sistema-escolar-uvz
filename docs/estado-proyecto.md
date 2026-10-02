# Estado del proyecto — Sistema Escolar UVZ

> Actualizado al cierre de la **Fase 5** (01/10/2026). Fuente de verdad técnica: `docs/arquitectura-mvp.md` (v3). Orden de ejecución: `docs/plan-implementacion.md`.

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
| Fases 6–16 | ⬜ pendientes (esperan aprobación) |

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

## Pendientes / registrados

- **Fase 5 no iniciar** hasta aprobación del usuario.
- **`docs/reglas-desarrollo.md`**: documento permanente de reglas (fuentes de verdad, alcance, stack, seguridad, BD, Git, cierre de fase, pruebas, reglas críticas, branding, cambios no previstos) — obligatorio en todas las fases futuras.
- Cuenta ADMIN de desarrollo: `ADMIN_EMAIL`/`ADMIN_PASSWORD` en `.env` (valores no versionados ni documentados; `.env.example` solo con las claves vacías; **`DB_PASSWORD` también vacía en `.env.example` y `phpunit.xml` — credenciales solo en `.env`**).
- Policies de modelos de fases futuras: las 8 clases ya existen; Laravel las asocia por convención cuando los modelos se creen (sin código artificial).
- Logo variante para fondos oscuros: **PENDIENTE** (el sitio no la publica; `branding.logo_dark` reutiliza el horizontal).
- `duracion_periodos`, `periodos` iniciales y más tipos de documento: **PENDIENTES de fuente institucional** (se capturan/seedan cuando UVZ confirme datos; nada inventado).
- Lectura **contextual** de información médica para CONTROL_ESCOLAR: **PENDIENTE** del flujo de revisión final (Fase futura; en Fase 4 no existe pantalla general).
- Guardas de negocio pendientes en la máquina de estados (pagos `INSCRIPCION`, documentos, observaciones, número de control): **PENDIENTES** de sus fases; en Fase 5 **no se simularon** con datos ficticios (§7.3.5).
- Comprobante PDF con branding UVZ → **Fase 9**.
- Git local activo: `master` acumula las fases aprobadas y el trabajo por fase se hace en ramas `feat/fase-*` (actualmente `feat/fase-5-nucleo-inscripcion`); sin remotos ni push.
