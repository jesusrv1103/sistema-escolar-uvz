# Plan de implementación — Sistema Escolar MVP

> Orden definitivo por fases. Complementa `docs/arquitectura-mvp.md` (versión 3, arquitectura aprobada).
> **Estado:** Fases 1, 1.1, 2, 3, 4, 5 y 6 **COMPLETAS**; **Fase 7 NO iniciada** — requiere aprobación explícita.

---

## Reglas del plan

1. **Cada fase termina con sus pruebas verdes.** Las pruebas se escriben **junto con el módulo**, nunca al final.
2. **Base de datos exclusiva de testing**: `sistema_escolar_testing` (MySQL). Prohibido usar las BD de desarrollo o producción.
3. **Nada fuera de la arquitectura aprobada.** Si surge algo nuevo, se documenta y se aprueba antes de implementar.
4. **Migraciones en el mismo orden de las fases**, respetando dependencias de FK.
5. Cada fase deja la app compilando (`composer test` + `npm run build` limpios).

---

## Fase 1 — Base del proyecto ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `laravel new` (Laravel 12.69, PHP 8.2) + Livewire (ahora **v4**, ver Fase 1.1) + Tailwind vía Vite + auth propia (login simple, sin Breeze/Jetstream) |
| Config | `APP_LOCALE=es_MX`, `timezone=America/Mexico_City`, moneda MXN; disco `private` fuera de `public/` en `config/filesystems.php`; middleware alias `role` en `bootstrap/app.php` |
| Pruebas | Pest + `phpunit.xml` apuntando a `sistema_escolar_testing` (MySQL); rate limiting en login; smoke test de login/logout |
| Salida | App corriendo, login funcional, suite Pest operativa |

**Guardas de avance cumplidas:** app funcional ✔ · login/logout ✔ · pruebas verdes ✔ · `npm run build` correcto ✔

## Fase 1.1 — Actualización tecnológica y personalización institucional ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Livewire | `^3.0` → **`^4.4.7`** (actualización limpia: la Fase 1 no dejó componentes Livewire; login, layout y pruebas conservados) |
| MySQL/Docker | **Docker solo para desarrollo local** (contenedor `sistema-escolar-mysql`, MySQL 8, puerto 3308; BD `sistema_escolar` y `sistema_escolar_testing` separadas). **No es dependencia de arquitectura**: producción irá a hosting compartido con MySQL del hosting (`docs/arquitectura-mvp.md` §23.6) |
| Branding | Nuevo `config/branding.php` (fuente única: nombre, UVZ, slogan, sitio, logo/favicon, colores, teléfono, correo, domicilio); vistas consumen `config('branding.*')` |
| Tokens visuales | `resources/css/app.css` (Tailwind 4): `@theme` enlaza `--color-brand-*` → `--brand-*`, inyectadas desde `layouts/app.blade.php`; sin HEX hardcodeados en Blade |
| Assets locales | `public/images/` (logo horizontal/vertical UVZ + favicon, descargados del sitio oficial, sin hotlinking) y `public/fonts/` (DM Sans + Playfair Display `.woff2` auto-alojados) |
| Login | Tarjeta institucional: logo UVZ, Universidad Villa de Zacatecas, "Sistema Escolar", campos, botón "Iniciar sesión" y lema |
| Layout | Cabecera con logo UVZ, nombre completo («UVZ» en pantallas reducidas), usuario autenticado y cerrar sesión; responsive; **sin menú de módulos** (Fases futuras) |
| Dashboard | Identidad UVZ + "Sistema Escolar" + bienvenida; sin métricas reales (Fase 14) |
| Documentación | `arquitectura-mvp.md` §23 "Identidad institucional y branding"; catálogo inicial y PDF con branding registrados |
| Pruebas | 15 pruebas verdes (11 de Fase 1 + 4 nuevas de branding); `npm run build` correcto |

**Pendientes registrados:** variante de logo para fondos oscuros (`branding.logo_dark`); semilla de catálogo y PDF con branding se ejecutan en sus fases.

