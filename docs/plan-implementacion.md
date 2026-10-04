# Plan de implementación — Sistema Escolar MVP

> Orden definitivo por fases. Complementa `docs/arquitectura-mvp.md` (versión 3, arquitectura aprobada).
> **Estado:** Fases 1, 1.1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13 y 14 **COMPLETAS** (ETAPA 1 — Admisión e Inscripción). **Fases 15 y 16 → reubicadas como 2.0-00 — Blindaje del núcleo existente** (Opción A aprobada, C6), **✅ COMPLETADA (386 tests / 2326 assertions, 0 migraciones, 33 rutas, build OK) — COMPLETA e integrada en master**. **2.0-01 ✅ COMPLETA e integrada en master (447 tests / 2471 assertions, 5 migraciones, 36 rutas, build OK)**. **2.0-02 ✅ COMPLETA e integrada en master** · **2.0-03 ✅ COMPLETA e integrada en master** · **2.0-04 ✅ COMPLETA e integrada en master** · **2.0-05 ✅ COMPLETA e integrada en master** · **2.0-06 ✅ COMPLETA e integrada en master** · **2.0-07 ✅ COMPLETA e integrada en master** · **2.0-08 ✅ COMPLETA e integrada en master**. 2.0-09 en adelante: NO INICIADAS.

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

## Fase 7 — Convenios y generación de cargos ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `ConvenioService` (aplicar → `PENDIENTE_APROBACION`; aprobar/rechazar por ADMINISTRACION → `APROBADO`/`RECHAZADO`); `ModalAplicarConvenio`; `InscripcionService::generarCargos()` (acción y lista de cargos en el wizard) |
| Decisión UVZ | **UVZ opera por CUATRIMESTRES**: 1 `INSCRIPCION` + exactamente **4** `MENSUALIDAD` (meses derivados de `periodos.fecha_inicio`), vencimiento el **día 10** centralizado en `config/escolar.php` (`escolar.dia_vencimiento_mensualidad`; clamp al último día del mes); convenio **solo sobre INSCRIPCION**; `duracion_periodos` se conserva |
| Cargos | **Automáticos**: 1 concepto `INSCRIPCION` (nace `PAGADO` si total = 0; vence en `periodos.fecha_inicio`) + 4 `MENSUALIDAD` con descuento 0; descuento **solo si convenio APROBADO** (`PORCENTAJE`: `monto × valor / 100`; `MONTO`: `min(valor, monto)`; redondeo a 2 decimales, nunca total negativo); precondición: convenio resuelto antes de `PENDING_PAYMENT`; **idempotencia** (sin duplicados) y **rollback total** ante fallo |
| Pruebas | **U4–U6** cálculo de convenio (incl. sin aprobar ⇒ descuento 0), **F23** requiere aprobación antes de cargos, **F24** ADMISIONES no aprueba + inactivos/vigencia/un-convenio/snapshot/rechazo/auditoría/modal + generación (5 conceptos, vencimientos, $0 → PAGADO, duplicados, rollback, autorización, integraciones) |

## Fase 8 — Pagos

| Elemento | Detalle |
|---|---|
| Tareas | `PagoService`: registrar (comprobante a disco privado), validar (folio único), rechazar con motivo; `ModalRegistrarPago`, `ModalValidarPago` |
| Regla crítica | **Un pago por concepto.** El ciclo `PENDING_PAYMENT → PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` **solo aplica al concepto `INSCRIPCION`**; validar una `MENSUALIDAD` **nunca** modifica `inscripciones.estatus` |
| Migración | `pagos` (folio `PAG-YYYYMMDD-########` unique, sin soft delete). **Aclaración de alcance:** `conceptos_pago` **ya existe desde la Fase 7** y **NO se recrea ni se reemplaza** |
| Pruebas | **F11** validación transaccional del pago INSCRIPCION, **F12** rechazo sin folio, **F25** validar mensualidad no cambia estado, **U9/U10** formato/unicidad/inmutabilidad del folio |

**✅ COMPLETA (02/10/2026):** `FolioPagoService` (folio `PAG-YYYYMMDD-########`: fecha de **validación** con timezone de la app + `pago.id` con padding a 8; sin `consecutivos`, sin `MAX()`, sin `NumeroControlService`); `PagoService` con `registrar()/validar()/rechazar()` en una transacción por operación, `lockForUpdate()` (un único intento `PENDIENTE` activo por concepto; históricos `RECHAZADO` preservados y permiten nuevo intento), monto exacto = `monto_total` (sin parciales), comprobante opcional PDF/JPG/JPEG/PNG ≤ 10 MB con MIME real (`finfo`) a **disco privado**, ruta interna por sistema y **compensación de archivos huérfanos** si la transacción falla; efecto de proceso **solo para `INSCRIPCION`** vía `InscripcionService::transicionar()` (registro → `PAYMENT_REVIEW`; validación → `PAYMENT_VALIDATED → PENDING_DOCUMENTS` con `pagos_validados_at`; rechazo → `PENDING_PAYMENT` con motivo); `ModalRegistrarPago` + `ModalValidarPago` integrados en el listado de cargos del wizard (sin información médica, RB-18); descarga del comprobante por **1 ruta nueva** mínima con policy, resolución desde BD y defensa contra path traversal. **153 pruebas verdes** (F11/F12/F25 + U9/U10 + 19 reglas de alta confianza adicionales).

## Fase 9 — Comprobante de pago PDF ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `ComprobantePagoService` + `resources/views/pdf/comprobante-pago.blade.php` + `ComprobantePdfController` (descarga autorizada) |
| Contenido | alumno, programa, concepto, monto, forma de pago, referencia, fecha, folio. **Solo pagos `VALIDADO`.** Sin facturación fiscal |
| Branding | Logo UVZ + "Universidad Villa de Zacatecas" + colores y datos institucionales **desde `config('branding.*')`** (arquitectura §23.5) |
| Dependencia | `barryvdh/laravel-dompdf` en versión compatible con Laravel 12 (**P1 aprobado**) |
| Pruebas | **F26** PDF solo con pago `VALIDADO` |

**✅ COMPLETA (02/10/2026):** `barryvdh/laravel-dompdf` **3.1.2** (dompdf 3.1.6) instalado vía Composer. `ComprobantePagoService` (`datos()` + `generar()`) exige pago `VALIDADO` con folio/validación/integridad completas, carga solo las relaciones necesarias, prepara los datos (branding desde `config('branding.*')`, logo como **data URI local** sin exponer rutas ni usar internet) y renderiza **bajo demanda** — sin persistir el PDF en BD ni en disco, sin modificar el pago y sin auditoría adicional. Vista Blade independiente con CSS propio DomPDF (sin Tailwind/Vite), contenido escapado, sin datos médicos/sensibles y con los avisos "Comprobante administrativo de pago / No constituye factura fiscal". `Pagos\ComprobantePdfController` invokable (nombre elegido por el usuario por colisión con el controlador de evidencia de Fase 8, **cuya ruta/semántica no se tocó**): `auth` + policy `descargarComprobantePdf` (4 roles con lectura) y nombre de descarga `comprobante-<FOLIO>.pdf`; ruta nueva `GET /pagos/{pago}/comprobante-pdf` (`pagos.comprobante-pdf`); no `VALIDADO` ⇒ 404. **163 pruebas verdes** (F26 + 9 de cobertura adicional en `ComprobantePagoTest`).

