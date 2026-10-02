# Estado del proyecto — Sistema Escolar UVZ

> Actualizado al cierre de la **Fase 3** (01/10/2026). Fuente de verdad técnica: `docs/arquitectura-mvp.md` (v3). Orden de ejecución: `docs/plan-implementacion.md`.

## Fases

| Fase | Estado |
|---|---|
| Fase 1 — Base del proyecto | ✅ **COMPLETA** (app, login/logout, 11 pruebas, `npm run build`) |
| Fase 1.1 — Livewire 4 + identidad institucional | ✅ **COMPLETA** (15 pruebas, build correcto) |
| Fase 2 — Seguridad y autorización base | ✅ **COMPLETA** (26 pruebas, build correcto, `route:list` OK) |
| Fase 3 — Catálogos | ✅ **COMPLETA** (38 pruebas, build correcto, `route:list` OK) |
| Fases 4–16 | ⬜ pendientes (esperan aprobación) |

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

## Pendientes / registrados

- **Fase 4 no iniciar** hasta aprobación del usuario.
- Cuenta ADMIN de desarrollo: `ADMIN_EMAIL`/`ADMIN_PASSWORD` en `.env` (valores no versionados ni documentados; `.env.example` solo con las claves vacías).
- Policies de modelos de fases futuras: las 8 clases ya existen; Laravel las asocia por convención cuando los modelos se creen (sin código artificial).
- Logo variante para fondos oscuros: **PENDIENTE** (el sitio no la publica; `branding.logo_dark` reutiliza el horizontal).
- `duracion_periodos`, `periodos` iniciales y más tipos de documento: **PENDIENTES de fuente institucional** (se capturan/seedan cuando UVZ confirme datos; nada inventado).
- Comprobante PDF con branding UVZ → **Fase 9**.
- No existe repositorio git en el directorio (nada commiteado).