## Fase 2 — Seguridad y autorización base ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | Enums (10): `RolUsuario`, `InscripcionEstatus`, `EstatusPago`, `EstatusDocumento`, `EstatusConvenio`, `TipoConcepto`, `TipoContacto`, `TipoConvenio`, `TipoObservacion`, `MetodoPago`; middleware `EnsureUserHasRole` (alias `role`); `AuthServiceProvider` con `Gate::before` (ADMIN) y mapa único `CAPACIDADES`; 8 policies; `AdminSeeder` |
| Migración | `users` (rol NOT NULL, activo, last_login_at, soft deletes) — incremental, no reemplaza la migración original |
| Pruebas | **F10** (`test_admin_accede_a_todo`) + matriz de roles, regla de información médica, middleware, usuario inactivo, cast de rol, AdminSeeder idempotente, `last_login_at` |
| Credenciales | `AdminSeeder` toma `ADMIN_EMAIL`/`ADMIN_PASSWORD` de `.env` (claves vacías en `.env.example`); sin secretos en `docs/` ni en el código |
| Guardas | `composer test` 26/26 ✔ · `npm run build` ✔ · `route:list` con `auth`/`guest` correctos ✔ |

## Fase 3 — Catálogos ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | CRUD simple (ADMIN): `programas`, `periodos` (+ `cerrado_at`), `tipos_documento`, `convenios` + `CatalogosSeeder`, `TiposDocumentoSeeder` |
| Migraciones | `programas`, `periodos`, `tipos_documento`, `convenios` |
| Semilla inicial | `CatalogosSeeder` parte de los 3 programas que publica el sitio UVZ: **Lic. en Derecho**, **Lic. en Seguridad y Administración Industrial**, **Lic. en Ciencia Forense** (ver arquitectura §23.4). El catálogo sigue siendo administrable |
| Salida | Catálogos administrables; periodos con cierre |
| Ajuste de extensibilidad (previo) | `programas.duracion_semestres` → **`duracion_periodos`** (`smallint unsigned`, **nullable**): duración total en periodos académicos, capturada por el ADMIN cuando exista fuente institucional; **sin** `tipo_periodo`, planes, RVOE, modalidad ni campus |
| Implementación | 4 migraciones (`2026_10_01_000004`–`000007`); modelos `Programa`, `Periodo`, `TipoDocumento` (`$table = tipos_documento`), `Convenio`; componentes Livewire 4 `App\Livewire\Catalogos\{Programas, Periodos, TiposDocumento, Convenios}` sobre la base abstracta `CatalogoAdmin` (crear/editar/guardar/activar-desactivar/eliminar + cerrar/reabrir periodo); rutas `/admin/{programas,periodos,tipos-documento,convenios}` con `auth` + `role:ADMIN`; enlaces mínimos en cabecera y dashboard (`@can('usuario.manage')`) |
| Seeders | `CatalogosSeeder` (claves internas `DER`, `SAI`, `CF`; `duracion_periodos = NULL`; idempotente) y `TiposDocumentoSeeder` (solo `ACTA_NAC`, lo definido en arquitectura; idempotente). `DatabaseSeeder` los invoca |
| Validaciones | server-side en cada `guardar()`: claves únicas + formato, `fecha_fin >= fecha_inicio`, convenio `PORCENTAJE` 0 < v ≤ 100 / `MONTO` v > 0, campos obligatorios, `Gate usuario.manage` en cada acción |
| Pruebas | `tests/Feature/CatalogosTest.php` (12): CRUD de los 4 catálogos, 403 a otros roles (rutas y componente), claves duplicadas, fechas inválidas, valores de convenio fuera de rango, soft delete, seeders idempotentes y semilla UVZ |
| Estado | ✅ **COMPLETA** — `composer test` 38 pruebas / 227 assertions verdes; `npm run build` OK; `route:list` OK |