## Fase 10 — Documentos ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `DocumentoService` (MIME real con `finfo`, ≤ 10 MB, formatos PDF/JPG/JPEG/PNG, disco privado), `PanelDocumentos`, `DescargaArchivosController` |
| Reglas | Upsert por `(inscripcion_id, tipo_documento_id)`; `PENDING_DOCUMENTS → DOCUMENT_REVIEW` solo con todos los obligatorios cargados; rechazo con motivo |
| Pruebas | **F13** carga completa, **F14** obligatorio faltante bloquea, **F15** rechazo con motivo |

**✅ COMPLETA (02/10/2026):** migración `documentos` (UNIQUE `(inscripcion_id, tipo_documento_id)`, FK `cascade/restrict`, sin soft delete) + modelo `Documento` con `estatus`/`validado_*`/`motivo_rechazo` **fuera de `$fillable`** y relaciones en `Inscripcion`/`TipoDocumento`. `DocumentoService` (`subir()/aceptar()/rechazar()`) es el **único punto de negocio**: validación server-side (tamaño ≤ 10 MB, extensión admitida y **MIME real con `finfo`**), ruta interna UUID en **disco `private`** (el nombre original solo es metadato), rate limiting de cargas (30/min), **upsert por clave lógica** —una recarga reutiliza ESA MISMA fila, la deja `PENDIENTE` y limpia `validado_*`/`motivo_rechazo`— y **patrón de compensación**: archivo nuevo ANTES de la transacción, ante fallo de BD se borra el nuevo y el anterior jamás se toca; solo tras COMMIT se retira el reemplazado. Transiciones SIEMPRE vía `InscripcionService::transicionar()`: `PENDING_DOCUMENTS → DOCUMENT_REVIEW` cuando TODOS los obligatorios activos estén cargados (los opcionales no bloquean), `DOCUMENT_REVIEW → FINAL_REVIEW` cuando todos los obligatorios estén `ACEPTADO` (con `documentos_validados_at`), y rechazo `DOCUMENT_REVIEW → PENDING_DOCUMENTS` con el motivo como motivo explícito. `PanelDocumentos` (Livewire, ruta `inscripciones.documentos` integrada al wizard y al listado): muestra tipo, obligatorio/opcional, estatus, nombre original, fecha, responsable, motivo y acciones por permisos (histórico de tipos desactivados incluido, RB-18 sin datos médicos); `Documentos\DescargaArchivosController` (`documentos.archivo`, 2 rutas nuevas en total): auth + policy `view`, ruta SOLO desde BD, verificación de pertenencia al disco privado y anti path traversal. **192 pruebas verdes** (F13/F14/F15 + 26 de cobertura adicional: formatos, MIME, compensación, roles, descargas y auditoría).

## Fase 11 — Observaciones ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `Observacion` (**APPEND-ONLY §4.13/RB-15**: crear, listar/ver y resolver — sin editar/borrar), `ListaObservaciones`; solo `FALTANTE` abierto bloquea la finalización |
| Migración | `observaciones` (append-only, sin soft delete) |

**✅ COMPLETA (02/10/2026):** migración `observaciones` (`inscripcion_id` FK **cascade**, `tipo(20)`, `detalle` (text), `estatus(20)` default `ABIERTA`, `creada_por`/`resuelta_por` FK a `users`, `resuelta_at` nullable, timestamps; **sin soft delete**). El plan original decía "CRUD + resolver", pero **manda la arquitectura (§4.13/RB-15): APPEND-ONLY** — solo **crear, listar/ver y resolver**. Modelo `Observacion` con `$fillable` mínimo (`estatus`/`resuelta_*` **fuera**), endurecido como `Auditoria` (`update()`/`delete()`/`forceDelete()`/`restore()` → `LogicException`) y enum mínimo `EstatusObservacion` (`ABIERTA`/`RESUELTA`, **sin reapertura** en el MVP). `ObservacionService` (`crear()/resolver()`) es el **único punto de negocio**: validación server-side (`tipo` ∈ `TipoObservacion`, `detalle` 5–2000 con trim), autorización de los 4 roles según matriz §8.2 (`Gate::before` + capacidades `observacion.crear/resolver`, policy `ObservacionPolicy` conectada al modelo real), cada operación en **UNA transacción** con `lockForUpdate` + auditoría `observacion_creada`/`observacion_resuelta` en la misma transacción (RB-12, **ROLLBACK TOTAL** si la auditoría falla; payload con tipo/estatus/inscripción, **sin texto libre**), doble resolución **rechazada explícitamente**, `creada_por` = usuario autenticado server-side y **jamás** modifica `inscripciones.estatus`. Precondición: **no se crean sobre `CANCELLED`** (estado terminal: lectura y resolución de lo previo sí están permitidas); en `ENROLLED` se permiten **sin efectos sobre el proceso**. Guarda RB-8 punto 3 lista para la Fase 12: **`Inscripcion::tieneFaltantesAbiertos()`** (solo `FALTANTE` + `ABIERTA` bloquea; `DOCUMENTO`/`PAGO`/`GENERAL` no). `ListaObservaciones` (Livewire, ruta **`inscripciones.observaciones`** integrada al wizard y al listado): alta con validación, filtros de estatus/tipo, responsables y fechas, botón **Resolver**; binding horizontal por inscripción; sin datos médicos/CURP (RB-18). **219 pruebas verdes** (27 nuevas: ciclo de vida, validaciones, bloqueo `FALTANTE`, 4 roles, inactivos, listado sin datos sensibles, auditoría y rollback).

## Fase 12 — Revisión final y número de control ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | Guardas **RB-8 corregidas** (solo concepto `INSCRIPCION` pagado + documentos obligatorios `ACEPTADO` + sin `FALTANTE` abierto + revisión de Control Escolar + convenio **resuelto**: `APROBADO` o `RECHAZADO`, con `PENDIENTE_APROBACION` bloqueando); `NumeroControlService` (`YYYY####`, una sola vez, se conserva) |
| Migración | `consecutivos` (`id`, `tipo`, `anio`, `ultimo_valor`, `timestamps`, **UNIQUE (tipo, anio)**) |
| Transacción | `finalizar()`: `NumeroControlService` → (1) iniciar transacción, (2) `lockForUpdate()` sobre `consecutivos(tipo=NUMERO_CONTROL, anio)`, (3) incrementar `ultimo_valor`, (4) generar `YYYY####` con 4 dígitos, (5) asignar al alumno, (6) `UNIQUE` en `alumnos.numero_control`, (7) todo dentro de la misma transacción de finalización → `ENROLLED`. **Prohibido `MAX(numero_control)`** |
| Clave | **NO se verifica el estado de las mensualidades** |
| Pruebas | **F16** caso feliz con NC, **F17** mensualidades pendientes **no** bloquean, **F18** bloqueo por INSCRIPCION/documentos/faltantes, **U7–U8** formato y unicidad del NC |

**✅ COMPLETA (02/10/2026):** migración `consecutivos` (`tipo varchar(30)`, `anio smallint`, `ultimo_valor int unsigned` default 0, timestamps, **UNIQUE (tipo, anio)**, sin soft delete) + enum mínimo `TipoConsecutivo` (solo `NUMERO_CONTROL`, deliberadamente separado de `TipoConcepto`/`TipoObservacion`) + modelo `Consecutivo` (casts enum/enteros, `$fillable` mínimo, **sin CRUD ni UI**). **Decisión de alcance aprobada y documentada:** el `YYYY` se deriva **EXCLUSIVAMENTE de `inscripciones.periodo.fecha_inicio->year`** (aclaración de RB-9/P2 en `arquitectura-mvp.md`): `fecha_inicio = 2027-01-11` ⇒ primer NC `20270001` aunque se finalice en diciembre de 2026; en `consecutivos`, `anio` = ese año. `NumeroControlService::asignar(Alumno, Inscripcion)` es el **único punto de asignación**: si el alumno ya tiene NC lo devuelve **sin incrementar**; si no → `lockForUpdate()` del alumno, **`insert-or-ignore` + `UNIQUE`** para crear la fila del año de forma segura ante concurrencia, `lockForUpdate()` del consecutivo, incremento con **límite 9999 explícito** (`NumeroControlException`, sin wrap-around silencioso), `YYYY + str_pad` a exactamente 8 dígitos y `forceFill` sobre `alumnos.numero_control` (**fuera de `$fillable`**; `UNIQUE` como última defensa); `DB::transaction` se anida como SAVEPOINT en la transacción de `finalizar()` (nunca un commit intermedio). `InscripcionService::finalizar()` orquesta en **UNA transacción**: authorize `inscripcion.finalizar` (ADMIN/CONTROL_ESCOLAR; inactivos 403) → `lockForUpdate()` → **solo desde `FINAL_REVIEW`** (otro estado ⇒ rechazo explícito; `ENROLLED` terminal) → guardas RB-8 vía **`requisitosPendientes()` reutilizable**: 1) concepto `INSCRIPCION` `PAGADO` (reutiliza `Inscripcion::conceptoInscripcion()`); 2) documentos obligatorios `ACEPTADO` (reutiliza `DocumentoService::obligatoriosTodosAceptados()`, ahora público); 3) sin `FALTANTE`+`ABIERTA` (reutiliza `tieneFaltantesAbiertos()`); 5) convenio aplicado **debe estar RESUELTO** — `APROBADO` **o** `RECHAZADO` (corrección aprobada Fase 12: el rechazo es resuelto sin descuento —`monto_descuento = 0`, cargos normales— y **no bloquea**; único bloqueante `PENDIENTE_APROBACION`); 4) `validada_por` server-side → NC en la MISMA transición → `transicionar()` a `ENROLLED` (`finalizada_at`, `validada_por`, auditoría `estado_cambiado`) → auditoría **`numero_control_asignado`** (RB-12: `numero_control`, `anio`, `conservado`); **ROLLBACK TOTAL** ante cualquier error (probado con fallo REAL de `Auditoria::creating`, sin mocks). **NO se verifican mensualidades** ni observaciones que no sean `FALTANTE`+`ABIERTA`. `PanelEstados` (Livewire, ruta **`inscripciones.revision-final`**, integrada al wizard y al listado): alumno/NC actual, programa, periodo, estatus, pago `INSCRIPCION`, convenio, documentos con desglose (aceptados/pendientes/rechazados/ausentes), faltantes abiertos, requisitos pendientes y botón **Finalizar** solo con rol+estado+guardas — las guardas **siempre server-side** (forzar la llamada Livewire ⇒ 403 o error, sin cambios); éxito = "Inscripción completada" + NC; **sin PDF de constancia y sin información médica** (RB-18: no necesaria para la UI mínima). **256 pruebas verdes** (37 nuevas: F16–F18, U7–U8, concurrencia real de BD, rollback, autorización, UI y la corrección de convenios).

## Fase 13 — Cancelación ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `InscripcionService::cancelar()` (solo ADMIN, motivo obligatorio) |
| Efectos | Conceptos `PENDIENTE → CANCELADO`; conceptos `PAGADO` intactos; pagos `RECHAZADO` como historial; **pagos `VALIDADO` nunca se tocan**; **`ENROLLED` no cancelable**; sin reembolsos |
| Pruebas | **F19** rol + motivo, **F20** efectos sobre conceptos/pagos |

**✅ COMPLETA (02/10/2026):** excepción de dominio `CancelacionInvalidaException` (motivo inválido o estatus no cancelable). `InscripcionService::cancelar(Inscripcion, string $motivo, ?User)` es el **único punto de negocio**: authorize `inscripcion.cancelar` (capacidad ya existente **sin roles asignados** ⇒ solo ADMIN vía `Gate::before`; ADMISIONES/ADMINISTRACION/CONTROL_ESCOLAR e inactivos 403; el `@can` de la UI es solo UX) → motivo con **trim server-side**, obligatorio (no vacío ni solo espacios) y ≤ 2000 (mismo patrón que los rechazos de pagos/documentos/convenios) → **EN UNA transacción**: `lockForUpdate()` de la inscripción con estado fresco → **rechazo explícito de los estados terminales** `ENROLLED` y `CANCELLED` (sin idempotencia silenciosa) → conceptos: **únicamente `PENDIENTE → CANCELADO`** (`PAGADO`/`CANCELADO` intactos; `monto`, `descuento`, `monto_total`, `pagado_at` sin tocar) → transición a `CANCELLED` **mediante la máquina existente** (`transicionar()` reutilizado: escribe `cancelada_por`/`cancelada_at`/`cancelacion_motivo` y la auditoría `estado_cambiado` con el motivo) → auditoría **`inscripcion_cancelada`** con contexto mínimo (`conceptos_cancelados`, sin duplicar el motivo); **pagos (VALIDADO/RECHAZADO), documentos, observaciones y número de control: INTACTOS** — sin deletes, sin soft delete, sin updates financieros, sin folios/montos/comprobantes modificados, **sin reembolsos, sin reapertura ni devolución de NC**; **ROLLBACK TOTAL** ante cualquier error incluida la auditoría (probado con fallo REAL de `Auditoria::creating`, sin mocks de la lógica principal). UI mínima `ModalCancelarInscripcion` (Livewire, **incluida en el wizard; sin ruta nueva** ⇒ se mantienen las 33 rutas): el panel solo lo ve ADMIN (`Gate::allows`) y solo en estados cancelables (`estatus->puedeIrA(CANCELLED)`), con los tres avisos (definitiva en el MVP, no implica reembolso, pagos validados permanecen registrados) y modal con motivo obligatorio (trim previo + `required|string|max:2000`); tras cancelar muestra `CANCELLED`, la confirmación de flujo y **sin botón de reapertura**; la acción también autoriza server-side (forzar la llamada Livewire ⇒ 403). **271 pruebas verdes** (15 nuevas: F19/F20, 403 de 3 roles + usuario inactivo, motivos inválidos, estados terminales, 5 etapas cancelables por la máquina, efectos mixtos de conceptos/pagos, rollback real y UI).