## Fase 4 — Expediente del alumno ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `Alumno`, `Contacto`, `InformacionMedica` + componentes Livewire (FormAlumno, FormContactos, FormInfoMedica, ListaAlumnos) + **`InformacionMedicaPolicy`** (ADMIN/ADMISIONES ✔, CONTROL_ESCOLAR lectura en revisión final, **ADMINISTRACION ✖**) + `@can` en Blade |
| Migraciones | `alumnos` (CURP unique, `numero_control` char(8) unique nullable), `contactos`, `informacion_medicas` |
| Pruebas | **F1** CURP inválida, **F2** CURP duplicada, **F3** contactos + info médica, **F27** ADMINISTRACION 403 en info médica |
| Enums nuevos | **Aprobados en Fase 4:** `Sexo` (`MASCULINO` \| `FEMENINO`), `TipoSangre` (`A+`…`O-`, 8 con RH) |
| Rutas | `/alumnos`, `/alumnos/nuevo`, `/alumnos/{alumno}`, `/alumnos/{alumno}/contactos`, `/alumnos/{alumno}/info-medica` (todas `auth`; autorización en cada componente) |
| Info médica | Pantalla y acciones exigen el gate **`informacionMedica.edit`** (ADMIN/ADMISIONES): ADMINISTRACION ✖ siempre; CONTROL_ESCOLAR sin pantalla general en Fase 4 (su lectura contextual llega con la revisión final; conserva `informacionMedica.view`) |
| CURP | Sintaxis local (`Alumno::REGEX_CURP`, 18 chars, normalización a mayúsculas) + `unique` local; sin consultas RENAPO; datos ficticios en factories/pruebas (`ZZZZ…`) |
| Estado | ✅ **COMPLETA** — `composer test` 54 pruebas / 304 assertions verdes; `npm run build` OK; `route:list` OK |

## Fase 5 — Núcleo del flujo de inscripción ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | Modelo `Inscripcion`, **máquina de estados única** (constante `TRANSICIONES`), `InscripcionService::transicionar()`, `AuditoriaService`, trait `HasAudit` |
| Migración | `inscripciones` (estados, montos snapshot, campos de convenio, hitos, responsables, **UNIQUE (alumno_id, periodo_id)**), `auditoria` (morph, sin `updated_at`) |
| Pruebas | **U1–U3** matriz de transiciones, **F21** auditoría completa por transición |
| Estado máquina | `InscripcionEstatus::TRANSICIONES` (19 aristas de §7.2); retrocesos y `CANCELLED` exigen motivo; `ENROLLED`/`CANCELLED` terminales; guardas de módulos futuros **no** simuladas |
| Servicios | `InscripcionService` (valida → transacción → estatus + hitos/responsables → auditoría; excepciones `TransicionInvalida`/`MotivoRequerido`) y `AuditoriaService` (único punto de escritura, payload depurado, ip/agent opcionales) |
| Sin alcance | Sin Wizard, sin `conceptos_pago`, sin pagos/documentos/observaciones/consecutivos (Fases 6–12) |
| Estado | ✅ **COMPLETA** — `composer test` 69 pruebas / 609 assertions verdes; `npm run build` OK; `route:list` OK |

## Fase 6 — Wizard de inscripción ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `WizardInscripcion` (pasos 1–6) con validación por paso y **borrador persistido en BD**; `ListaInscripciones` con filtros |
| Reglas | Bloquea periodo con `cerrado_at` (RB-13) y duplicidad alumno+periodo (RB-14) |
| Pruebas | **F4** pasos completos → `DATA_COMPLETE`, **F5** paso incompleto no avanza, **F22** duplicidad, **F28** periodo cerrado |

## Fase 7 — Convenios y generación de cargos