## Fase 14 — Panel operativo y listados ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| Tareas | `DashboardService` (único punto de cálculo: exactamente los 4 contadores FC-9/RB-20 con `count()` directo y scopes normales; sin cache/Redis/jobs) + `PanelOperativo` **embebido en el `/dashboard` existente** (tarjetas simples y responsive; sin gráficas ni BI) |
| Contadores | `inscripciones_pendientes` = estatus ∉ {`ENROLLED`, `CANCELLED`} · `pagos_por_validar` = `PENDIENTE` · `documentos_pendientes` = documentos cargados en `PENDIENTE` · `inscripciones_completadas` = `ENROLLED`; sin soft-deleted |
| Detalle | `DetalleInscripcion` embebido en `inscripciones.ver` (**sin rutas nuevas**): estado general + hitos y cancelación; convenio legible (no aplica / `PENDIENTE_APROBACION` / `APROBADO` / `RECHAZADO` + `monto_descuento` y quién aplicó/aprobó); pagos por concepto (folio, método, comprobante/PDF vía policy); documentos (tipo/obligatorio/estatus/validación) e historial de `auditoria` |
| Autorización | Panel: `viewAny` de `Inscripcion` (4 roles activos ✔, inactivo 403 server-side). Historial: capacidad existente `auditoria.view` (solo ADMIN, §8.2) |
| Privacidad | Sin información médica (RB-18), rutas privadas, payload de auditoría ni hashes; listado operativo muestra número de control |
| Pruebas | **17 nuevas** (`PanelOperativoTest` + `DetalleInscripcionTest`): mezcla sintética de contadores, aislamiento/soft deletes, 4 roles, inactivo 403, privacidad, historial (orden, append-only, sin payload), convenio, pagos, documentos e integración |
| Guardas | `composer test` 288/2119 ✔ · `npm run build` ✔ · `route:list` sin rutas nuevas (33) ✔ · sin migraciones nuevas ✔ |

## Fase 15 — Cobertura completa de pruebas

> **↪️ Reubicada (Opción A aprobada, C6):** se ejecuta como **2.0-00 — Blindaje del núcleo existente**, junto con la Fase 16, **antes de 2.0-01** (ETAPA 2). Alcance original conservado. **✅ COMPLETA (2026-10-02).**

| Elemento | Detalle |
|---|---|
| Tareas | **F6–F9** (matriz de autorización por rol) + revisión de cobertura de los 4 flujos críticos + tests de políticas |
| Salida | Suite Pest verde al 100% sobre `sistema_escolar_testing` (386 pruebas / 2326 assertions) |

## Fase 16 — Endurecimiento (RB-21)

> **↪️ Reubicada (Opción A aprobada, C6):** se ejecuta como **2.0-00 — Blindaje del núcleo existente**, junto con la Fase 15, **antes de 2.0-01** (ETAPA 2). Alcance original conservado. **✅ COMPLETA (2026-10-02).**

| Elemento | Detalle |
|---|---|
| Tareas | Rate limiting en login y cargas; validación server-side completa; archivos fuera de `public/` y descargas siempre autorizadas; sesiones seguras (regeneración al login); `@can` en toda vista; secretos solo en `.env`; verificación de `hidden` en modelos; auditoría append-only con payload depurado; privacidad médica; concurrencia con `lockForUpdate` |
| Salida | Checklist RB-21 firmado — hardening completo del núcleo F1–F14 |

> **ETAPA 2 — Control Escolar:** la continuación (fases 2.0-00 … 2.0-17) está en `docs/roadmap-sistema-escolar.md`, con análisis completo en `docs/analisis-evolucion-control-escolar.md`. Este plan (Fases 1–16) documenta la ETAPA 1 y no se reescribe.

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
| 11 | ciclo de vida de observaciones (cobertura propia, 27) |
| 12 | F16–F18, U7–U8 (más concurrencia, rollback, autorización, UI y corrección de convenios: 37) |
| 13 | F19–F20 |
| 14 | contadores, detalle, autorización, privacidad e historial (cobertura propia, 17) |
| **15 (→ 2.0-00)** | **F6–F9 + tests de políticas — ✅ COMPLETA (92 nuevas)** |
| **16 (→ 2.0-00)** | **RB-21 hardening verificado en código — ✅ COMPLETA** |

**Total: 10 unitarias (U1–U10) + 28 feature (F1–F28) + 92 de 2.0-00 = 130 feature totales.**

---

## Validación al cierre de 2.0-00 (Blindaje del núcleo)

- `composer test` → **386 pruebas / 2326 assertions, todas verdes** (294 base + 92 nuevas).
- `npm run build` → **correcto** (`public/build`, sin errores).
- `php artisan route:list` → **33 rutas** (sin cambios).
- Migraciones: **0 nuevas** (2.0-00 cerró sin migraciones).
- Cobertura pendiente (F6–F9): **completa** — 4 tests de integración Livewire + matriz 20 gates × 4 roles + 16 tests de políticas.
- Hardening RB-21: **completo** — verificado en código (login/sesiones, autorización, archivos, validación, modelos, secretos, auditoría, privacidad médica, finanzas, estados, concurrencia, localización).
- Regresiones: **0** — F1–F14 intactas.
- Git: rama `feat/2.0-00-blindaje-nucleo` lista para merge a master.

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

> **Estado al cierre de 2.0-00:** Fase 1 ✅ · Fase 1.1 ✅ · Fase 2 ✅ · Fase 3 ✅ · Fase 4 ✅ · Fase 5 ✅ · Fase 6 ✅ · Fase 7 ✅ · Fase 8 ✅ · Fase 9 ✅ · Fase 10 ✅ · Fase 11 ✅ · Fase 12 ✅ · Fase 13 ✅ · Fase 14 ✅ · **Fase 15 (→ 2.0-00) ✅ COMPLETA** · **Fase 16 (→ 2.0-00) ✅ COMPLETA** — **2.0-00 COMPLETA en rama `feat/2.0-00-blindaje-nucleo`** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK). Pendiente merge a master y despliegue a staging. **2.0-01 COMPLETA e integrada en master** (447 tests / 2471 assertions, 5 migraciones, 36 rutas, build OK). **2.0-02 (Docentes y Aulas) ✅ COMPLETA** · **2.0-03 (Grupos y Horarios) ✅ COMPLETA** · **2.0-04 (Matrícula + Carga) ✅ COMPLETA** — todas en rama `feat/2.0-02-04-nucleo-academico`.

---

## 2.0-02 — Docentes y Aulas ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Migraciones** | `2026_10_03_101554_create_docentes_table` (FK `user_id` nullable restrict, `clave` unique, `tipo` enum `TipoDocente`, `activo`, soft deletes) + `2026_10_03_101601_create_aulas_table` (FK `edificio_id` nullable, `clave` unique, `tipo` enum `TipoAula`, `capacidad`, `activo`, soft deletes) |
| **Enums** | `TipoDocente` (`TIEMPO_COMPLETO`, `MEDIO_TIEMPO`, `HORAS`, `INVITADO`), `TipoAula` (`TEORICA`, `LABORATORIO`, `TALLER`, `AUDITORIO`, `VIRTUAL`, `OTRO`) |
| **Modelos** | `Docente` (FK `user_id`, `belongsTo User`, `hasMany Grupos`, `nombreCompleto()`, scope `activos`), `Aula` (`hasMany Grupos`, scope `activas`) |
| **Componentes** | `Docentes`, `Aulas` (heredan `CatalogoAdmin` abstracto: CRUD modal crear/editar/guardar/eliminar/alternar activo, búsqueda debounce, paginación 15, validación server-side + Gates) |
| **Rutas** | `/admin/docentes`, `/admin/aulas` (middleware `auth` + `role:ADMIN`, nombres `admin.docentes`, `admin.aulas`) |
| **Policies** | `DocentePolicy`, `AulaPolicy` (admin-only vía `Gate::before`, capacidades `docente.*`, `aula.*`) |
| **Factories** | `DocenteFactory`, `AulaFactory` (estados, datos sintéticos) |
| **Pruebas** | 23 Docente + 16 Aula = 39 pruebas (CRUD, validaciones, autorización 403/inactivo, duplicados, soft delete, UI) |
| **Validación** | `composer test` 39/39 ✔ · `npm run build` OK · `route:list` 2 rutas nuevas ✔ |

---

## 2.0-03 — Grupos y Horarios ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Migraciones** | `2026_10_03_110300_create_grupos_table` (FK `materia_id`, `periodo_id`, `docente_id`/`aula_id` nullable, `clave` 20, `cupo`, `turno` enum `TurnoGrupo`, `estatus` enum `EstatusGrupo`, `activo`, `nombre` nullable, `UNIQUE(materia_id, periodo_id, clave)`, soft deletes) + `2026_10_03_110329_create_grupo_horarios_table` (FK `grupo_id` cascade, `dia` enum `DiaSemana`, `hora_inicio/fin` time, soft deletes) + 3 incrementales (`add_nombre_to_grupos_table`, `add_activo_to_grupos_table`, `add_soft_deletes_to_grupo_horarios_table`) |
| **Enums** | `EstatusGrupo` (`CREADO`→`ABIERTO`→`EN_CURSO`→`CERRADO`, `CANCELADO`; transiciones + terminales), `TurnoGrupo` (`MATUTINO`, `VESPERTINO`, `NOCTURNO`), `DiaSemana` (`LUNES`–`SABADO`, `orden()` 1–6) |
| **Modelos** | `Grupo` (FK `materia`, `periodo`, `docente`, `aula`; `hasMany Horarios`, `hasMany InscripcionesMaterias`, `scopeActivos`, `estaAbierto()`, `tieneCupo()` usando `CURSANDO`), `GrupoHorario` (FK `grupo`, `dia` enum, `hora_inicio/fin` time, `belongsTo Grupo`) |
| **Componentes** | `GrupoAdmin` (CRUD: `materia_id`, `periodo_id`, `docente_id`, `aula_id`, `cupo` 1–500, `turno`, `estatus`, `nombre` auto; validación `clave` `[A-Z0-9_-]+`, `cupo` 1–500, transiciones `CREADO`→`ABIERTO`→`EN_CURSO`→`CERRADO`, `CANCELADO`, `activo` toggle solo en `CREADO`), `GrupoHorarioAdmin` (CRUD horarios por grupo/global; validación `dia` enum, `hora_fin > hora_inicio`, **conflictos**: aula/docente/horario en mismo día con solapamiento) |
| **Rutas** | `admin.grupos`, `admin.grupos.horarios` (middleware `auth` + `role:ADMIN`) |
| **Policies** | `GrupoPolicy`, `GrupoHorarioPolicy` (admin-only) |
| **Factories** | `GrupoFactory`, `GrupoHorarioFactory` (estados, relaciones) |
| **Pruebas** | 23 Grupo + 16 GrupoHorario = 39 pruebas (CRUD, validaciones, transiciones, autorización, conflictos aula/docente/horario, soft delete, UI) |
| **Validación** | `composer test` 39/39 ✔ · `npm run build` OK · `route:list` 2 rutas nuevas ✔ |

---