| Elemento | Detalle |
|---|---|
| Tareas | `ConvenioService` (aplicar → `PENDIENTE_APROBACION`; aprobar/rechazar por ADMINISTRACION → `APROBADO`/`RECHAZADO`); `ModalAplicarConvenio`; `ConvenioPolicy`; `InscripcionService::generarCargos()` |
| Cargos | **Automáticos**: 1 concepto `INSCRIPCION` (nace `PAGADO` si monto = 0) + N `MENSUALIDAD` con vencimientos; descuento **solo si convenio APROBADO**; precondición: convenio resuelto antes de `PENDING_PAYMENT` |
| Pruebas | **U4–U6** cálculo de convenio (incl. sin aprobar ⇒ descuento 0), **F23** requiere aprobación antes de cargos, **F24** ADMISIONES no aprueba |

## Fase 8 — Pagos

| Elemento | Detalle |
|---|---|
| Tareas | `PagoService`: registrar (comprobante a disco privado), validar (folio único), rechazar con motivo; `ModalRegistrarPago`, `ModalValidarPago` |
| Regla crítica | **Un pago por concepto.** El ciclo `PENDING_PAYMENT → PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` **solo aplica al concepto `INSCRIPCION`**; validar una `MENSUALIDAD` **nunca** modifica `inscripciones.estatus` |
| Migración | `conceptos_pago`, `pagos` (folio unique, sin soft delete) |
| Pruebas | **F11** validación transaccional del pago INSCRIPCION, **F12** rechazo sin folio, **F25** validar mensualidad no cambia estado |

## Fase 9 — Comprobante de pago PDF

| Elemento | Detalle |
|---|---|
| Tareas | `ComprobantePagoService` + `resources/views/pdf/comprobante-pago.blade.php` + `ComprobantePagoController` (descarga autorizada) |
| Contenido | alumno, programa, concepto, monto, forma de pago, referencia, fecha, folio. **Solo pagos `VALIDADO`.** Sin facturación fiscal |
| Branding | Logo UVZ + "Universidad Villa de Zacatecas" + colores y datos institucionales **desde `config('branding.*')`** (arquitectura §23.5) |
| Dependencia | `barryvdh/laravel-dompdf` en versión compatible con Laravel 12 (**P1 aprobado**) |
| Pruebas | **F26** PDF solo con pago `VALIDADO` |

## Fase 10 — Documentos

| Elemento | Detalle |
|---|---|
| Tareas | `DocumentoService` (MIME real con `finfo`, ≤ 10 MB, formatos PDF/JPG/JPEG/PNG, disco privado), `PanelDocumentos`, `DescargaArchivosController` |
| Reglas | Upsert por `(inscripcion_id, tipo_documento_id)`; `PENDING_DOCUMENTS → DOCUMENT_REVIEW` solo con todos los obligatorios cargados; rechazo con motivo |
| Pruebas | **F13** carga completa, **F14** obligatorio faltante bloquea, **F15** rechazo con motivo |

## Fase 11 — Observaciones

| Elemento | Detalle |
|---|---|
| Tareas | `Observacion` (CRUD + resolver), `ListaObservaciones`; solo `FALTANTE` abierto bloquea la finalización |
| Migración | `observaciones` (append-only, sin soft delete) |

## Fase 12 — Revisión final y número de control ★

| Elemento | Detalle |
|---|---|
| Tareas | Guardas **RB-8 corregidas** (solo concepto `INSCRIPCION` pagado + documentos obligatorios `ACEPTADO` + sin `FALTANTE` abierto + revisión de Control Escolar + convenio `APROBADO`); `NumeroControlService` (`YYYY####`, una sola vez, se conserva) |
| Migración | `consecutivos` (`id`, `tipo`, `anio`, `ultimo_valor`, `timestamps`, **UNIQUE (tipo, anio)**) |
| Transacción | `finalizar()`: `NumeroControlService` → (1) iniciar transacción, (2) `lockForUpdate()` sobre `consecutivos(tipo=NUMERO_CONTROL, anio)`, (3) incrementar `ultimo_valor`, (4) generar `YYYY####` con 4 dígitos, (5) asignar al alumno, (6) `UNIQUE` en `alumnos.numero_control`, (7) todo dentro de la misma transacción de finalización → `ENROLLED`. **Prohibido `MAX(numero_control)`** |
| Clave | **NO se verifica el estado de las mensualidades** |
| Pruebas | **F16** caso feliz con NC, **F17** mensualidades pendientes **no** bloquean, **F18** bloqueo por INSCRIPCION/documentos/faltantes, **U7–U8** formato y unicidad del NC |