## 2.0-04 — Matrícula Académica + Carga de Materias ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Migraciones** | `2026_10_03_142305_create_matriculas_periodo_table` (`alumno_id`, `periodo_id`, `programa_id`, `plan_version_id` nullable, `origen` enum `PRIMERA_VEZ`/`REINGRESO`/`CAMBIO_PROGRAMA`/`TRASLADO_EXTERNO`, `fecha_matricula`, `estatus` `ACTIVA`/`BAJA_TEMPORAL`/`BAJA_DEFINITIVA`/`EGRESADO`, `UNIQUE(alumno_id, periodo_id)`, soft deletes) + `2026_10_03_142323_create_inscripciones_materias_table` (`matricula_periodo_id`, `grupo_id`, `materia_id`, `tipo_prerrequisito` enum `OBLIGATORIO`/`RECOMENDADO`, `prerrequisito_cumplido`, `estatus` `CURSANDO`/`APROBADA`/`REPROBADA`/`BAJA`/`VALIDADA`, `calificacion_final` decimal:2, `fecha_baja`, `UNIQUE(matricula_periodo_id, grupo_id)`, soft deletes) |
| **Enums** | `EstatusMatriculaPeriodo` (`ACTIVA`, `BAJA_TEMPORAL`, `BAJA_DEFINITIVA`, `EGRESADO`), `OrigenMatricula` (`PRIMERA_VEZ`, `REINGRESO`, `CAMBIO_PROGRAMA`, `TRASLADO_EXTERNO`), `EstatusInscripcionMateria` (`CURSANDO`, `APROBADA`, `REPROBADA`, `BAJA`, `VALIDADA`), `TipoPrerrequisito` (`OBLIGATORIO`, `RECOMENDADO` — **sin `NINGUNO`**) |
| **Modelos** | `MatriculaPeriodo` (FK `alumno`, `periodo`, `programa`, `plan_version`; `hasMany InscripcionesMaterias`, `scopeActivas`, `isActiva()`), `InscripcionMateria` (FK `matricula_periodo`, `grupo`, `materia`; `tipo_prerrequisito`, `prerrequisito_cumplido`, `estatus`, `calificacion_final` decimal:2; scopes `Cursando`, `PorMatricula`, `PorGrupo`; helpers `isCursando()`, `isAprobada()`, `tienePrerrequisitoObligatorioPendiente()`) |
| **Servicios** | `MatriculaService` (`crearDesdeInscripcion()` desde `ENROLLED`, `crear()` manual, `cambiarEstatus()` con transiciones, `puedeMatricularse()`, `obtenerActiva()`), `InscripcionMateriaService` (`inscribir()`, `inscribirLote()`, `cambiarEstatus()` con transiciones, **validación prerrequisitos**: RECOMENDADO = solo advertencia; OBLIGATORIO = bloquea **SOLO** si hay historial y no está APROBADA/VALIDADA; sin historial ⇒ no bloquea; validaciones: cupo, grupo ABIERTO, matrícula ACTIVA, no duplicados, conflictos de horario; `tieneCupo()` usa `CURSANDO`) |
| **Prerrequisitos** | `TipoPrerrequisito::OBLIGATORIO` / `RECOMENDADO` (sin `NINGUNO`). OBLIGATORIO bloquea **solo** si hay inscripción previa NO aprobada; sin historial ⇒ no bloquea; RECOMENDADO nunca bloquea |
| **Policies** | `MatriculaPeriodoPolicy`, `InscripcionMateriaPolicy` (admin-only) |
| **Factories** | `MatriculaPeriodoFactory`, `InscripcionMateriaFactory` (estados, relaciones) |
| **Autorización** | `matricula.*`, `inscripcion_materia.*`, `prerrequisito.validar` en `AuthServiceProvider` (admin-only) |
| **Pruebas** | 10 `MatriculaServicePhpUnitTest` + 13 `InscripcionMateriaServicePhpUnitTest` = 23 pruebas (CRUD, transiciones, prerrequisitos OBLIGATORIO/RECOMENDADO, bloqueo por reprobado, permiso por aprobado, sin historial no bloquea, cupo, horarios, duplicados, autorización) |
| **Validación** | `composer test` 23/23 ✔ · `npm run build` OK · `route:list` sin rutas nuevas (solo admin) ✔ |

---

### Métricas del macrobloque 2.0-02 → 2.0-04

- **Tests totales:** 514 (386 previas + 101 nuevas: 39 + 39 + 23)
- **Assertions:** 2645
- **Migraciones nuevas:** 10 (6 base + 4 incrementales)
- **Rutas nuevas:** 4 (`admin.docentes`, `admin.aulas`, `admin.grupos`, `admin.grupos.horarios`)
- **Enums nuevos:** 9 (`TipoDocente`, `TipoAula`, `EstatusGrupo`, `TurnoGrupo`, `DiaSemana`, `EstatusMatriculaPeriodo`, `OrigenMatricula`, `EstatusInscripcionMateria`, `TipoPrerrequisito`)
- **Build:** `npm run build` → **OK**
- **Rutas:** 37 totales (33 + 4 nuevas)
- **Tests:** 514 tests / 2645 assertions / 0 fallos
- **Branch:** `feat/2.0-02-04-nucleo-academico`

---

> **Estado actualizado:** **2.0-00 COMPLETA e integrada en master** · **2.0-01 COMPLETA e integrada en master** · **2.0-02 COMPLETA** · **2.0-03 COMPLETA** · **2.0-04 COMPLETA** · **2.0-05 COMPLETA** · **2.0-06 COMPLETA** · **2.0-07 COMPLETA** · **2.0-08 COMPLETA** — todo en rama `feat/2.0-05-08-evaluacion-academica`. 2.0-09 en adelante: NO INICIADAS.

---

## 2.0-05 — Motor de Formatos + Primeros Formatos ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Pipeline** | DATOS/REGLAS → SERVICIO (`FormatoService`) → PLANTILLA BLADE → PDF (DomPDF) |
| **Formatos (5)** | Constancia de Inscripción (CI), Comprobante de Inscripción, Ficha del Alumno, Horario del Alumno, Lista de Grupo |
| **Folio CI** | `CI-YYYY-########` (tipo `CONSTANCIA_INSCRIPCION` en `TipoConsecutivo`; consecutivo con `lockForUpdate()`, `UNIQUE(tipo, anio)`) |
| **Descarga privada** | `DescargaDocumentoGeneradoController` (disco `private`, policy `documento_generado.ver`, anti path-traversal, nombre sanitizado) |
| **Trazabilidad** | Tabla `documentos_generados` (FK `alumno_id` nullable, `matricula_periodo_id`, `grupo_id`, `tipo`, `folio`, `emitido_por`, `emitido_at`, `snapshot_datos`) |
| **Branding** | Centralizado en `config/branding.php` → variables `--brand-*` → tokens `@theme` Tailwind 4 → PDF (data URI local del logo) |
| **Autorización** | `documento_generado.crear/ver` (ADMIN, ADMISIONES, CONTROL_ESCOLAR, DOCENTE sus grupos); `Gate::before` para ADMIN |
| **PDFs** | 5 plantillas Blade en `resources/views/pdf/formatos/` (HTML + CSS DomPDF-compatible, sin Tailwind/Vite) |
| **Pruebas** | 9 tests (`FormatoServiceTest`): generación, folio, branding, autorización, IDOR, concurrencia folios |
| **Validación** | `composer test` 9/9 ✔ · `npm run build` OK · `route:list` 1 ruta nueva (`admin.formatos`) ✔ |

---