## Fase 13 — Cancelación

| Elemento | Detalle |
|---|---|
| Tareas | `InscripcionService::cancelar()` (solo ADMIN, motivo obligatorio) |
| Efectos | Conceptos `PENDIENTE → CANCELADO`; conceptos `PAGADO` intactos; pagos `RECHAZADO` como historial; **pagos `VALIDADO` nunca se tocan**; **`ENROLLED` no cancelable**; sin reembolsos |
| Pruebas | **F19** rol + motivo, **F20** efectos sobre conceptos/pagos |

## Fase 14 — Panel operativo y listados

| Elemento | Detalle |
|---|---|
| Tareas | `DashboardService` + `PanelOperativo` con **4 contadores**: inscripciones pendientes, pagos por validar, documentos pendientes, inscripciones completadas. Sin gráficas ni BI |
| Detalle | Vista de inscripción con historial de `auditoria` y estados de convenio/pago/documentos |

## Fase 15 — Cobertura completa de pruebas

| Elemento | Detalle |
|---|---|
| Tareas | **F6–F9** (matriz de autorización por rol) + revisión de cobertura de los 4 flujos críticos |
| Salida | Suite Pest verde al 100% sobre `sistema_escolar_testing` |

## Fase 16 — Endurecimiento (RB-21)

| Elemento | Detalle |
|---|---|
| Tareas | Rate limiting en login y cargas; validación server-side completa; archivos fuera de `public/` y descargas siempre autorizadas; sesiones seguras (regeneración al login); `@can` en toda vista; secretos solo en `.env`; verificación de `hidden` en modelos |
| Salida | Checklist de seguridad firmado |

---

## Resumen de cobertura de pruebas por fase

| Fase | Pruebas |
|---|---|
| 1 | smoke login |
| 2 | F10 |
| 4 | F1–F3, F27 |
| 5 | U1–U3, F21 |
| 6 | F4–F5, F22, F28 |
| 7 | U4–U6, F23–F24 |
| 8 | F11–F12, F25 |
| 9 | F26 |
| 10 | F13–F15 |
| 12 | F16–F18, U7–U8 |
| 13 | F19–F20 |
| 15 | F6–F9 + U9–U10 |

**Total: 10 unitarias (U1–U10) + 28 feature (F1–F28).**

---

## Decisiones P1–P3 — APROBADAS (Fase 1 autorizada)

- **P1 — APROBADO:** `barryvdh/laravel-dompdf` en versión compatible con Laravel 12.
- **P2 — APROBADO con ajuste:** tabla **`consecutivos`** (`id`, `tipo`, `anio`, `ultimo_valor`, `timestamps`, `UNIQUE(tipo, anio)`) con `tipo = NUMERO_CONTROL`; algoritmo de 7 pasos descrito en la Fase 12. **No se usa `MAX(numero_control)`.**
- **P3 — APROBADO:** base exclusiva de pruebas **`sistema_escolar_testing`** (MySQL); prohibido ejecutar pruebas contra desarrollo o producción.

### Guardas de avance de la Fase 1 — CUMPLIDAS ✅

La **Fase 2 no inicia** hasta que se cumpla todo lo siguiente:

1. la aplicación funcione; ✅
2. login/logout funcionen; ✅
3. las pruebas de la Fase 1 estén verdes; ✅
4. `npm run build` termine correctamente. ✅

> **Estado al cierre de la Fase 5:** Fase 1 ✅ · Fase 1.1 ✅ · Fase 2 ✅ · Fase 3 ✅ · Fase 4 ✅ · Fase 5 ✅ **COMPLETA** · **Fase 6 ⬜ NO iniciada, pendiente de aprobación del usuario.**