## 2.0-06 — Evaluaciones, Calificaciones + Rol DOCENTE ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Rol DOCENTE** | `RolUsuario::DOCENTE`: `User::docente()` (HasOne a `Docente` con `user_id`), factory `UserFactory::docente()` crea perfil |
| **Evaluacion** | `grupo_id`, `nombre`, `tipo` (string libre, no catálogo), `ponderacion` 1–100, `fecha`, `descripcion`, `creada_por`; **SIN máquina de estados** (inmutabilidad controlada por cierre de ACTA en 2.0-08); `tipo` valida contra `TipoEvaluacion` (enum con 7 ejemplos + `esValido()` permite cualquier string) |
| **Calificacion** | `inscripcion_materia_id`, `evaluacion_id`, `valor` decimal(5,2) 0.00–100.00, `capturada_por`, `capturada_at`, `observacion`; **escala 0.00–100.00**, **aprobatoria 60.00**, **2 decimales**, **HALF_UP** (convencional, no bancario); `≥60 = APROBADA`, `<60 = REPROBADA` |
| **Config centralizada** | `config/escolar.php` → `calificaciones` (aprobatoria, min, max, decimales) |
| **Servicios** | `EvaluacionService` (crear/actualizar/eliminar + validación ponderación ≤100%, autorización docente/CE/ADMIN, sin publicar/cerrar), `CalificacionService` (capturar/consolidar; ponderación total 100% obligatoria; todas las evaluaciones con calificación; consolidación = ponderada + redondeo HALF_UP + estatus APROBADA/REPROBADA + auditoría) |
| **Policies** | `EvaluacionPolicy` (crear/actualizar/eliminar: docente/CE/ADMIN), `CalificacionPolicy` (capturar/consolidar/ver: docente sus grupos/CE/ADMIN) |
| **Livewire** | `EvaluacionAdmin` (CRUD, filtro grupo/tipo, docente solo sus grupos), `CalificacionAdmin` (captura/consolidación, filtro grupo/estatus) |
| **Autorización** | `evaluacion.crear/actualizar/eliminar` (DOCENTE, CE), `calificacion.crear/actualizar/consolidar` (DOCENTE, CE, ADMIN) |
| **Pruebas** | Incluidas en suite completa (533 tests / 2679 assertions / 0 fallos / 0 risky) |
| **Validación** | `composer test` ✔ · `npm run build` OK · `route:list` 2 rutas nuevas (`admin.evaluaciones`, `admin.calificaciones`) ✔ |

---

## 2.0-07 — Asistencia + Extraordinarios (D-N4) ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Asistencia** | `inscripcion_materia_id`, `fecha`, `presente` (boolean), `registrado_por`; `UNIQUE(inscripcion_materia_id, fecha)`; solo presente/ausente (sin retardos, justificantes, porcentajes, sanciones) |
| **Servicio** | `AsistenciaService` (registrar individual/masivo; autorización docente del grupo/CE/ADMIN; validaciones: inscripción CURSANDO, fecha dentro del periodo, no duplicado; auditoría) |
| **Policy** | `AsistenciaPolicy` (crear/actualizar/ver: docente sus grupos, CE, ADMIN) |
| **Livewire** | `AsistenciaAdmin` (filtro grupo/fecha, lista alumnos con checkbox presente/ausente, guardar masivo), `AsistenciaForm` (modal individual) |
| **D-N4 Extraordinarios** | 1 única oportunidad, solo desde REPROBADA, sin requisito adicional de calificación previa, sin % mínimo asistencia, sin límite de materias, fechas flexibles. Misma escala 0–100, misma aprobatoria 60.00, 2 decimales, HALF_UP. Sustituye `calificacion_final` ordinaria; **conserva trazabilidad** (auditoría guarda calificación ordinaria + extraordinaria). No promedia, no cobra, no segunda oportunidad, no modalidades adicionales. |
| **Servicio** | `ExtraordinarioService` (iniciar solo desde REPROBADA, capturar calificación, consolidar automáticamente; autorización CE/ADMIN; evaluación tipo `EXTRAORDINARIO` peso 100% creada automáticamente) |
| **Exception** | `ExtraordinarioException` |
| **Estado nuevo** | `EstatusInscripcionMateria::EXTRAORDINARIO` (entre REPROBADA y BAJA) |
| **Validación** | `composer test` ✔ · `npm run build` OK · `route:list` 1 ruta nueva (`admin.asistencia`) ✔ |

---

## 2.0-08 — Actas de Calificaciones (D-N7) ✅ COMPLETA

| Elemento | Detalle |
|---|---|
| **Acta** | `grupo_id`, `periodo_id`, `folio` (nullable hasta cierre), `estatus` (`ABIERTA` \| `CERRADA`), `cerrada_por`, `cerrada_at`; `UNIQUE(grupo_id, periodo_id)` |
| **Folio ACT** | `ACT-YYYY-########` (tipo `ACTA_CALIFICACIONES` en `TipoConsecutivo`; `ConsecutivoService::generar()` con `lockForUpdate()`, generado **solo al cierre definitivo** en la misma transacción) |
| **Four-eyes obligatorio** | DOCENTE captura calificaciones; CONTROL_ESCOLAR cierra acta; ADMIN Gate::before; DOCENTE **no puede cerrar** su propia acta |
| **Cierre (validaciones previas)** | Ponderación total = 100%; Todas las evaluaciones del grupo tienen calificaciones para alumnos CURSANDO; Calificaciones finales consolidadas (APROBADA/REPROBADA definidas); Grupo/periodo coherentes; Extraordinarios resueltos (cuando correspondan) |
| **Inmutabilidad post-cierre** | Tras `CERRADA` bloquear crear/editar/eliminar evaluaciones, capturar/modificar calificaciones, recalcular final, extraordinario (validado server-side en servicios, no solo UI) |
| **Servicio** | `ActaService` (crear ABIERTA sin folio, cerrar con validaciones + folio + auditoría; `DB::transaction` + `lockForUpdate()`) |
| **Policy** | `ActaPolicy` (crear: docente/CE/ADMIN; cerrar: CE/ADMIN; ver: docente sus grupos/CE/ADMIN; DOCENTE no cierra) |
| **Livewire** | `ActaAdmin` (filtros periodo/estatus/búsqueda, listado, botón Cerrar solo para CE/ADMIN), `CrearActa` (modal grupo/periodo, valida coherencia) |
| **Folio D-N7** | `ACT-YYYY-########` generado **solo al cierre** con `ConsecutivoService` (`lockForUpdate` + `UNIQUE(tipo, anio)`) |
| **Pruebas de concurrencia** | `UNIQUE(tipo, anio)` rechaza duplicado, `lockForUpdate()` emite `FOR UPDATE` real, fila ausente creada con `insert-or-ignore`, incrementos secuenciales no pierden valores |
| **Validación** | `composer test` ✔ · `npm run build` OK · `route:list` 1 ruta nueva (`admin.actas`) ✔ |

---

### Métricas finales del macrobloque 2.0-05 → 2.0-08

- **Tests totales:** 533 (514 previas + 19 nuevas del macrobloque)
- **Assertions:** 2679
- **Migraciones nuevas:** 8 (`asistencias`, `actas`, `evaluaciones`, `calificaciones`, `documentos_generados` + 4 incrementales: `estatus` add/drop, `alumno_id` nullable)
- **Rutas nuevas:** 2 (`admin.asistencia`, `admin.actas`)
- **Enums nuevos/ampliados:** `TipoFormato` (5), `TipoConsecutivo` (+`ACTA_CALIFICACIONES`), `EstatusInscripcionMateria` (+`EXTRAORDINARIO`), `TipoEvaluacion` (flexible)
- **Build:** `npm run build` → **OK**
- **Rutas:** `php artisan route:list` → 46 rutas (44 previas + 2 nuevas)
- **Tests:** `composer test` → **533 tests / 2679 assertions / 0 fallos / 0 risky**
- **Migraciones:** 8 nuevas + 4 incrementales = 12 en total para el macrobloque
- **Branch:** `feat/2.0-05-08-evaluacion-academica`

---

## Pendientes / registrados

- **`docs/reglas-desarrollo.md`**: documento permanente de reglas (fuentes de verdad, alcance, stack, seguridad, BD, Git, cierre de fase, pruebas, reglas críticas, branding, cambios no previstos) — obligatorio en todas las fases futuras.
- Cuenta ADMIN de desarrollo: `ADMIN_EMAIL`/`ADMIN_PASSWORD` en `.env` (valores no versionados ni documentados; `.env.example` solo con las claves vacías; **`DB_PASSWORD` también vacía en `.env.example` y `phpunit.xml` — credenciales solo en `.env`**).
- Policies: las 8 clases existen desde la Fase 2; `PagoPolicy`, `DocumentoPolicy` y `ObservacionPolicy` están **registradas explícitamente** en `AuthServiceProvider` (modelo real) y el resto se resuelve por convención cuando los modelos las usen (sin código artificial).
- Logo variante para fondos oscuros: **PENDIENTE** (el sitio no la publica; `branding.logo_dark` reutiliza el horizontal).
- `duracion_periodos`, `periodos` iniciales y más tipos de documento: **PENDIENTES de fuente institucional** (se capturan/seedan cuando UVZ confirme datos; nada inventado).
- Lectura **contextual** de información médica para CONTROL_ESCOLAR: **PENDIENTE** — el `PanelEstados` de la Fase 12 **no la incluyó** por no ser necesaria para la UI mínima (RB-18: no se amplió el acceso por iniciativa propia; `informacionMedica.view` de CONTROL_ESCOLAR se conserva intacto).
- Guardas de negocio de la máquina de estados: **RESUELTAS en la Fase 12 ✅** — `finalizar()` conecta el número de control (`NumeroControlService`), la consulta de `FALTANTE`+`ABIERTA` (`tieneFaltantesAbiertos()`), los documentos obligatorios (`obligatoriosTodosAceptados()`) y el concepto `INSCRIPCION`; en Fase 5 **no se simularon** con datos ficticios (§7.3.5).
- Comprobante PDF con branding UVZ → **Fase 9 ✅ COMPLETA** (bajo demanda, solo pagos `VALIDADO`).
- Documentos: recepción/reemplazo/validación con `DocumentoService`, `PanelDocumentos` y descarga privada → **Fase 10 ✅ COMPLETA** (UNIQUE por tipo, compensación de archivos, F13–F15).
- Observaciones: crear/listar/resolver con `ObservacionService`, `ListaObservaciones` y política conectada → **Fase 11 ✅ COMPLETA** (APPEND-ONLY, 27 pruebas, sola guarda `FALTANTE`+`ABIERTA` reutilizable).
- Revisión final y número de control: `NumeroControlService`, `finalizar()` con guardas RB-8 y `PanelEstados` → **Fase 12 ✅ COMPLETA** (256 pruebas totales, 37 nuevas, 1 ruta nueva; año `YYYY` = `periodos.fecha_inicio`; convenio `RECHAZADO` resuelto sin descuento, no bloquea).
- Cancelación: `InscripcionService::cancelar()`, `ModalCancelarInscripcion` y pruebas F19/F20 → **Fase 13 ✅ COMPLETA** (271 pruebas totales, 15 nuevas, sin rutas nuevas; solo ADMIN con motivo; solo `PENDIENTE → CANCELADO`; pagos/documentos/observaciones/NC intactos; sin reembolsos ni reapertura).
- Panel operativo y detalle: `DashboardService`, `PanelOperativo` y `DetalleInscripcion` → **Fase 14 ✅ COMPLETA** (288 pruebas totales, 17 nuevas, sin rutas nuevas; exactamente los 4 contadores FC-9/RB-20; historial de auditoría solo ADMIN por la capacidad existente `auditoria.view`; sin información médica ni rutas privadas).
- Git local activo: `master` acumula las fases aprobadas (merges `--ff-only` de Fase 5 en `b8965e4`→`2d19d0d`, de Fase 6 en `2d19d0d`→`fe6c977`, de Fase 7 en `fe6c977`→`d8337f0`, de Fase 8 en `d8337f0`→`87bb211`, de Fase 9 en `87bb211`→`2f13e84`, de Fase 10 en `2f13e84`→`3632f4f`, de Fase 11 en `3632f4f`→`d00b3e2`, de Fase 12 en `d00b3e2`→`d625084` y de Fase 13 en `d625084`→`694ecb4`) y el trabajo por fase se hace en ramas `feat/fase-*` (actualmente `feat/2.0-05-08-evaluacion-academica`, **sin merge a master**; se conservan `feat/fase-13-cancelacion`, `feat/fase-12-revision-final-numero-control`, `feat/fase-11-observaciones`, `feat/fase-10-documentos`, `feat/fase-9-comprobante-pdf`, `feat/fase-8-pagos`, `feat/fase-7-convenios-cargos`, `feat/fase-6-wizard-inscripcion`, `feat/fase-5-nucleo-inscripcion` y `feat/fase-4-expediente-alumno`); sin remotos ni push.
- **Estado de fases de la Etapa 2:** 2.0-00 ✅ COMPLETA e integrada en master · 2.0-01 ✅ COMPLETA e integrada en master · 2.0-02 ✅ COMPLETA · 2.0-03 ✅ COMPLETA · 2.0-04 ✅ COMPLETA · 2.0-05 ✅ COMPLETA · 2.0-06 ✅ COMPLETA · 2.0-07 ✅ COMPLETA · 2.0-08 ✅ COMPLETA — todo en rama `feat/2.0-05-08-evaluacion-academica`. 2.0-09 en adelante: NO INICIADAS.
