# Arquitectura MVP — Sistema Escolar (Admisión e Inscripción)

> Documento técnico de propuesta. **Sin código de aplicación.**
> Entorno de desarrollo detectado: directorio de proyecto vacío (greenfield), PHP 8.2.34, Composer 2.8.5, MySQL 8.0.46, Node 22.23.2 / npm 10.9.8 → compatible con **Laravel 12 + Livewire 4 + Tailwind CSS**.
>
> **Versión 3 — decisiones finales incorporadas**: corrección de mensualidades (§11 RB-3/RB-5/RB-8, §7 y §13), decisiones D1–D6, ajustes 7–18, seguridad de información médica y **P1/P2/P3 aprobados** (§22). **Plan de implementación aprobado** → **Fase 1 autorizada** (`docs/plan-implementacion.md`).
>
> **Fase 7 registrada**: decisiones UVZ cuatrimestrales aprobadas → **UVZ opera por CUATRIMESTRES**, 4 mensualidades por inscripción con vencimiento el **día 10 configurable** (`config/escolar.php`), convenio aplicado **únicamente al concepto INSCRIPCION** y descuento fijo limitado al monto de inscripción (§4.9, RB-3, FC-4). La abstracción `duracion_periodos` **se conserva**.
>
> **Fase 8 registrada**: pagos implementados → folio **`PAG-YYYYMMDD-########`** generado **solo al validar** (fecha de validación con el timezone de la aplicación + `pago.id` con padding a 8 dígitos; `UNIQUE(folio)` en BD como defensa final, sin tabla `consecutivos`, sin `MAX()`, sin reutilizar `NumeroControlService`), **un único intento activo por concepto** (a lo sumo UN `PENDIENTE` a la vez, bloqueado con `lockForUpdate()`, o UN `VALIDADO` que liquida el concepto) e **historial de intentos `RECHAZADO` preservado** (nunca se sobrescribe ni elimina; tras un rechazo se admite un nuevo intento) — §4.10, RB-4, RB-10.
>
> **Etapa 2 registrada (02/10/2026)**: **ETAPA 2 — CONTROL ESCOLAR aprobada en planeación** → **§24** (F1–F14 = ETAPA 1 — Admisión e Inscripción; `inscripciones` reservada al ingreso; nueva entidad `matriculas_periodo`; roles DOCENTE/ALUMNO; formatos internos UVZ; F15/F16 → 2.0-00). **2.0-00 ✅ COMPLETADA** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK); **2.0-01 ✅ COMPLETA e integrada en master** · **2.0-02 ✅ COMPLETA e integrada en master** · **2.0-03 ✅ COMPLETA e integrada en master** · **2.0-04 ✅ COMPLETA e integrada en master** · 2.0-05 en adelante NO INICIADAS.

---

## Índice

1. [Objetivo y alcance del MVP](#1-objetivo-y-alcance-del-mvp)
2. [Módulos del sistema](#2-módulos-del-sistema)
3. [Tablas MySQL necesarias](#3-tablas-mysql-necesarias)
4. [Campos principales de cada tabla](#4-campos-principales-de-cada-tabla)
5. [Relaciones entre tablas (ER)](#5-relaciones-entre-tablas-er)
6. [Relaciones Eloquent](#6-relaciones-eloquent)
7. [Estados y transiciones de la inscripción](#7-estados-y-transiciones-de-la-inscripción)
8. [Roles y permisos](#8-roles-y-permisos)
9. [Estructura recomendada del proyecto Laravel](#9-estructura-recomendada-del-proyecto-laravel)
10. [Services, Actions, Policies y clases adicionales](#10-services-actions-policies-y-clases-adicionales)
11. [Reglas de negocio](#11-reglas-de-negocio)
12. [Validaciones](#12-validaciones)
13. [Flujos críticos](#13-flujos-críticos)
14. [Pruebas unitarias propuestas](#14-pruebas-unitarias-propuestas)
15. [Pruebas feature propuestas](#15-pruebas-feature-propuestas)
16. [Riesgos técnicos](#16-riesgos-técnicos)
17. [Decisiones pendientes](#17-decisiones-pendientes)
18. [Decisiones bloqueantes (resueltas)](#18-decisiones-bloqueantes-resueltas)
19. [Qué queda fuera del MVP](#19-qué-queda-fuera-del-mvp)
20. [Orden recomendado de implementación](#20-orden-recomendado-de-implementación)
21. [Supuestos importantes usados](#21-supuestos-importantes-usados)
22. [Decisiones aprobadas (registro)](#22-decisiones-aprobadas-registro)
23. [Identidad institucional y branding (Fase 1.1)](#23-identidad-institucional-y-branding-fase-11)
24. [ETAPA 2 — Control Escolar (decisión aprobada 02/10/2026)](#24-etapa-2--control-escolar-decisión-aprobada-02102026)

---

## 1. Objetivo y alcance del MVP

### Objetivo

Construir un **monolito Laravel sencillo y mantenible** que cubra el proceso completo de **admisión e inscripción** de un alumno, desde la captura de sus datos personales hasta la inscripción completada con número de control asignado, manteniendo **trazabilidad** de las acciones críticas y **control de acceso** por roles.

### Alcance (dentro)

- Registro y expediente completo del alumno: datos personales, académicos, de contacto, contacto de emergencia e información médica básica (**acceso restringido**, ver §8).
- Registro de inscripción a un programa/licenciatura en un periodo escolar.
- Registro de cargos: concepto de **INSCRIPCION** (obligatorio para completar) y **MENSUALIDADES** (obligaciones futuras, no bloqueantes).
- Convenio o promoción simple, con **aprobación por ADMINISTRACION** y trazabilidad.
- Registro de pago, validación de pago, generación de folio y **comprobante de pago en PDF** (no fiscal).
- Recepción y validación de documentos, con rechazos motivados.
- Registro de observaciones o faltantes.
- Asignación de número de control e inscripción completada.
- Administración básica: usuarios y roles, programas, periodos, tipos de documento, convenios.
- **Dashboard operativo mínimo** (contadores, sin gráficas ni BI).
- Historial/auditoría de cambios importantes.
- Pruebas automáticas de los flujos principales, desarrolladas **junto con cada módulo**.

### Alcance (fuera)

Ver sección [19. Qué queda fuera del MVP](#19-qué-queda-fuera-del-mvp).

### Principios rectores

- Monolito Laravel sencillo, **sin microservicios** y **sin API separada**.
- **Sin React/Vue** salvo que sea estrictamente necesario (no lo es: Livewire + Blade + Tailwind cubre la UI).
- Priorizar **simplicidad, seguridad, trazabilidad y facilidad de mantenimiento**.
- Claves foráneas correctas, validación de entrada, transacciones en procesos multi-tabla.
- Evitar duplicidad de lógica: **un solo estilo** por capa (ver §10).
- No crear tablas ni abstracciones innecesarias.
- Soft deletes **donde tenga sentido** (no en tablas financieras ni en tablas append-only).
- Compatible con buenas prácticas de Laravel actuales.
- **No agregar funcionalidades fuera del MVP.**

---

## 2. Módulos del sistema

| # | Módulo | Contenido | Rol protagonista |
|---|--------|-----------|------------------|
| 1 | **Acceso y usuarios** | Login, gestión de usuarios, roles, bloqueo de cuentas | ADMIN |
| 2 | **Alumnos (expediente)** | Datos personales, contactos (tutor y emergencia), información médica (acceso restringido) | ADMISIONES |
| 3 | **Catálogos académicos** | Programas/licenciaturas, periodos escolares, tipos de documento, convenios | ADMIN |
| 4 | **Inscripción (wizard + flujo)** | Pasos 1–6 del registro, máquina de estados, revisión final | ADMISIONES / CONTROL_ESCOLAR |
| 5 | **Conceptos y mensualidades** | Generación automática de cargos: INSCRIPCION + N MENSUALIDADES | ADMISIONES / ADMIN |
| 6 | **Convenios / promociones** | Aplicación (ADMISIONES) y **aprobación** (ADMINISTRACION) con trazabilidad | ADMISIONES → ADMINISTRACION |
| 7 | **Pagos** | Registro, validación, folio, rechazo con motivo y **comprobante PDF** | ADMISIONES → ADMINISTRACION |
| 8 | **Documentos** | Recepción (carga), validación, rechazo, verificación de obligatorios | ADMISIONES → CONTROL_ESCOLAR |
| 9 | **Observaciones / faltantes** | Notas abiertas o resueltas sobre una inscripción | Todos los roles |
| 10 | **Auditoría e historial** | Quién hizo qué, cuándo, antes y después | Solo lectura |
| 11 | **Panel operativo y listados** | Dashboard de contadores + búsqueda/filtrado de inscripciones por estatus y periodo | Todos los roles |

> No se propone módulo de alumnos con login propio, calificaciones, grupos, horarios ni reportes avanzados.

---

## 3. Tablas MySQL necesarias

Se proponen **15 tablas**:

1. `users`
2. `alumnos`
3. `contactos`
4. `informacion_medicas`
5. `programas`
6. `periodos`
7. `convenios`
8. `inscripciones`
9. `conceptos_pago`
10. `pagos`
11. `tipos_documento`
12. `documentos`
13. `observaciones`
14. `auditoria`
15. `consecutivos` *(control de concurrencia de números secuenciales — P2)*

**Nota sobre roles:** no existen tablas `roles` ni `permissions`. Los roles viven en `users.rol` (decisión aprobada D1).

### Convenciones de esquema

- `id` BIGINT UNSIGNED autoincremental como PK en todas las tablas.
- `timestamps` (`created_at`, `updated_at`) salvo donde se indique.
- Charset `utf8mb4_unicode_ci`.
- Montos en **`DECIMAL(10,2)`**, nunca `FLOAT`/`DOUBLE`.
- Los estados/tipos se almacenan como `VARCHAR` y se protegen con **PHP Backed Enums**. Se evitan los `ENUM` nativos de MySQL por lo engorrosos que son de migrar.
- Todas las FK tienen `onDelete` explícito (`cascade` o `restrict`).
- Índices compuestos en las columnas de filtrado frecuente (`inscripciones.estatus`, `inscripciones.periodo_id`).

---

## 4. Campos principales de cada tabla

Convención: `unique` = índice único; `nullable` = admite NULL; *(snapshot)* = valor copiado al momento del hecho para no depender de catálogos futuros.

### 4.1 `users`

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `name` | varchar(255) | not null | |
| `email` | varchar(255) | **unique**, not null | login |
| `password` | varchar(255) | not null | hash nativo Laravel, `hidden` |
| `rol` | varchar(30) | not null | `ADMIN \| ADMISIONES \| ADMINISTRACION \| CONTROL_ESCOLAR` (BackedEnum) |
| `activo` | boolean | not null, default true | bloqueo lógico de cuenta |
| `last_login_at` | timestamp | nullable | |
| `remember_token` | | | |
| `deleted_at` | timestamp | nullable | **soft delete** |

> **Un único rol por usuario** (D1 aprobado). ADMIN realiza todas las acciones vía `Gate::before`.

### 4.2 `alumnos`

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `numero_control` | char(8) | **unique**, nullable | formato **`YYYY####`** (ej. `20260001`); se asigna al completar la **primera** inscripción y no cambia (D3) |
| `nombre` | varchar(100) | not null | |
| `apellidos` | varchar(100) | not null | |
| `curp` | char(18) | **unique**, not null | valida formato en app |
| `fecha_nacimiento` | date | not null | |
| `sexo` | varchar(10) | not null | BackedEnum `Sexo` (**`MASCULINO` \| `FEMENINO`**, valores aprobados en Fase 4) |
| `nacionalidad` | varchar(60) | not null, default `Mexicana` | |
| `correo` | varchar(150) | nullable | |
| `telefono` | varchar(20) | nullable | |
| `calle` | varchar(150) | nullable | domicilio |
| `colonia` | varchar(120) | nullable | |
| `cp` | varchar(10) | nullable | |
| `municipio` | varchar(120) | nullable | |
| `estado` | varchar(120) | nullable | |
| `pais` | varchar(80) | not null, default `México` | |
| `creado_por` | bigint | FK → `users.id` | quién lo registró |
| `deleted_at` | timestamp | nullable | **soft delete** |

> El número de control es un **atributo del alumno**, no una tabla. En inscripciones posteriores se conserva el mismo valor.

### 4.3 `contactos` (cubre el paso 3 "Datos de contacto" y el paso 4 "Contacto de emergencia")

Una sola tabla con `tipo` evita duplicar columnas `tutor_*` y `emergencia_*`.

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `alumno_id` | bigint | FK → `alumnos.id`, **cascade** | |
| `tipo` | varchar(20) | not null | `TUTOR \| EMERGENCIA` (BackedEnum) |
| `nombre` | varchar(150) | not null | nombre completo |
| `parentesco` | varchar(60) | not null | |
| `telefono` | varchar(20) | not null | |
| `telefono_alt` | varchar(20) | nullable | |
| `correo` | varchar(150) | nullable | |
| `notas` | text | nullable | |
| `created_at` / `updated_at` | | | |

*Sin soft delete:* los contactos pertenecen al expediente y se gestionan con el alumno.

### 4.4 `informacion_medicas` (relación 1:1 con `alumnos`) — **información sensible**

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `alumno_id` | bigint | **unique**, FK → `alumnos.id`, **cascade** | garantiza 1:1 |
| `tipo_sangre` | varchar(5) | nullable | BackedEnum `TipoSangre` (**`A+`, `A-`, `B+`, `B-`, `AB+`, `AB-`, `O+`, `O-`**, valores aprobados en Fase 4; `null` = sin capturar) |
| `alergias` | text | nullable | |
| `condiciones` | text | nullable | enfermedades/condiciones previas |
| `medicamentos` | text | nullable | |
| `tiene_discapacidad` | boolean | not null, default false | |
| `detalle_discapacidad` | text | nullable | |
| `actualizado_por` | bigint | FK → `users.id` | |
| `created_at` / `updated_at` | | | |

**Acceso restringido (ajuste de seguridad aprobado):** ADMIN ✔, ADMISIONES ✔, CONTROL_ESCOLAR (lectura, solo en el contexto de revisión final), **ADMINISTRACION ✖**. Ver `InformacionMedicaPolicy` en §8 y §10.

### 4.5 `programas`

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `clave` | varchar(20) | **unique**, not null | identificador **INTERNO** del sistema (ej. `DER`); **no es RVOE** |
| `nombre` | varchar(150) | not null | licenciatura/programa |
| `duracion_periodos` | smallint unsigned | **nullable** | duración total del programa en **periodos académicos** (no semestres); sin fuente institucional se deja vacío y el ADMIN lo captura desde el CRUD (ajuste de extensibilidad, evita datos falsos) |
| `activo` | boolean | not null, default true | |
| `deleted_at` | timestamp | nullable | **soft delete** |

### 4.6 `periodos`

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `clave` | varchar(20) | **unique**, not null | ej. `2026-1` |
| `nombre` | varchar(100) | not null | |
| `fecha_inicio` | date | not null | |
| `fecha_fin` | date | not null | |
| `activo` | boolean | not null, default true | |
| `cerrado_at` | timestamp | nullable | al tener valor: **bloquea nuevas inscripciones y modificaciones** de ese periodo (RB-13); ADMIN visualiza histórico. **Semántica exclusiva de inscripción:** NO es un cierre académico global para módulos futuros (calificaciones, asistencia, grupos), que definirán su propio criterio (riesgo R2 del roadmap) |
| `deleted_at` | timestamp | nullable | **soft delete** |

### 4.7 `convenios` (catálogo)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `clave` | varchar(20) | **unique**, not null | |
| `nombre` | varchar(150) | not null | |
| `tipo` | varchar(15) | not null | `PORCENTAJE \| MONTO` (BackedEnum) |
| `valor` | decimal(10,2) | not null | 10.00 = 10%, o monto en MXN |
| `fecha_inicio` | date | nullable | vigencia |
| `fecha_fin` | date | nullable | vigencia |
| `activo` | boolean | not null, default true | |
| `notas` | text | nullable | |
| `deleted_at` | timestamp | nullable | **soft delete** |

> El **estado de aprobación no vive aquí** sino en `inscripciones` (ver §4.8), porque es propio de cada aplicación del convenio.

### 4.8 `inscripciones` (tabla núcleo)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | identificador interno de la inscripción (**no hay folio de inscripción**, D7) |
| `alumno_id` | bigint | FK → `alumnos.id`, restrict | |
| `periodo_id` | bigint | FK → `periodos.id`, restrict | |
| `programa_id` | bigint | FK → `programas.id`, restrict | |
| `estatus` | varchar(30) | not null, default `DRAFT` | ver §7 |
| `monto_inscripcion` | decimal(10,2) | not null | *(snapshot)* capturado al crear la inscripción (D8) |
| `monto_mensualidad` | decimal(10,2) | not null | *(snapshot)* capturado al crear la inscripción (D8) |
| **Convenio (con aprobación)** | | | |
| `convenio_id` | bigint | FK → `convenios.id`, nullable, restrict | máx. **1** por inscripción (D6) |
| `convenio_estatus` | varchar(25) | nullable | `PENDIENTE_APROBACION \| APROBADO \| RECHAZADO` |
| `convenio_aplicado_por` | bigint | FK → `users.id`, nullable | quién lo aplicó (ADMISIONES/ADMIN) |
| `convenio_aplicado_at` | timestamp | nullable | |
| `convenio_aprobado_por` | bigint | FK → `users.id`, nullable | quién lo aprobó (ADMINISTRACION/ADMIN) |
| `convenio_aprobado_at` | timestamp | nullable | |
| `convenio_motivo` | text | nullable | motivo si fue `RECHAZADO` |
| `monto_descuento` | decimal(10,2) | not null, default 0 | *(snapshot)* **solo aplica si `convenio_estatus = APROBADO`** |
| **Hitos** | | | |
| `datos_completados_at` | timestamp | nullable | paso 1–6 completos |
| `pagos_validados_at` | timestamp | nullable | pago del concepto **INSCRIPCION** validado |
| `documentos_validados_at` | timestamp | nullable | |
| `finalizada_at` | timestamp | nullable | llegada a `ENROLLED` |
| **Responsables** | | | |
| `creado_por` | bigint | FK → `users.id` | quién creó la inscripción |
| `validada_por` | bigint | FK → `users.id`, nullable | revisión final |
| `cancelada_por` | bigint | FK → `users.id`, nullable | |
| `cancelada_at` | timestamp | nullable | |
| `cancelacion_motivo` | text | nullable | obligatorio si se cancela |
| `deleted_at` | timestamp | nullable | **soft delete** |
| **UNIQUE** | | `(alumno_id, periodo_id)` | **aprobado (D2)**: un alumno no puede tener 2 inscripciones en el mismo periodo |

*Índices:* `(estatus)`, `(periodo_id, estatus)`, `(convenio_estatus)`.

### 4.9 `conceptos_pago` (generación automática de cargos — D4)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `inscripcion_id` | bigint | FK → `inscripciones.id`, **cascade** | |
| `tipo` | varchar(20) | not null | **`INSCRIPCION` \| `MENSUALIDAD` \| `OTRO`** (BackedEnum) |
| `numero` | smallint | nullable | mes 1..N para mensualidades |
| `concepto` | varchar(150) | not null | ej. `Mensualidad 3 - Ago 2026` |
| `monto` | decimal(10,2) | not null | |
| `descuento` | decimal(10,2) | not null, default 0 | por convenio **aprobado** |
| `monto_total` | decimal(10,2) | not null | `monto - descuento` |
| `fecha_vencimiento` | date | not null | |
| `estatus` | varchar(20) | not null, default `PENDIENTE` | `PENDIENTE \| PAGADO \| CANCELADO` |
| `pagado_at` | timestamp | nullable | |
| `created_at` / `updated_at` | | | |

**Semántica clave (corrección aprobada):**
- `tipo = INSCRIPCION` → **concepto obligatorio** para completar la inscripción.
- `tipo = MENSUALIDAD` → **obligación futura**: puede quedar `PENDIENTE` indefinidamente sin impedir que el alumno llegue a `ENROLLED`.
- Generados **automáticamente** por `InscripcionService` (no captura manual).
- Si `monto_inscripcion = 0`, el concepto `INSCRIPCION` nace directamente `PAGADO` para no bloquear el flujo.

**Decisiones UVZ aprobadas (registradas en la Fase 7):**
- **UVZ opera actualmente por CUATRIMESTRES.** Cada inscripción genera **exactamente 4** conceptos `MENSUALIDAD`: los cuatro meses del cuatrimestre, derivados del mes de `periodos.fecha_inicio` (mes 1 = mes de inicio, +1, +2, +3). Los vencimientos se derivan del **PERIODO**, nunca de la fecha de captura.
- **Día institucional de vencimiento: el día 10 de cada mes**, centralizado en **`config/escolar.php`** (`escolar.dia_vencimiento_mensualidad`); **no** se hardcodea en servicios ni vistas y **no** vive en `.env` (es regla de negocio). Si el día configurado no existe en un mes futuro, se usa el **último día válido** del mes.
- **Los convenios/promociones aplican únicamente al concepto `INSCRIPCION`.** Las mensualidades **NO** reciben descuento de convenio en el MVP: su `descuento` nace y permanece en 0.
- `descuento` del concepto `INSCRIPCION` = `inscripciones.monto_descuento`, **congelado** al aprobar el convenio: `PORCENTAJE` → `monto_inscripcion * valor / 100`; `MONTO` → `min(valor, monto_inscripcion)`; redondeo monetario a 2 decimales y **nunca** un `monto_total` negativo (`monto_total = max(monto - descuento, 0)`).
- Vencimiento del concepto `INSCRIPCION`: **`periodos.fecha_inicio`** (solución mínima técnicamente consistente: el documento no fija una fecha institucional distinta y no se inventó una regla de negocio nueva).
- `mensualidades.pendientes` **NO bloquean** `ENROLLED` (RB-8).
- **NO** se renombra `duracion_periodos` a `duracion_cuatrimestres`: la abstracción genérica se conserva.

*Sin `UNIQUE` compuesto:* los conceptos los genera el servicio dentro de una transacción; un unique estricto con `numero` nullable resulta incómodo en MySQL.

### 4.10 `pagos` (registro, validación, folio y comprobante)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `concepto_pago_id` | bigint | FK → `conceptos_pago.id`, restrict | |
| `monto` | decimal(10,2) | not null | **un pago por concepto** (D5): sin parciales ni anticipos |
| `fecha_pago` | date | not null | fecha del hecho, no del sistema |
| `metodo` | varchar(20) | not null | `EFECTIVO \| TRANSFERENCIA \| TARJETA \| OTRO` |
| `referencia` | varchar(100) | nullable | nº de operación/transferencia |
| `comprobante_ruta` | varchar(255) | nullable | comprobante **cargado**, disco privado |
| `comprobante_nombre_original` | varchar(255) | nullable | nombre original del archivo |
| `estatus` | varchar(20) | not null, default `PENDIENTE` | `PENDIENTE \| VALIDADO \| RECHAZADO` |
| `folio` | varchar(30) | **unique**, nullable | **se genera al validar** |
| `validado_por` | bigint | FK → `users.id`, nullable | |
| `validado_at` | timestamp | nullable | |
| `motivo_rechazo` | text | nullable | obligatorio si `RECHAZADO` |
| `registrado_por` | bigint | FK → `users.id` | |
| `notas` | text | nullable | |
| `created_at` / `updated_at` | | | |

**Sin soft deletes:** los registros financieros no se borran; se rechazan o cancelan con motivo y quedan en auditoría.

**Folio (Fase 8, decisión aprobada):** formato exacto **`PAG-YYYYMMDD-########`** (ej. `PAG-20261001-00000127`). `YYYYMMDD` corresponde a la fecha/hora de **validación** con el timezone configurado de la aplicación (NO `fecha_pago`); `########` es el `id` del pago con padding a **8 dígitos**. El ID garantiza la unicidad lógica y el `UNIQUE(folio)` de BD es la defensa final; longitud 21 ≤ `varchar(30)`. **Sin** tabla `consecutivos`, **sin** `MAX()` y **sin** reutilizar `NumeroControlService`. El folio es **inmutable** una vez asignado y un pago `RECHAZADO` **jamás** lo recibe.

**Reintentos (Fase 8, decisión aprobada):** "un pago por concepto" significa **un solo intento activo**: a lo sumo UN pago `PENDIENTE` a la vez (controlado con `lockForUpdate()` sobre el concepto dentro de la transacción de registro) y UN pago `VALIDADO` que liquide el concepto. Los intentos `RECHAZADO` se conservan como historial —nunca se sobrescriben ni se eliminan, incluido su comprobante— y **no bloquean** un nuevo intento posterior. Por eso la tabla **NO** tiene `UNIQUE(concepto_pago_id)`: impediría conservar los intentos rechazados.

**Comprobante PDF (aprobado):** se genera **bajo demanda** a partir de los datos ya validados (no se persiste una copia), solo para pagos `VALIDADO`. Campos mínimos: alumno, programa, concepto, monto, forma de pago, referencia, fecha, folio. **Sin facturación fiscal.**

### 4.11 `tipos_documento` (catálogo, poblado por seed)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `clave` | varchar(30) | **unique**, not null | ej. `ACTA_NAC` |
| `nombre` | varchar(120) | not null | ej. `Acta de nacimiento` |
| `obligatorio` | boolean | not null, default true | ¿bloquea la revisión final? |
| `activo` | boolean | not null, default true | |
| `deleted_at` | timestamp | nullable | **soft delete** |

### 4.12 `documentos` (pasos 13 y 14: recepción y validación de documentos)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `inscripcion_id` | bigint | FK → `inscripciones.id`, **cascade** | |
| `tipo_documento_id` | bigint | FK → `tipos_documento.id`, restrict | |
| `ruta` | varchar(255) | not null | disco **privado** |
| `nombre_original` | varchar(255) | not null | |
| `mime` | varchar(100) | not null | validado al subir |
| `tamano_kb` | int | not null | máx. **10 MB** (10240 KB) |
| `estatus` | varchar(20) | not null, default `PENDIENTE` | `PENDIENTE \| ACEPTADO \| RECHAZADO` |
| `validado_por` | bigint | FK → `users.id`, nullable | |
| `validado_at` | timestamp | nullable | |
| `motivo_rechazo` | text | nullable | obligatorio si `RECHAZADO` |
| `subido_por` | bigint | FK → `users.id` | |
| `created_at` / `updated_at` | | | |
| **UNIQUE** | | `(inscripcion_id, tipo_documento_id)` | un registro por tipo por inscripción (se reemplaza el archivo) |

**Formatos permitidos:** `PDF`, `JPG`, `JPEG`, `PNG`. **Tamaño máximo:** 10 MB por archivo.

### 4.13 `observaciones` (paso 15: observaciones o faltantes)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `inscripcion_id` | bigint | FK → `inscripciones.id`, **cascade** | |
| `tipo` | varchar(20) | not null | `FALTANTE \| DOCUMENTO \| PAGO \| GENERAL` |
| `detalle` | text | not null | |
| `estatus` | varchar(20) | not null, default `ABIERTA` | `ABIERTA \| RESUELTA` |
| `creada_por` | bigint | FK → `users.id` | |
| `resuelta_por` | bigint | FK → `users.id`, nullable | |
| `resuelta_at` | timestamp | nullable | |
| `created_at` / `updated_at` | | | |

*Append-only:* **sin soft delete** (se resuelven, no se borran).

### 4.14 `auditoria` (trazabilidad / historial)

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `auditable_type` | varchar(150) | not null | morph (ej. `Inscripcion`, `Alumno`, `Pago`) |
| `auditable_id` | bigint | not null | índice compuesto con el tipo |
| `user_id` | bigint | FK → `users.id`, nullable | quién realizó la acción |
| `accion` | varchar(60) | not null | ej. `estado_cambiado`, `pago_validado`, `convenio_aprobado` |
| `estado_anterior` | varchar(30) | nullable | |
| `estado_nuevo` | varchar(30) | nullable | |
| `datos` | json | nullable | payload del cambio |
| `ip` | varchar(45) | nullable | IPv4/IPv6 |
| `user_agent` | varchar(255) | nullable | |
| `created_at` | timestamp | | **sin `updated_at`** (append-only) |

*Índice:* `(auditable_type, auditable_id)`.

### 4.15 `consecutivos` (tabla mínima de concurrencia — P2 aprobado)

Controla los números secuenciales de forma segura ante concurrencia. **No se calcula el consecutivo con `MAX(numero_control)`.**

| Campo | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `tipo` | varchar(30) | not null | ej. `NUMERO_CONTROL` (BackedEnum) |
| `anio` | smallint | not null | año del consecutivo |
| `ultimo_valor` | int unsigned | not null, default 0 | último valor emitido |
| `created_at` / `updated_at` | | | |
| **UNIQUE** | | `(tipo, anio)` | una fila por tipo y año |

**Uso (`NumeroControlService`), todo dentro de la transacción de finalización:**

1. iniciar transacción;
2. obtener la fila del año con `lockForUpdate()`;
3. incrementar `ultimo_valor`;
4. generar `YYYY####` con 4 dígitos (`str_pad`);
5. asignarlo al alumno;
6. mantener `UNIQUE` en `alumnos.numero_control` (última línea de defensa);
7. completar todo dentro de la misma transacción de finalización a `ENROLLED`.

---

## 5. Relaciones entre tablas (ER)

```
users 1 ──── n alumnos.creado_por
users 1 ──── n inscripciones.creado_por / validada_por / cancelada_por
users 1 ──── n inscripciones.convenio_aplicado_por / convenio_aprobado_por
users 1 ──── n pagos.registrado_por / validado_por
users 1 ──── n documentos.subido_por / validado_por
users 1 ──── n observaciones.creada_por / resuelta_por
users 1 ──── n informacion_medicas.actualizado_por
users 1 ──── n auditoria.user_id

alumnos 1 ──── n contactos                  (onDelete: cascade)
alumnos 1 ──── 1 informacion_medicas        (unique en informacion_medicas.alumno_id)
alumnos 1 ──── n inscripciones              (onDelete: restrict)
alumnos 1 ──── 1 numero_control             (atributo de alumnos, no tabla)

programas 1 ── n inscripciones              (onDelete: restrict)
periodos  1 ── n inscripciones              (onDelete: restrict)
convenios 1 ── n inscripciones              (nullable, onDelete: restrict)

inscripciones 1 ── n conceptos_pago         (cascade)
inscripciones 1 ── n documentos             (cascade)
inscripciones 1 ── n observaciones          (cascade)
inscripciones 1 ── n auditoria              (vía morph)

conceptos_pago 1 ── n pagos                 (onDelete: restrict)
tipos_documento 1 ─ n documentos            (onDelete: restrict)
```

**Notas:**
- `restrict` en las FK hacia catálogos y hacia `conceptos_pago`/`inscripciones` para impedir borrados accidentales de registros con historial.
- El borrado físico de alumnos con inscripciones queda bloqueado por `restrict`; el borrado lógico (soft delete) sí está permitido y no dispara la FK.
- Los contactos y la información médica se eliminan en cascada cuando se elimina físicamente un alumno.

---

## 6. Relaciones Eloquent

### `Alumno`
- `hasMany(Contacto::class)` — contactos del alumno.
- `hasOne(InformacionMedica::class)` — información médica (**acceso restringido**, §8).
- `hasMany(Inscripcion::class)` — historial de inscripciones.
- `belongsTo(User::class, 'creado_por')` — quién lo registró.

### `Contacto`
- `belongsTo(Alumno::class)`.

### `InformacionMedica`
- `belongsTo(Alumno::class)`.

### `Inscripcion`
- `belongsTo(Alumno::class)`
- `belongsTo(Programa::class)`
- `belongsTo(Periodo::class)`
- `belongsTo(Convenio::class)` — nullable.
- `belongsTo(User::class, 'convenio_aplicado_por')` — quién aplicó el convenio.
- `belongsTo(User::class, 'convenio_aprobado_por')` — quién lo aprobó.
- `hasMany(ConceptoPago::class)`
- `hasMany(Documento::class)`
- `hasMany(Observacion::class)`
- `morphMany(Auditoria::class, 'auditable')` — historial de la inscripción.
- `belongsTo(User::class, 'creado_por')`
- `belongsTo(User::class, 'validada_por')`
- `belongsTo(User::class, 'cancelada_por')`
- *Relación de dominio:* `conceptoInscripcion()` → primer `ConceptoPago` con `tipo = INSCRIPCION` (usado por las guardas de finalización).

### `ConceptoPago`
- `belongsTo(Inscripcion::class)`
- `hasMany(Pago::class)`
- *Scopes:* `scopeInscripcion($q)` (`tipo = INSCRIPCION`), `scopeMensualidades($q)`, `scopePendientes($q)`.
- *Accessor:* `saldoPendiente` (`monto_total` menos pagos `VALIDADO`).

### `Pago`
- `belongsTo(ConceptoPago::class)`
- `belongsTo(User::class, 'registrado_por')`
- `belongsTo(User::class, 'validado_por')`
- *Scope:* `scopeValidado($query)` para filtrar pagos aplicados.
- *Accessor:* `esRequeridoParaInscripcion()` → `$this->concepto->tipo === TipoConcepto::INSCRIPCION` (define si su validación mueve el estado del proceso).

### `Documento`
- `belongsTo(Inscripcion::class)`
- `belongsTo(TipoDocumento::class)`
- `belongsTo(User::class, 'subido_por')`
- `belongsTo(User::class, 'validado_por')`

### `Observacion`
- `belongsTo(Inscripcion::class)`
- `belongsTo(User::class, 'creada_por')`
- `belongsTo(User::class, 'resuelta_por')`

### `Auditoria`
- `morphTo('auditable')`
- `belongsTo(User::class)`

**Prácticas comunes a todos los modelos:** `$fillable` explícito (nunca `$guarded = []`), `$casts` para enums/fechas/decimales, `hidden` en `password`, rutas de archivos y tokens.

---

## 7. Estados y transiciones de la inscripción

### 7.1 Estados

| Estado | Significado |
|---|---|
| `DRAFT` | Borrador: datos incompletos |
| `DATA_COMPLETE` | Datos de los pasos 1–6 completos |
| `PENDING_PAYMENT` | Cargos generados, esperando el **pago del concepto INSCRIPCION** |
| `PAYMENT_REVIEW` | Pago del concepto **INSCRIPCION** capturado, esperando validación |
| `PAYMENT_VALIDATED` | Pago del concepto **INSCRIPCION** validado y folio emitido |
| `PENDING_DOCUMENTS` | Esperando recepción de documentos |
| `DOCUMENT_REVIEW` | Documentos subidos, esperando validación |
| `FINAL_REVIEW` | Documentos validados, esperando revisión final |
| `ENROLLED` | **Inscripción completada** (estado terminal) |
| `CANCELLED` | Inscripción cancelada (estado terminal) |

### 7.2 Diagrama de transiciones (versión final)

```
DRAFT ──────────────► DATA_COMPLETE            (ADMISIONES · pasos 1–6 completos)
DATA_COMPLETE ──────► PENDING_PAYMENT          (ADMISIONES · genera cargos; exige convenio
                                                RESUELTO: APROBADO / RECHAZADO / ninguno)

PENDING_PAYMENT ────► PAYMENT_REVIEW           (ADMISIONES · captura el pago del
                                                concepto INSCRIPCION)

PAYMENT_REVIEW ─────► PAYMENT_VALIDATED        (ADMINISTRACION · valida ese pago + folio)
        │
        └────────────► PENDING_PAYMENT          (ADMINISTRACION · rechaza, con motivo)

PAYMENT_VALIDATED ──► PENDING_DOCUMENTS        (automático al validar el pago INSCRIPCION)

PENDING_DOCUMENTS ──► DOCUMENT_REVIEW          (ADMISIONES · sube todo lo obligatorio)

DOCUMENT_REVIEW ────► FINAL_REVIEW             (CONTROL_ESCOLAR · valida documentos)
        │
        └────────────► PENDING_DOCUMENTS        (CONTROL_ESCOLAR · rechaza, con motivo)

FINAL_REVIEW ───────► ENROLLED                 (CONTROL_ESCOLAR · asigna NC, transacción)
        │
        └────────────► PENDING_DOCUMENTS        (retroceso justificado, con motivo)

Cualquier estado NO final ──► CANCELLED        (solo ADMIN, motivo obligatorio;
                                                prohibido desde ENROLLED)
```

### 7.3 Reglas de la máquina de estados

1. Las transiciones permitidas viven en **una única constante** (`TRANSICIONES`) — matriz `desde[] → [hasta[]]`. Prohibido saltar estados.
2. Todo retroceso requiere **motivo explícito** y queda registrado en `auditoria`.
3. Toda transición ocurre **dentro de una transacción de BD** y escribe un registro en `auditoria` con usuario, estado anterior y nuevo.
4. `CANCELLED` y `ENROLLED` son **estados terminales**: sin salida.
5. Las guardas de negocio (§11) se verifican **antes** de ejecutar la transición; si no se cumplen, la transacción aborta.
6. La máquina de estados es la **única vía** para modificar `inscripciones.estatus`. Ningún controlador ni componente Livewire asigna `estatus` directamente.
7. **Corrección de alcance (aprobada):** el ciclo `PENDING_PAYMENT → PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` corresponde **exclusivamente al concepto `INSCRIPCION`** (el requerido para completar la inscripción). **Capturar o validar un pago de `MENSUALIDAD` NO modifica `inscripciones.estatus` en ningún caso.**
8. **La aprobación de un convenio NO es un estado de la inscripción:** es un sub-proceso (`inscripciones.convenio_estatus`) que debe quedar resuelto antes de `PENDING_PAYMENT`.
9. Los estados no cambian por el simple paso del tiempo (una mensualidad vencida no altera el proceso).
10. **Alcance exclusivo (Etapa 2, decisión aprobada C2/C3):** esta máquina gobierna únicamente el proceso de **ADMISIÓN / INGRESO** (`inscripciones`). La matrícula académica por periodo de la Etapa 2 (`matriculas_periodo`) tendrá su **propia máquina de estados**, separada de `InscripcionEstatus`, sin transiciones cruzadas (ver §24). Esta máquina, `RB-8`, `ENROLLED`/`CANCELLED` terminales y el resto de reglas §10 permanecen **intactos**.

---

## 8. Roles y permisos

### 8.1 Enfoque elegido (D1 aprobado)

- **Sin paquete** `spatie/laravel-permission`.
- **Una columna** `users.rol` (BackedEnum) con **un rol por usuario**: `ADMIN`, `ADMISIONES`, `ADMINISTRACION`, `CONTROL_ESCOLAR`.
- **Laravel Gates + Policies** para la autorización:
  - `Gate::before(fn ($user) => $user->esAdmin())` → **ADMIN realiza todas las acciones**.
  - Policies por modelo: `AlumnoPolicy`, `InscripcionPolicy`, `PagoPolicy`, `DocumentoPolicy`, `ObservacionPolicy`, `UsuarioPolicy`, **`InformacionMedicaPolicy`**.
  - Gates con nombre por capacidad: `alumno.create`, `inscripcion.create`, `convenio.aprobar`, `pago.validar`, `documento.validar`, `inscripcion.finalizar`, `usuario.manage`, `informacionMedica.view`.
- **Middleware de ruta:** `role:ADMIN` (etc.) para agrupaciones de rutas.
- **`$this->authorize(...)` obligatorio** en cada método público de los componentes Livewire (Livewire no aplica policies automáticamente).
- **Protección en capas:** policy server-side + Blade (`@can`) para ocultar controles + validación server-side.

### 8.2 Matriz de capacidades

| Capacidades | ADMIN | ADMISIONES | ADMINISTRACION | CONTROL_ESCOLAR |
|---|:-:|:-:|:-:|:-:|
| Gestionar usuarios, catálogos (programas, periodos, tipos de documento, convenios) | ✔ | – | – | – |
| CRUD alumnos, contactos, información médica | ✔ | ✔ | – | – |
| **Leer información médica** | ✔ | ✔ | **✖** | ✔ (lectura, solo en revisión final) |
| Crear/editar inscripción, wizard hasta `DATA_COMPLETE` | ✔ | ✔ | – | – |
| Generar cargos / mensualidades | ✔ | ✔ | – | – |
| **Aplicar convenio** (queda `PENDIENTE_APROBACION`) | ✔ | ✔ | – | – |
| **Aprobar / rechazar convenio** | ✔ | – | ✔ | – |
| Registrar pago (queda `PENDIENTE`) | ✔ | ✔ | ✔ | – |
| **Validar / rechazar pago + folio** | ✔ | – | ✔ | – |
| **Descargar comprobante PDF de pago** | ✔ | ✔ | ✔ | ✔ |
| Cargar documentos | ✔ | ✔ | ✔ | ✔ |
| **Validar / rechazar documentos** | ✔ | – | – | ✔ |
| Crear observaciones | ✔ | ✔ | ✔ | ✔ |
| Resolver observaciones | ✔ | ✔ | ✔ | ✔ |
| **Revisión final, asignar número de control, `ENROLLED`** | ✔ | – | – | ✔ |
| Cancelar inscripción | ✔ | – | – | – |
| Ver auditoría | ✔ | – | – | – |
| Ver dashboard operativo y listados | ✔ | ✔ | ✔ | ✔ |
| Lectura de alumnos / inscripciones / pagos / documentos | ✔ | ✔ | ✔ | ✔ |

**Separación de duties clave (four-eyes principle):**
- ADMISIONES **aplica** el convenio → ADMINISTRACION lo **aprueba**.
- ADMISIONES **captura** el pago → ADMINISTRACION lo **valida**.
- ADMISIONES **sube** los documentos → CONTROL_ESCOLAR los **valida** y hace la **revisión final**.
- **ADMINISTRACION no accede a información médica** (ni siquiera para validar pagos: no la necesita).

---

## 9. Estructura recomendada del proyecto Laravel

```
app/
├── Enums/
│   ├── RolUsuario.php
│   ├── InscripcionEstatus.php
│   ├── EstatusPago.php
│   ├── EstatusDocumento.php
│   ├── EstatusConvenio.php             (PENDIENTE_APROBACION | APROBADO | RECHAZADO)
│   ├── TipoConcepto.php                (INSCRIPCION | MENSUALIDAD | OTRO)
│   ├── TipoContacto.php
│   ├── TipoConvenio.php
│   ├── TipoObservacion.php
│   ├── MetodoPago.php
│   └── ...
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── DashboardController.php                     (dashboard operativo mínimo)
│   │   ├── ComprobantePagoController.php               (PDF bajo demanda, authorize)
│   │   └── DescargaArchivosController.php              (descarga privada, authorize)
│   └── Middleware/
│       └── EnsureUserHasRole.php                       (alias: role)
├── Livewire/
│   ├── Alumnos/
│   │   ├── ListaAlumnos.php
│   │   ├── FormAlumno.php
│   │   ├── FormContactos.php
│   │   └── FormInfoMedica.php
│   ├── Inscripciones/
│   │   ├── WizardInscripcion.php                       (pasos 1–6)
│   │   ├── ListaInscripciones.php
│   │   ├── PanelEstados.php
│   │   └── ModalAplicarConvenio.php
│   ├── Pagos/
│   │   ├── ModalRegistrarPago.php
│   │   └── ModalValidarPago.php
│   ├── Documentos/
│   │   └── PanelDocumentos.php
│   ├── Observaciones/
│   │   └── ListaObservaciones.php
│   ├── Dashboard/
│   │   └── PanelOperativo.php                          (4 contadores)
│   └── Catalogos/                                      (programas, periodos, convenios, tipos doc.)
├── Models/
│   ├── Concerns/HasAudit.php
│   └── (Alumno, Contacto, InformacionMedica, Programa, Periodo, Convenio,
│        Inscripcion, ConceptoPago, Pago, TipoDocumento, Documento,
│        Observacion, Auditoria, Consecutivo, User)
├── Policies/
│   ├── AlumnoPolicy.php
│   ├── InformacionMedicaPolicy.php                     (restricción ADMINISTRACION)
│   ├── InscripcionPolicy.php
│   ├── ConvenioPolicy.php                              (aplicar vs aprobar)
│   ├── PagoPolicy.php
│   ├── DocumentoPolicy.php
│   ├── ObservacionPolicy.php
│   └── UsuarioPolicy.php
├── Services/
│   ├── InscripcionService.php
│   ├── ConvenioService.php
│   ├── PagoService.php
│   ├── ComprobantePagoService.php                      (generación PDF)
│   ├── DocumentoService.php
│   ├── NumeroControlService.php
│   ├── AuditoriaService.php
│   ├── ArchivoService.php
│   └── DashboardService.php
└── Providers/
    └── AuthServiceProvider.php                         (definición de Gates)

bootstrap/app.php                                (middleware aliases: 'role')
config/filesystems.php                           (disco 'private' fuera de public/)

database/
├── migrations/
├── seeders/
│   ├── AdminSeeder.php
│   ├── CatalogosSeeder.php                     (semilla de `programas`: los 3 programas UVZ de §23.4; `periodos` sin semilla por no existir fechas institucionales)
│   └── TiposDocumentoSeeder.php

resources/
├── css/app.css
├── js/app.js
└── views/
    ├── layouts/app.blade.php
    ├── livewire/
    │   ├── alumnos/
    │   ├── inscripciones/
    │   ├── pagos/
    │   ├── documentos/
    │   ├── observaciones/
    │   ├── dashboard/
    │   └── catalogos/
    ├── pdf/comprobante-pago.blade.php                       (plantilla del comprobante)
    └── auth/login.blade.php

routes/
├── web.php                                      (rutas protegidas por middleware de rol)
└── ...

tests/
├── Feature/
│   ├── AlumnoTest.php
│   ├── InformacionMedicaTest.php
│   ├── WizardInscripcionTest.php
│   ├── AutorizacionTest.php
│   ├── ConvenioTest.php
│   ├── PagoTest.php
│   ├── RechazoPagoTest.php
│   ├── ComprobantePagoTest.php
│   ├── DocumentoTest.php
│   ├── FinalizacionTest.php
│   ├── CancelacionTest.php
│   ├── PeriodoTest.php
│   ├── AuditoriaTest.php
│   └── DuplicidadTest.php
└── Unit/
    ├── MaquinaEstadosTest.php
    ├── CalculoConvenioTest.php
    ├── GeneracionNumeroControlTest.php
    └── GeneracionFolioTest.php
```

**Decisiones de stack:**
- **Livewire 4**: componentes en `app/Livewire`, vistas en `resources/views/livewire` (actualizado en Fase 1.1; greenfield, sin código previo incompatible).
- **Tailwind CSS** vía Vite. Sin Bootstrap. Sin JS adicional (Alpine viene incluido con Livewire).
- **Archivos** (comprobantes y documentos) en un disco **`private`** fuera de `public/`, servidos por `DescargaArchivosController` con `authorize()` y validación de ruta. **Nunca** en `public/`.
- **Generación de PDF**: `barryvdh/laravel-dompdf` compatible con Laravel 12 (**P1 aprobado**).
- **Pruebas**: Pest + MySQL con **base de datos exclusiva de testing** (`sistema_escolar_testing`, **P3 aprobado**); jamás la de desarrollo ni producción.
- `APP_LOCALE=es_MX`, `timezone=America/Mexico_City`, moneda **MXN**.
- Auth propia con login simple (sin Breeze/Jetstream para evitar dependencias innecesarias del MVP).

---

## 10. Services, Actions, Policies y clases adicionales

### 10.1 Sí se proponen

| Clase | Tipo | Responsabilidad |
|---|---|---|
| `InscripcionService` | Service | Crear borrador, marcar datos completos, **generar cargos automática y distinguible** (INSCRIPCION + MENSUALIDADES), **transicionar estado** (guardas + transacción + auditoría), `finalizar()` |
| `ConvenioService` | Service | Aplicar convenio (ADMISIONES → `PENDIENTE_APROBACION`), aprobar/rechazar (ADMINISTRACION → `APROBADO`/`RECHAZADO` + trazabilidad), aplicar descuento **solo si aprobado** |
| `PagoService` | Service | Registrar pago (intentos, `lockForUpdate`, comprobante a disco privado); **validar** (transacción: pago → `VALIDADO`, folio único, concepto → `PAGADO`); **mover el estado del proceso solo si el concepto es `INSCRIPCION`**; **rechazar** con motivo |
| `FolioPagoService` | Service | Generar el folio `PAG-YYYYMMDD-########` **solo al validar** (fecha de validación con timezone de la app + `id` con padding a 8; sin `consecutivos`, sin `MAX()`, sin `NumeroControlService`) |
| `ComprobantePagoService` | Service | Generar PDF de comprobante **solo** para pagos `VALIDADO` (alumno, programa, concepto, monto, forma de pago, referencia, fecha, folio) |
| `DocumentoService` | Service | Subir archivo (valida MIME/tamaño 10 MB), validar, rechazar con motivo, verificar obligatorios |
| `NumeroControlService` | Service | Generar NC **`YYYY####`** secuencial y único mediante la tabla **`consecutivos`** (`lockForUpdate` + incremento), **una sola vez**, dentro de la transacción final. Nunca con `MAX()` |
| `AuditoriaService` | Service | `registrar(Model, accion, antes, después)` — **único punto de escritura** de la tabla `auditoria` |
| `ArchivoService` | Service | Guardar/eliminar en disco privado, sanitizar nombre, leer metadata, verificar MIME real (`finfo`) |
| `DashboardService` | Service | 4 contadores: inscripciones pendientes, pagos por validar, documentos pendientes, inscripciones completadas |
| `AuthServiceProvider` | Provider | Definición de todos los `Gate` por capacidad |
| Policies (8) | Policy | Autorización por modelo (§8), incluye `InformacionMedicaPolicy` y `ConvenioPolicy` |
| `HasAudit` | Trait (Model) | Integra `AuditoriaService` en los modelos auditables |
| Backed Enums (10) | Enum | Estados, roles, tipos: fuente única de verdad de los valores |
| `EnsureUserHasRole` | Middleware | Protección de rutas por rol (alias `role`) |

### 10.2 No se proponen (y por qué)

| Descartado | Razón |
|---|---|
| **Actions** | Se usaría **un solo estilo**: Services. Mezclar Actions + Services = duplicidad de lógica. Los procesos del MVP (validar pago, finalizar inscripción) son multi-paso y comparten transacción, lo que encaja mejor en un Service. |
| **Repositories / Data Mappers** | Abstracción innecesaria en un monolito pequeño; Eloquent ya es la capa de acceso a datos. |
| **DTOs / FormRequest pesados** | La validación de pasos se hace en Livewire con `$rules`; los controladores usan `FormRequest` simples solo donde existan. |
| **Eventos / Listeners / Jobs** | Sin caso de uso real en el MVP. Todo es síncrono y en la misma transacción. |
| **CQRS / capa de aplicación extra** | Innecesaria para este volumen. |
| **Paquete de activity log** | La tabla `auditoria` propia es suficiente y controlable. |
| **API Resources / rutas `api.php`** | No hay API separada en el MVP. |
| **Estructura de tarifas por programa/periodo** | D8: los montos se capturan al crear la inscripción; un catálogo de tarifas podrá agregarse después sin alterar el histórico. |

---

## 11. Reglas de negocio

> **Reglas RB-3, RB-5, RB-8 corregidas** y nuevas RB-17 … RB-21 añadidas por la aprobación.

### RB-1 — Secuencia del flujo
La inscripción avanza estrictamente en el orden de estados definido en §7. No se permiten saltos.

### RB-2 — Completitud de datos
Un alumno no puede avanzar de `DRAFT` a `DATA_COMPLETE` sin tener: datos personales completos, datos académicos (programa y periodo), al menos un contacto, el contacto de emergencia y la información médica básica.

### RB-3 — Generación de cargos *(CORREGIDA)*
Al pasar de `DATA_COMPLETE` a `PENDING_PAYMENT`, `InscripcionService` genera **en una sola transacción**:
- **1 concepto `INSCRIPCION`** (obligatorio para completar la inscripción) con el monto *snapshot* `monto_inscripcion`;
- **N conceptos `MENSUALIDAD`** como **obligaciones futuras**, con vencimientos calculados desde el periodo y el monto *snapshot* `monto_mensualidad` — **UVZ opera por cuatrimestres ⇒ N = 4** exactamente (decisión UVZ, §4.9), venciendo el **día 10** (`config('escolar.dia_vencimiento_mensualidad')`) de los cuatro meses derivados de `periodos.fecha_inicio`;
- el descuento del convenio **solo si `convenio_estatus = APROBADO`** (si no, descuento 0) y **únicamente sobre el concepto `INSCRIPCION`** (las mensualidades nunca lo reciben, decisión UVZ);
- **idempotencia**: si ya existen cargos, la generación se rechaza explícitamente sin crear duplicados ni cambiar el estado;
- **precondición**: si hay convenio aplicado debe estar `APROBADO` o `RECHAZADO`; `PENDIENTE_APROBACION` **bloquea** la generación (F23).

**Las mensualidades generadas no bloquean la inscripción inicial:** pueden permanecer `PENDIENTE` mientras el alumno avanza y queda `ENROLLED`.
Si `monto_inscripcion = 0`, el concepto `INSCRIPCION` nace `PAGADO` para no bloquear el flujo.
La generación es **automática**; no hay captura manual de mensualidades (D4).

### RB-4 — Un solo pago por concepto
Cada concepto se cubre con **un único pago**. En el MVP **no** hay pagos parciales, anticipos, enganches ni división de pagos (D5). El diseño (pagos referidos a `concepto_pago_id`) queda preparado para ampliarse a parcialidades sin rehacer el módulo.

**Semántica de reintentos (Fase 8):** el concepto se liquida mediante **un solo pago completo**, pero conserva su historial de intentos: solo puede existir **un intento `PENDIENTE` activo a la vez** (bloqueo con `lockForUpdate()` en `PagoService::registrar()`), solo un pago `VALIDADO` liquida el concepto, y los intentos `RECHAZADO` permanecen íntegros —**sin UNIQUE(concepto_pago_id)**— permitiendo registrar un nuevo intento después de cada rechazo.

### RB-5 — Validación de pago (four-eyes) *(CORREGIDA)*
- Solo **ADMINISTRACION** (o ADMIN) puede pasar un pago de `PENDIENTE` a `VALIDADO`.
- Al validar, **en una transacción**: se marca el pago `VALIDADO`, se genera el `folio` **único**, se marca el concepto `PAGADO` (un solo pago por concepto ⇒ saldo en cero) y se registra `auditoria`.
- **Efecto en el proceso (solo concepto `INSCRIPCION`):** si el pago validado corresponde al concepto `INSCRIPCION`, la inscripción avanza `PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` y se llena `pagos_validados_at`.
- **Validar una `MENSUALIDAD` NO modifica `inscripciones.estatus`** (se puede validar estando la inscripción en `PENDING_DOCUMENTS`, `DOCUMENT_REVIEW`, `FINAL_REVIEW` o `ENROLLED`, sin alterar el proceso).
- El rechazo obliga a `motivo_rechazo` y **no genera folio**. Si el pago rechazado era del concepto `INSCRIPCION`, la inscripción regresa a `PENDING_PAYMENT`; si era una mensualidad, el proceso no cambia (solo el pago queda `RECHAZADO`).

### RB-6 — Separación de captura y validación de pago
ADMISIONES solo puede **captura** pagos (quedan `PENDIENTE`). Nunca validarlos.

### RB-7 — Documentos obligatorios
- La inscripción solo puede pasar a `DOCUMENT_REVIEW` si **todos** los tipos de documento con `obligatorio = true` tienen un archivo cargado.
- Solo **CONTROL_ESCOLAR** (o ADMIN) valida o rechaza documentos.
- El rechazo obliga a `motivo_rechazo` y regresa la inscripción a `PENDING_DOCUMENTS`.
- Cada tipo de documento admite **un solo registro** por inscripción: al volver a subir, se reemplaza el archivo.

### RB-8 — Guardas de la revisión final *(CORREGIDA)*
Para pasar a `ENROLLED` **debe ser obligatorio únicamente**:

1. ✅ El concepto **`INSCRIPCION`** tiene estatus **`PAGADO`** (es decir, su pago está `VALIDADO`);
2. ✅ Todos los **documentos obligatorios** están `ACEPTADO`;
3. ✅ **No existen observaciones** con `tipo = FALTANTE` y `estatus = ABIERTA`;
4. ✅ **Control Escolar** realizó la revisión final (usuario `validada_por`);
5. ✅ Si hay convenio aplicado, está **RESUELTO**: `convenio_estatus = APROBADO` **o** `RECHAZADO` (o no existe convenio). **`PENDIENTE_APROBACION` bloquea.**
   > **Corrección Fase 12 (decisión aprobada):** un convenio **`RECHAZADO` se considera RESUELTO SIN DESCUENTO** y **no bloquea** la finalización. La Fase 7 ya permite generar cargos con `RECHAZADO` (`monto_descuento = 0`, cargos normales, el proceso continúa): bloquearlo después en `FINAL_REVIEW` generaría un estado imposible de resolver. Único bloqueante: `PENDIENTE_APROBACION`.

**NO bloquea la finalización:**
- ❌ mensualidades futuras `PENDIENTE` o vencidas;
- ❌ cualquier concepto `MENSUALIDAD` sin pagar;
- ❌ pagos rechazados de mensualidades (quedan como historial);
- ❌ observaciones `GENERAL`/`DOCUMENTO`/`PAGO` abiertas (solo `FALTANTE` bloquea).

### RB-9 — Asignación de número de control
- Formato **`YYYY####`** (ej. `20260001`): 4 dígitos de año + 4 dígitos secuenciales.
- **Aclaración Fase 12 (decisión aprobada):** el **`YYYY` se deriva EXCLUSIVAMENTE de `inscripciones.periodo.fecha_inicio->year`** — el año del periodo académico de ingreso — y **NUNCA** de la fecha en que se ejecuta la finalización. Es determinista para todos los alumnos del mismo periodo, no depende de cuándo el personal administrativo finalice el trámite y respalda el carácter permanente del número de control. Ejemplo: `periodo.fecha_inicio = 2027-01-11` ⇒ primer número **`20270001`**, aunque la revisión final se realice en diciembre de 2026. En `consecutivos`, `anio` = ese mismo año.
- Secuencial y **único** a nivel institucional.
- Se asigna **una sola vez**, al completar exitosamente la **primera** inscripción (`ENROLLED`), dentro de esa misma transacción.
- En inscripciones posteriores (otros periodos) **se conserva el mismo** número de control.
- Implementación (P2 aprobado): **tabla `consecutivos`** con `UNIQUE (tipo, anio)` y `ultimo_valor`. Dentro de la transacción final: `lockForUpdate()` sobre la fila del año (`tipo = NUMERO_CONTROL`), incremento, generación `YYYY####`, asignación al alumno y `UNIQUE` en `alumnos.numero_control`. **Prohibido calcular el consecutivo con `MAX(numero_control)`.**

### RB-10 — Folio de pago
El `folio` del pago se genera **solo al validar**, es único y no se modifica después. Si el pago se rechaza, nunca existe folio. **No existe folio de inscripción** (D7): la inscripción se identifica por su `id`, el alumno por su número de control y cada pago por su folio.

**Formato exacto (Fase 8, decisión aprobada):** `PAG-YYYYMMDD-########` — `YYYYMMDD` es la fecha/hora de **validación** con el timezone configurado de la aplicación y `########` es el `id` del pago con padding a 8 dígitos (id 127 ⇒ `PAG-20261001-00000127`). Implementación central en `FolioPagoService` (método único, reutilizable): sin tabla `consecutivos`, sin `MAX()`, sin `NumeroControlService`; la unicidad la garantiza el ID y el `UNIQUE(folio)` de BD es la defensa final (R12).

### RB-11 — Cancelación *(AMPLIADA)*
- Solo **ADMIN** puede cancelar; `motivo` obligatorio.
- **Una inscripción `ENROLLED` no puede cancelarse** mediante este flujo.
- Al cancelar:
  - los conceptos `PENDIENTE` pasan a **`CANCELADO`**;
  - los conceptos `PAGADO` no se alteran;
  - los pagos `RECHAZADO` permanecen como historial;
  - los pagos **`VALIDADO` nunca se eliminan ni se modifican automáticamente**; se conservan íntegros para auditoría.
- **Reembolsos/devoluciones quedan fuera del MVP.**
- Queda registro en `auditoria` con quién, cuándo y por qué.

### RB-12 — Trazabilidad
Toda acción crítica (creación de inscripción, cambio de estado, aplicación/aprobación de convenio, registro/validación/rechazo de pago, validación/rechazo de documento, asignación de NC, cancelación) genera **obligatoriamente** un registro en `auditoria` con usuario, IP y estados antes/después, dentro de la misma transacción que produjo el cambio.

### RB-13 — Vigencia de catálogos y cierre de periodo
- Un alumno solo puede inscribirse en un `periodo` con `activo = true` y **sin `cerrado_at`**.
- **`periodos.cerrado_at` bloquea nuevas inscripciones y modificaciones normales** asociadas al periodo; **ADMIN** puede visualizar la información histórica.
- Los `programas` y `convenios` inactivos no se pueden seleccionar en inscripciones nuevas.
- Un convenio fuera de su vigencia no se puede aplicar.

### RB-14 — Duplicidad
Un alumno **no puede** tener dos inscripciones en el mismo periodo: `UNIQUE (alumno_id, periodo_id)` (**D2 aprobado**). No se contemplan carreras simultáneas en el mismo periodo en el MVP.

### RB-15 — No borrado de registros financieros
Pagos, conceptos y observaciones **no** usan soft delete: se rechazan, cancelan o resuelven con motivo y responsable.

### RB-16 — Control de acceso
Toda acción está limitada por la matriz de §8. El rol se verifica tanto a nivel de ruta (middleware) como a nivel de acción (policy en cada método Livewire).

### RB-17 — Aprobación de convenio *(NUEVA)*
- **Máximo un convenio por inscripción** (D6).
- **ADMISIONES lo aplica** → `convenio_estatus = PENDIENTE_APROBACION`, con `convenio_aplicado_por` y `convenio_aplicado_at`.
- **ADMINISTRACION lo aprueba o rechaza** → `APROBADO` (con `convenio_aprobado_por` y `convenio_aprobado_at`) o `RECHAZADO` (con `convenio_motivo`).
- **ADMIN puede aplicar y aprobar.**
- **Mientras esté `PENDIENTE_APROBACION`, el convenio no afecta los cargos** y la inscripción no puede pasar a `PENDING_PAYMENT`.
- El descuento se aplica (y se congela como `monto_descuento`) **solo al aprobarse**.
- Trazabilidad obligatoria: quién aplicó, quién aprobó, cuándo y valor *snapshot* utilizado.

### RB-18 — Información médica sensible *(NUEVA)*
- La lectura/edición de `informacion_medicas` se restringe a **ADMIN** y **ADMISIONES**.
- **CONTROL_ESCOLAR** tiene **lectura** únicamente en el contexto de la revisión final si el proceso lo requiere.
- **ADMINISTRACION no puede visualizarla** (no la necesita para validar pagos). Se aplica en policy server-side + ocultamiento en Blade (`@can`).

### RB-19 — Comprobante de pago PDF *(NUEVA)*
- Solo se genera comprobante para pagos en estatus **`VALIDADO`**.
- Contenido mínimo: alumno, programa, concepto, monto, forma de pago, referencia, fecha, folio.
- **Identidad institucional (Fase 1.1):** el PDF debe incluir logo UVZ, "Universidad Villa de Zacatecas", colores institucionales y datos de contacto tomados de `config('branding.*')` — sin textos ni colores hardcodeados en la vista del PDF.
- Se genera bajo demanda y se descarga a través de ruta autorizada.
- **No es factura**; no hay facturación fiscal en el MVP.

### RB-20 — Dashboard operativo *(NUEVA)*
- Cuatro contadores: **inscripciones pendientes**, **pagos por validar**, **documentos pendientes**, **inscripciones completadas**.
- Sin gráficas, sin BI, sin exportación.

### RB-21 — Seguridad mínima obligatoria *(NUEVA)*
- Contraseñas seguras + hash nativo de Laravel.
- Rate limiting en login y en cargas de archivo.
- Protección CSRF, autorización y validación server-side.
- Archivos privados (nunca en `public/`).
- Sesiones seguras (cookie `httpOnly`/`secure`, regeneración al login).
- Auditoría de acciones críticas.
- **No guardar contraseñas ni secretos en el código** (`.env`).
- Sin 2FA en el MVP.

---

## 12. Validaciones

### 12.1 Alumno

| Campo | Regla |
|---|---|
| `nombre`, `apellidos` | `required`, `string`, `max:100`, sin solo-espacios |
| `curp` | `required`, `regex` de CURP (18 chars), `unique:alumnos,curp` (ignorando soft delete) |
| `fecha_nacimiento` | `required`, `date`, `before:today`, edad mínima razonable |
| `sexo` | `required`, debe existir en el BackedEnum |
| `correo` | `nullable`, `email`, `max:150` |
| `telefono` | `nullable`, `regex` de teléfono MX, `max:20` |
| `cp` | `nullable`, `regex` numérico 5 dígitos |
| `nacionalidad`, `pais` | `required`/`default`, `max` razonable |
| domicilio | `nullable`, `max` por campo |
| `numero_control` | **no editable por el usuario**: solo se asigna por servicio en la transacción final; formato `^\d{8}$` |

### 12.2 Contactos

| Campo | Regla |
|---|---|
| `tipo` | `required`, en `TUTOR\|EMERGENCIA` |
| `nombre` | `required`, `max:150` |
| `parentesco` | `required`, `max:60` |
| `telefono` | `required`, `regex` MX, `max:20` |
| `correo` | `nullable`, `email` |
| Regla extra | Debe existir **exactamente 1 contacto de cada tipo** para considerar completo el paso |

### 12.3 Información médica

| Campo | Regla |
|---|---|
| `tipo_sangre` | `nullable`, en el BackedEnum |
| `alergias`, `condiciones`, `medicamentos` | `nullable`, `string`, `max:2000` |
| `tiene_discapacidad` | `required`, `boolean` |
| `detalle_discapacidad` | `required_if:tiene_discapacidad,true`, `max:2000` |
| Acceso | `InformacionMedicaPolicy` — **ADMINISTRACION denegado** (RB-18) |

### 12.4 Inscripción

| Campo | Regla |
|---|---|
| `alumno_id` | `required`, `exists:alumnos,id` (sin soft delete) |
| `periodo_id` | `required`, `exists:periodos,id`; periodo `activo`, **sin `cerrado_at`** y vigente |
| `programa_id` | `required`, `exists:programas,id`, programa `activo` |
| `convenio_id` | `nullable`, `exists:convenios,id`, vigente y activo |
| duplicidad | rechazo si ya existe `(alumno_id, periodo_id)` (RB-14) |
| `monto_inscripcion`, `monto_mensualidad` | `required`, `numeric`, `min:0`, `decimal:2` — capturados al crear la inscripción (D8) |

### 12.5 Convenio (aprobación)

| Regla |
|---|
| Solo puede existir **un** convenio aplicado por inscripción |
| `convenio_estatus` solo transita `→ PENDIENTE_APROBACION → APROBADO / RECHAZADO` (nunca por policy de rol equivocado) |
| Aplicar: requiere capability `convenio.aplicar` (ADMISIONES/ADMIN) |
| Aprobar/rechazar: requiere capability `convenio.aprobar` (ADMINISTRACION/ADMIN) |
| Al **rechazar**: `convenio_motivo` **required**, `min:5`, `max:2000` |
| `monto_descuento` recalculado y congelado solo al aprobar |

### 12.6 Conceptos de pago

| Campo | Regla |
|---|---|
| `tipo` | `required`, en el BackedEnum (`INSCRIPCION`, `MENSUALIDAD`, `OTRO`) |
| `numero` | `required_if:tipo=MENSUALIDAD`, `integer`, `min:1` |
| `concepto` | `required`, `max:150` |
| `monto`, `descuento`, `monto_total` | `required`, `numeric`, `min:0`, `decimal:2`; coherencia: `monto_total = monto - descuento` |
| `fecha_vencimiento` | `required`, `date` |
| Generación | **Solo automática por servicio**, dentro de transacción (D4); sin captura manual |

### 12.7 Pagos

| Campo | Regla |
|---|---|
| `concepto_pago_id` | `required`, `exists`, concepto `PENDIENTE` y de la inscripción en curso |
| `monto` | `required`, `numeric`, `min:0.01`, `decimal:2`; **igual** al `monto_total` del concepto (un solo pago por concepto, D5) |
| `fecha_pago` | `required`, `date`, no futura |
| `metodo` | `required`, en el BackedEnum |
| `referencia` | `required_if:metodo=TRANSFERENCIA\|TARJETA`, `max:100` |
| `comprobante` | `nullable`, `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240` (KB) = **10 MB** |
| Validación | `motivo_rechazo` **required** cuando se rechaza |

### 12.8 Documentos

| Campo | Regla |
|---|---|
| `tipo_documento_id` | `required`, `exists`, activo, no registrado ya para esa inscripción |
| archivo | `required`, `file`, `mimes:pdf,jpg,jpeg,png`, **`max:10240`** (10 MB) |
| integridad | MIME real verificado server-side (`finfo`), no solo la extensión |
| Validación | `motivo_rechazo` **required** cuando se rechaza |

> **Interpretación aprobada (Fase 10 — aclaración de "no registrado ya", coherente con RB-7/FC-6):** "no registrado ya" se refiere a que **jamás existan DOS filas** para el mismo tipo en una inscripción, no a prohibir la recarga. Se conserva `UNIQUE(inscripcion_id, tipo_documento_id)`: la primera carga **crea** el `Documento` y toda recarga **actualiza ESA MISMA fila** (vuelve a `PENDIENTE`, limpia `validado_por`/`validado_at`/`motivo_rechazo`); el reemplazo está permitido en las etapas del flujo de documentos —especialmente tras un `RECHAZADO`—, el archivo anterior se elimina **solo después del COMMIT** de la nueva carga, la trazabilidad del reemplazo vive en `auditoria` (`documento_subido`/`documento_reemplazado`) y **no** se crea histórico con filas duplicadas ni tabla de versiones.

### 12.9 Observaciones

| Campo | Regla |
|---|---|
| `tipo` | `required`, en el BackedEnum |
| `detalle` | `required`, `string`, `min:5`, `max:2000` |
| Resolución | `resuelta_at` y `resuelta_por` se llenan automáticamente al resolver |

### 12.10 Usuarios (ADMIN)

| Campo | Regla |
|---|---|
| `name` | `required`, `max:255` |
| `email` | `required`, `email`, `unique:users,email` |
| `password` | `required`, `confirmed`, `min:8`, exigencia de complejidad razonable |
| `rol` | `required`, en el BackedEnum (4 valores) |
| `activo` | `boolean` |

### 12.11 Transversales

- **Server-side siempre**: toda regla se valida también en el servidor; el frontend solo es UX.
- **Rate limiting** en login y en acciones de carga de archivos.
- **Escapado** de toda salida en Blade (`{{ }}`) para prevenir XSS.
- **CSRF** habilitado (Blade/Livewire por defecto).
- Consultas con `select` explícito en listados; `with()` para evitar N+1.
- Contraseñas con `Hash::make` y `hidden`; secretos solo en `.env`.

### 12.12 Catálogos (ADMIN)

| Catálogo | Reglas server-side |
|---|---|
| `programas` | `clave`: `required`, `max:20`, formato `A-Z0-9_-`, `unique` (índice simple: una clave con soft delete **no** se reutiliza hasta purgar el registro); `nombre`: `required`, `max:150`; `duracion_periodos`: `nullable`, `integer`, 1–65535; `activo`: `boolean` |
| `periodos` | `clave`/`nombre` requeridos; `fecha_inicio`: `required`, `date`; `fecha_fin`: `required`, `date`, `after_or_equal:fecha_inicio`; `activo`: `boolean`; `cerrado_at` solo por acción Cerrar/Reabrir (no se captura a mano) |
| `tipos_documento` | `clave`: `required`, `max:30`, `unique`; `nombre`: `required`, `max:120`; `obligatorio`, `activo`: `boolean` |
| `convenios` | `clave`: `required`, `max:20`, `unique`; `nombre`: `required`, `max:150`; `tipo`: en `TipoConvenio` (`PORCENTAJE`\|`MONTO`); `valor`: `PORCENTAJE` → 0 < v ≤ 100, `MONTO` → v > 0; `fecha_fin` ≥ `fecha_inicio`; `notas`: `nullable`, `max:2000`; `activo`: `boolean` |
| Autorización | Rutas `/admin/...` con `auth` + `role:ADMIN`; cada acción del componente verifica `Gate usuario.manage` (ADMIN). La **aprobación** de un convenio no vive en el catálogo: pertenece a cada inscripción (§4.8) |

---

## 13. Flujos críticos

### FC-1 — Alta de alumno (pasos 2–6)

```
ADMISIONES → buscar alumno existente por CURP (evitar duplicado)
  → crear Alumno (transacción)
      → crear Contacto tipo TUTOR
      → crear Contacto tipo EMERGENCIA
      → crear InformacionMedica
      → auditoria (alumno_creado)
→ Exito
```
- **Transacción:** sí (3–4 tablas).
- **Falla común:** CURP duplicada → se bloquea antes de crear.
- **Nota:** la información médica creada queda visible solo para ADMIN/ADMISIONES (RB-18).

### FC-2 — Wizard de inscripción (pasos 1–6 → `DATA_COMPLETE`)

```
ADMISIONES → crear Inscripcion en DRAFT (alumno + periodo + programa + montos snapshot)
  → persistir avance por paso (borrador en BD, sobrevive recargas)
  → paso 6 validado → guardar datos_completados_at
  → InscripcionService::transicionar(DATA_COMPLETE)
      → valida guardas → transacción → auditoria
```
- **Transacción:** sí (inscripción + auditoría).
- **Bloqueos:** periodo con `cerrado_at` (RB-13); duplicidad alumno+periodo (RB-14).

### FC-3 — Convenio: aplicación y aprobación *(NUEVO FLUJO)*

```
[ADMISIONES] Aplicar convenio (solo si vigente y activo)
  → ConvenioService::aplicar()
      → inscripcion.convenio_id = X, convenio_estatus = PENDIENTE_APROBACION
      → convenio_aplicado_por / convenio_aplicado_at
      → auditoria (convenio_aplicado)
  → los cargos NO se afectan todavía

[ADMINISTRACION] Aprobar / Rechazar
  → aprobar:
      → convenio_estatus = APROBADO, convenio_aprobado_por/at
      → calcular monto_descuento y congelarlo (snapshot)
      → si los cargos ya existen → recalculados con descuento
      → auditoria (convenio_aprobado)
  → rechazar:
      → convenio_estatus = RECHAZADO + convenio_motivo (obligatorio)
      → monto_descuento = 0, cargos sin descuento
      → auditoria (convenio_rechazado)
```

### FC-4 — Generación de cargos (→ `PENDING_PAYMENT`)

```
ADMISIONES → avanzar desde DATA_COMPLETE
  → InscripcionService::generarCargos()   **UNA transacción**
      → PRECONDICIÓN: si hay convenio, convenio_estatus debe ser APROBADO o RECHAZADO
            (PENDIENTE_APROBACION ⇒ se detiene y se notifica en UI)
      → PRECONDICIÓN: si ya existen cargos ⇒ se rechaza (sin duplicados)
      → crear ConceptoPago tipo INSCRIPCION (obligatorio; PAGADO si monto = 0; vence en periodo.fecha_inicio)
      → crear 4 ConceptoPago tipo MENSUALIDAD (cuatrimestre UVZ: cuatro meses desde
            periodo.fecha_inicio, vencimiento el día config('escolar.dia_vencimiento_mensualidad'))
      → aplicar descuento SOLO si convenio APROBADO y SOLO al concepto INSCRIPCION
      → transicionar(DATA_COMPLETE → PENDING_PAYMENT) + auditoria (cargos_generados)
  → si falla: ROLLBACK completo (nada se creó)
```

### FC-5 — Registro y validación de pago (→ `PAYMENT_VALIDATED`) *(CORREGIDO)*

```
[ADMISIONES] Registrar pago
  → PagoService::registrar()  **UNA transacción**
      → validar archivo comprobante (PDF/JPG/JPEG/PNG, ≤ 10 MB) → disco privado
      → crear Pago en PENDIENTE
      → SI el concepto es INSCRIPCION:
            transicionar inscripción PENDING_PAYMENT → PAYMENT_REVIEW + auditoria
      → SI el concepto es MENSUALIDAD:
            el proceso NO cambia de estado (solo se crea el pago)

[ADMINISTRACION] Validar pago
  → PagoService::validar()  **UNA transacción**
      → UPDATE pago SET estatus=VALIDADO, folio=<único>, validado_por, validado_at
      → UPDATE concepto SET estatus=PAGADO, pagado_at
      → auditoria (pago_validado)
      → SI concepto.tipo = INSCRIPCION:
            inscripción PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS
            pagos_validados_at = now()  (+ auditoria del cambio de estado)
      → SI concepto.tipo = MENSUALIDAD:
            **la inscripción NO cambia de estado** (puede estar en PENDING_DOCUMENTS,
            DOCUMENT_REVIEW, FINAL_REVIEW o ENROLLED)
  → COMMIT  |  en cualquier error → ROLLBACK (no queda folio huérfano)

[ADMINISTRACION] Rechazar pago
  → motivo_rechazo obligatorio → pago RECHAZADO (sin folio)
      → SI concepto INSCRIPCION → inscripción a PENDING_PAYMENT + auditoria
      → SI concepto MENSUALIDAD → el proceso no cambia

[POSTERIOR] Comprobante PDF
  → ComprobantePagoService::generar(pago) — solo si estatus = VALIDADO
      → descarga por ruta autorizada (RB-19)
```
- **Transacción:** sí, crítica (afecta `pagos`, `conceptos_pago`, `inscripciones`, `auditoria`).

### FC-6 — Recepción y validación de documentos (→ `FINAL_REVIEW`)

```
[ADMISIONES] Subir documento
  → DocumentoService::subir()
      → validar MIME/tamaño (≤10 MB) → guardar en disco privado
      → upsert en Documentos (un registro por tipo) estatus=PENDIENTE
  → cuando todos los obligatorios estén cargados
      → transicionar(PENDING_DOCUMENTS → DOCUMENT_REVIEW) + auditoria

[CONTROL_ESCOLAR] Validar / rechazar
  → aceptar: documento ACEPTADO (+ auditar)
  → rechazar: motivo obligatorio → documento RECHAZADO
      → inscripción regresa a PENDING_DOCUMENTS + auditoria
  → si todos los obligatorios ACEPTADOS
      → transicionar(DOCUMENT_REVIEW → FINAL_REVIEW) + auditoria
```

### FC-7 — Revisión final y asignación de número de control (→ `ENROLLED`) ★ *(CORREGIDO)*

```
CONTROL_ESCOLAR → abrir PanelEstados / revisión final
  → verificar guardas RB-8:
        ✓ concepto INSCRIPCION estatus = PAGADO
        ✓ documentos obligatorios todos ACEPTADOS
        ✓ sin observaciones ABIERTA de tipo FALTANTE
        ✓ convenio RESUELTO: APROBADO o RECHAZADO (o no aplica); PENDIENTE_APROBACION bloquea
        ✗ NO se verifica el estado de las MENSUALIDADES
  → InscripcionService::finalizar()  **UNA transacción**
      → NumeroControlService::asignar()
            → si el alumno ya tiene numero_control → conservarlo
            → si no → transacción:
                 (1) obtener fila `consecutivos (tipo=NUMERO_CONTROL, anio)` con lockForUpdate()
                 (2) incrementar ultimo_valor
                 (3) generar YYYY#### con 4 dígitos
                 (4) asignarlo al alumno
                 (5) UNIQUE en alumnos.numero_control (última defensa)
            → **prohibido calcular con MAX(numero_control)**
            → UPDATE alumnos SET numero_control = ...
      → UPDATE inscripciones
            estatus = ENROLLED, finalizada_at = now(), validada_por = user
      → INSERT auditoria (estado_nuevo=ENROLLED, NC asignado, IP, user)
  → COMMIT | cualquier error → ROLLBACK completo
```
- **Transacción:** la más crítica del sistema.
- **Punto clave:** una inscripción puede quedar `ENROLLED` **con mensualidades `PENDIENTE`**.

### FC-8 — Cancelación de inscripción

```
ADMIN → motivo obligatorio → InscripcionService::cancelar()
  → estatus = CANCELLED, cancelada_por/at/motivo
  → conceptos con estatus PENDIENTE → CANCELADO
  → conceptos PAGADO → intactos
  → pagos RECHAZADO → se conservan (historial)
  → pagos VALIDADO → NUNCA se eliminan ni se modifican
  → si estatus anterior = ENROLLED → RECHAZADO (no cancelable en el MVP)
  → auditoria obligatoria
```
- Sin reembolsos/devoluciones (fuera del MVP).

### FC-9 — Dashboard operativo

```
Cualquier rol autenticado
  → DashboardService::contadores()
      → inscripciones pendientes (no ENROLLED/CANCELLED)
      → pagos por validar (estatus PENDIENTE)
      → documentos pendientes de validación
      → inscripciones completadas (ENROLLED)
  → 4 contadores, sin gráficas (RB-20)
```

### FC-10 — Auditoría transversal

```
Cualquier acción crítica
  → AuditoriaService::registrar(modelo, accion, antes, después, contexto)
      → INSERT dentro de la MISMA transacción del cambio
      → user_id (auth), ip, user_agent
```

---

## 14. Pruebas unitarias propuestas

| # | Archivo / test | Qué valida |
|---|---|---|
| U1 | `MaquinaEstadosTest::test_matriz_transiciones_permitidas` | Las transiciones legítimas de §7.2 están permitidas |
| U2 | `MaquinaEstadosTest::test_transiciones_prohibidas_lanzan_excepcion` | Saltos (ej. `DRAFT → ENROLLED`) y estados terminales sin salida lanzan excepción |
| U3 | `MaquinaEstadosTest::test_retrocesos_requieren_motivo` | Un retroceso sin motivo es rechazado |
| U4 | `CalculoConvenioTest::test_convenio_porcentaje` | 10% sobre monto → descuento correcto, 2 decimales |
| U5 | `CalculoConvenioTest::test_convenio_monto_fijo` | Descuento fijo, sin exceder el monto base |
| U6 | `CalculoConvenioTest::test_convenio_sin_aprobar_no_descuenta` | `PENDIENTE_APROBACION` ⇒ descuento 0 (RB-17) |
| U7 | `GeneracionNumeroControlTest::test_formato_yyyy####` | Formato `20260001` (8 dígitos, año + consecutivo) |
| U8 | `GeneracionNumeroControlTest::test_numero_control_unico` | Dos generaciones consecutivas producen valores distintos (simulación de unicidad) |
| U9 | `GeneracionFolioTest::test_formato_folio_pago` | Formato del folio |
| U10 | `GeneracionFolioTest::test_folio_unico_y_no_generado_en_rechazo` | El folio solo existe tras validar; el rechazo no genera folio |

---

## 15. Pruebas feature propuestas

| # | Archivo / test | Qué valida |
|---|---|---|
| F1 | `AlumnoTest::test_crear_alumno_con_curp_invalida_falla` | Validación de CURP |
| F2 | `AlumnoTest::test_crear_alumno_con_curp_duplicada_falla` | `UNIQUE` de CURP |
| F3 | `AlumnoTest::test_guardar_contactos_e_info_medica` | Contacto tutor + emergencia + info médica persisten juntos |
| F4 | `WizardInscripcionTest::test_completar_pasos_transiciona_a_data_complete` | Los 6 pasos completos → `DATA_COMPLETE` + `datos_completados_at` |
| F5 | `WizardInscripcionTest::test_paso_incompleto_no_avanza` | Validación por paso |
| F6 | `AutorizacionTest::test_admisiones_no_puede_validar_pago` | 403 |
| F7 | `AutorizacionTest::test_admisiones_no_puede_validar_documentos` | 403 |
| F8 | `AutorizacionTest::test_administracion_no_puede_asignar_numero_control` | 403 |
| F9 | `AutorizacionTest::test_control_escolar_no_puede_validar_pagos` | 403 |
| F10 | `AutorizacionTest::test_admin_accede_a_todo` | `Gate::before` de ADMIN |
| F11 | `PagoTest::test_validar_pago_inscripcion_ejecuta_transaccion_completa` | Pago `VALIDADO` + folio único + concepto `PAGADO` + inscripción `PAYMENT_VALIDATED`/`PENDING_DOCUMENTS` + `auditoria` |
| F12 | `RechazoPagoTest::test_rechazo_regresa_a_pending_payment_con_motivo` | Rechazo con motivo, **sin folio**, inscripción `PENDING_PAYMENT` |
| F13 | `DocumentoTest::test_subir_obligatorios_transiciona_a_document_review` | Carga completa → `DOCUMENT_REVIEW` |
| F14 | `DocumentoTest::test_documento_obligatorio_faltante_bloquea_final_review` | Guarda RB-8 (documentos) |
| F15 | `DocumentoTest::test_rechazar_documento_regresa_con_motivo` | `RECHAZADO` + motivo → `PENDING_DOCUMENTS` |
| F16 | `FinalizacionTest::test_finalizacion_asigna_numero_control_y_enrolled` | Caso feliz: NC `YYYY####` único + `ENROLLED` + `finalizada_at` + auditoría |
| F17 | `FinalizacionTest::test_mensualidades_pendientes_no_bloquean_enrolled` | **Corrección RB-8:** inscripción `ENROLLED` con mensualidades `PENDIENTE` |
| F18 | `FinalizacionTest::test_bloqueo_por_concepto_inscripcion_o_faltantes` | RB-8: concepto `INSCRIPCION` sin pagar, documento faltante o `FALTANTE` abierto ⇒ bloqueo |
| F19 | `CancelacionTest::test_solo_admin_cancela_y_requiere_motivo` | 403 para otros roles; motivo obligatorio |
| F20 | `CancelacionTest::test_cancelacion_cancela_conceptos_sin_tocar_pagos_validados` | Conceptos `PENDIENTE→CANCELADO`; pagos `VALIDADO` intactos |
| F21 | `AuditoriaTest::test_cada_transicion_deja_registro_completo` | Usuario, estado anterior/nuevo, IP en cada transición |
| F22 | `DuplicidadTest::test_doble_inscripcion_mismo_periodo_rechazada` | `UNIQUE (alumno_id, periodo_id)` |
| F23 | `ConvenioTest::test_convenio_requiere_aprobacion_antes_de_generar_cargos` | `PENDIENTE_APROBACION` ⇒ sin descuento y sin `PENDING_PAYMENT` |
| F24 | `ConvenioTest::test_admisiones_no_puede_aprobar_convenio` | 403 (four-eyes convenio) |
| F25 | `PagoTest::test_validar_mensualidad_no_modifica_estado_inscripcion` | **Corrección RB-5:** validar mensualidad en `ENROLLED`/`FINAL_REVIEW` no cambia estatus |
| F26 | `ComprobantePagoTest::test_comprobante_pdf_solo_para_pago_validado` | PDF solo con `VALIDADO`; 403/404 en `PENDIENTE` |
| F27 | `InformacionMedicaTest::test_administracion_no_accede_a_informacion_medica` | 403 para ADMINISTRACION; sí ADMISIONES/ADMIN |
| F28 | `PeriodoTest::test_periodo_cerrado_bloquea_nuevas_inscripciones` | `cerrado_at` ⇒ bloqueo (RB-13) |

**Herramientas:** Pest + Livewire 4 (`Livewire::test`), `RefreshDatabase` sobre la **base de datos exclusiva de testing (MySQL)**, `actingAs()` por rol. **Las pruebas se desarrollan junto con cada módulo** (ver §20), no al final. **Cobertura mínima objetivo:** los 4 flujos críticos (datos → pago → documentos → finalización) + la matriz de autorización por rol + las correcciones RB-5/RB-8.

---

## 16. Riesgos técnicos

| # | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| R1 | **Asignación de número de control concurrente** | Números duplicados | Tabla **`consecutivos`** con `UNIQUE (tipo, anio)` + `lockForUpdate()` + incremento en la transacción final + `UNIQUE` en `alumnos.numero_control` |
| R2 | **Estados desincronizados** (pago OK pero la inscripción no avanza) | Datos inconsistentes | Única máquina de estados + una sola transacción por transición |
| R3 | **Soft delete + `UNIQUE` compuesto** (CURP, alumno+periodo) | Fallas raras al re-crear registros | Unique simple sobre columnas no borradas lógicamente; revisar cada índice con `deleted_at` |
| R4 | **Huérfanos por soft delete** (borrar alumno no borra contactos/info médica) | Datos huérfanos | Borrado lógico coordinado en el servicio + scopes activos en consultas |
| R5 | **Archivos sensibles accesibles públicamente** | Fuga de información personal | Disco fuera de `public/`, descarga por controlador con policy, validación MIME/tamaño, nunca `Storage::url()` público |
| R6 | **Autorización olvidada en componentes Livewire** | Escalamiento de rol | `authorize()` obligatorio en cada método público + pruebas F6–F10, F24, F27 |
| R7 | **Validación solo en cliente** | Datos basura en BD | Validación server-side en cada paso (`$rules`) + reglas en servicios |
| R8 | **Wizard largo pierde datos al recargar** | Mala experiencia y re captura | Persistir el borrador `DRAFT` en BD entre pasos (no solo en sesión) |
| R9 | **Tabla `auditoria` crece sin límite** | Rendimiento con el tiempo | Aceptable para el MVP; índice en el morph; retención futura fuera del MVP |
| R10 | **Montos en `FLOAT`** | Errores de centavos | `DECIMAL(10,2)` + cast `decimal:2` en Eloquent |
| R11 | **Estados/rutas duplicados entre capas** | Divergencia de lógica | Reglas de negocio solo en Services; las vistas jamás asignan `estatus` |
| R12 | **Carreras de concurrencia en validación de pago** | Folios repetidos o cobros dobles | `folio UNIQUE` + transacción + `lockForUpdate` |
| R13 | **Payloads pesados de archivos en Livewire** | Requests lentas o con timeout | Límite 10 MB, validación en servidor, disco local |
| R14 | **Sin pruebas** | Regresiones al iterar | Pruebas desde la fase 5 del plan (§20), junto a cada módulo |
| R15 | **Soft delete en tablas financieras** | Pérdida de rastro contable | Excluir `pagos`, `conceptos_pago` y `observaciones` del soft delete |
| R16 | **Regresión de la corrección de mensualidades** (volver a bloquear `ENROLLED` por mensualidades impagas) | Flujo principal roto | Guarda RB-8 implementada **solo** sobre el concepto `INSCRIPCION` + pruebas F17/F18/F25 como red de seguridad |
| R17 | **Filtrado de información médica olvidado en UI** | Exposición de datos sensibles | Policy server-side **y** `@can` en Blade + prueba F27 |
| R18 | **Validación de mensualidad con lógica condicional dispersa** | Efectos secundarios no intencionados en el estado | El criterio vive en **un solo lugar** (`PagoService`, vía `concepto->tipo`) y está cubierto por F25 |

---

## 17. Decisiones pendientes

**Todas las decisiones de la lista original quedaron resueltas** y se registran en §22. Los tres puntos residuales también fueron **aprobados**:

- **P1 — APROBADO:** dependencia de PDF `barryvdh/laravel-dompdf` (versión compatible con Laravel 12).
- **P2 — APROBADO con ajuste:** número de control `YYYY####` garantizado con la tabla **`consecutivos`** (`UNIQUE (tipo, anio)`, `lockForUpdate()`, incremento y generación dentro de la transacción de finalización). **Prohibido usar `MAX(numero_control)`.**
- **P3 — APROBADO:** base de datos exclusiva de pruebas `sistema_escolar_testing` (MySQL), nunca la de desarrollo ni producción.

**No hay decisiones abiertas.**

---

## 18. Decisiones bloqueantes (resueltas)

Las 6 decisiones bloqueantes **ya fueron aprobadas**; se resumen aquí con su resolución final (detalle en §22).

| # | Decisión | Resolución aprobada |
|---|---|---|
| D1 | Modelo de roles | `users.rol`, **un rol por usuario**, sin `spatie/laravel-permission`; ADMIN puede todo |
| D2 | Duplicidad de inscripción | **No permitida**: `UNIQUE (alumno_id, periodo_id)`; sin carreras simultáneas |
| D3 | Número de control | **Por alumno**, formato **`YYYY####`**, secuencial y único, se asigna 1 vez y se conserva |
| D4 | Mensualidades | **Automáticas** por `InscripcionService`, distinguibles `INSCRIPCION` vs `MENSUALIDAD`, **no bloquean** |
| D5 | Pagos | **Un pago por concepto**; sin parciales/anticipos/enganches; diseño preparado para ampliarse |
| D6 | Convenios | **Máximo 1 por inscripción**; ADMISIONES aplica, **ADMINISTRACION aprueba**, con trazabilidad |

---

## 19. Qué queda fuera del MVP

> **Registro de alcance (Etapa 2, aprobada 02/10/2026):** lo que sigue quedó **fuera del MVP (ETAPA 1 — Admisión e Inscripción)**, sin reescribir su historia. Las funciones que sí corresponden al nuevo alcance aprobado (calificaciones, kardex, docentes, grupos, constancias internas, reportes, becas, portales, etc.) ahora pertenecen a la **ETAPA 2 — CONTROL ESCOLAR** (`docs/roadmap-sistema-escolar.md`, fases 2.0-00 … 2.0-17 y §24 de este documento). **Ninguna fase de la Etapa 2 está implementada.** Las que siguen fuera **de la Etapa 2** se mantienen fuera.

- Calificaciones, kardex, boletas, asignaturas, docentes, grupos, horarios, aulas, asistencias. *(→ Etapa 2, fases 2.0-01 … 2.0-10)*
- Portal del alumno/tutor y autoservicio en línea (**solo usuarios administrativos con acceso**). *(Rol ALUMNO aprobado para 2.0-16 con aislamiento estricto — D-N5; en la Etapa 1 se mantiene "sin login")*
- Pasarela de pagos en línea, conciliación bancaria, **facturación CFDI**, **reembolsos/devoluciones**. *(sigue fuera)*
- Notificaciones por correo/SMS/WhatsApp. *(sigue fuera)*
- **Constancia formal de inscripción en PDF** (el **comprobante de pago PDF sí entra**, RB-19). *(los formatos internos UVZ pasan a la Etapa 2 — C8)*
- Reportes avanzados, exportación Excel/CSV, gráficas y BI (**el dashboard de 4 contadores sí entra**, RB-20). *(reportes operativos → 2.0-17; exportación → decisión D-N6 diferida)*
- Becas complejas, planes de pago, **pagos parciales**, descuentos múltiples acumulables. *(becas/descuentos → 2.0-12 con decisión D-N3; pagos parciales siguen fuera)*
- Multi-campus / multi-institución, turnos, sucursales. *(sigue fuera)*
- Importación masiva desde Excel. *(sigue fuera)*
- API/SPA, integraciones con sistemas externos. *(sigue fuera; en la Etapa 2 quedan además fuera SEP, MEC, MET, SIGED, certificados oficiales y titulación electrónica — C8)*
- RBAC granular con matriz permiso×rol (solo 4 roles fijos), **2FA**. *(la Etapa 2 solo agrega los roles aprobados DOCENTE y ALUMNO; sin RBAC granular ni 2FA)*
- **Catálogo de tarifas por programa/periodo** (los montos se capturan por inscripción, D8). *(sigue fuera)*
- Versionado de documentos, firma digital, OCR/escaneo de CURP. *(sigue fuera)*
- Retención/archivo de auditoría y dashboards de métricas históricas. *(sigue fuera)*
- Concurrencia multi-edición en tiempo real (bloqueo de registro en curso). *(sigue fuera)*

---

## 20. Orden recomendado de implementación

| Fase | Tarea | Pruebas en paralelo |
|---|---|---|
| 1 | Scaffold `laravel new` + Livewire + Tailwind + auth propia (login con rate limiting) + **Pest + BD exclusiva de testing** | smoke test de login |
| 2 | Enums, `RolUsuario`, middleware `role`, `Gate` definitions, policies (incl. `InformacionMedicaPolicy`, `ConvenioPolicy`), seeders | F10 |
| 3 | Migraciones de catálogos: `programas`, `periodos` (+ `cerrado_at`), `tipos_documento`, `convenios` + CRUD ADMIN | — |
| 4 | **Alumnos**: `alumnos`, `contactos`, `informacion_medicas` + pantallas Livewire (pasos 2–6) + restricción de info médica | F1–F3, F27 |
| 5 | **Núcleo**: `inscripciones`, máquina de estados, `InscripcionService`, `AuditoriaService`, trait `HasAudit` | U1–U3, F21 |
| 6 | Wizard de inscripción Livewire (pasos 1–6) con validación por paso | F4–F5, F22, F28 |
| 7 | **Convenio**: `ConvenioService` (aplicar/aprobar/rechazar) + **generación de cargos automática** (1 `INSCRIPCION` + **4** `MENSUALIDAD`, cuatrimestre UVZ con día configurable) | U4–U6, F23–F24 |
| 8 | **Pagos**: registro + validación/rechazo + folio + efecto de estado **solo para INSCRIPCION** | F11–F12, F25 |
| 9 | **Comprobante de pago PDF** (dependencia P1) bajo demanda, solo `VALIDADO` | F26 |
| 10 | **Documentos**: carga (10 MB, formatos) + validación/rechazo + obligatorios | F13–F15 |
| 11 | **Observaciones** (CRUD + resolver) | — |
| 12 | **Revisión final**: guardas RB-8 corregidas → `NumeroControlService` (`YYYY####`) → `ENROLLED` | F16–F18 |
| 13 | **Cancelación** con efectos sobre conceptos/pagos | F19–F20 |
| 14 | **Dashboard operativo** (4 contadores) + listados/búsqueda + detalle con historial de auditoría | — |
| 15 | Pruebas restantes (F6–F9) y cobertura completa de flujos críticos | suite verde |
| 16 | Endurecimiento: rate limit, validación de archivos, soft deletes, descargas autorizadas, sesiones seguras (RB-21) | checklist de seguridad |

> **Nota:** las pruebas se construyen **junto con cada módulo desde la fase 1**, no se dejan para el final. **Nunca** se ejecutan contra la base de desarrollo ni de producción.

---

## 21. Supuestos importantes usados

Estos supuestos sostienen la propuesta; si alguno es incorrecto, la arquitectura debe ajustarse.

1. **Proyecto greenfield**: el directorio está vacío; no hay código previo que integrar ni migrar.
2. **Stack confirmado**: Laravel + MySQL + Blade + Livewire + Tailwind; sin React/Vue y sin API separada.
3. **Versiones**: PHP 8.2 → **Laravel 12**, **Livewire 4** (actualización limpia en Fase 1.1), Tailwind vía Vite.
4. **Roles:** un rol por usuario en `users.rol`; 4 roles fijos; sin paquete de permisos; **ADMIN puede todo** (D1 aprobado).
5. **Pagos:** un pago por concepto, sin parciales/anticipos/enganches; MXN con `DECIMAL(10,2)` (D5 aprobado). El diseño admite parcialidades futuras sin rehacer el módulo.
6. **Mensualidades:** generadas automáticamente por `InscripcionService`, distinguibles de `INSCRIPCION`, **y no bloquean la finalización** (D4 + corrección RB-8).
7. **Convenio:** máximo 1 por inscripción, con **aprobación de ADMINISTRACION** y *snapshot* de valor (D6 aprobado).
8. **Duplicidad:** 1 inscripción por alumno por periodo → `UNIQUE (alumno_id, periodo_id)` (D2 aprobado).
9. **Número de control:** pertenece al **alumno**, formato `YYYY####`, secuencial, único, asignado 1 vez y conservado (D3 aprobado); concurrencia resuelta con la tabla **`consecutivos`** y `lockForUpdate()`, **nunca con `MAX()`** (P2 aprobado).
10. **Los alumnos NO tienen login**; solo usuarios administrativos acceden al sistema.
11. **Los montos** de inscripción y mensualidad se **capturan al crear la inscripción** y se congelan como *snapshot*; no hay catálogo de tarifas (D8 aprobado).
12. **Archivos** en disco local **privado** (nunca `public/`), formatos PDF/JPG/JPEG/PNG, máx. 10 MB, descarga siempre autorizada (D9 aprobado).
13. **Sin notificaciones, sin reportes/Excel, sin constancia de inscripción**; **sí** dashboard de 4 contadores y **sí** comprobante de pago PDF.
14. **No existe folio de inscripción**: `id` interno + folio por pago validado + número de control del alumno (D7 aprobado).
15. **El número de control se asigna únicamente al llegar a `ENROLLED`** en la primera inscripción (paso 16).
16. **La auditoría se escribe dentro de la misma transacción** del cambio que registra.
17. **Soft delete** en `users`, `alumnos`, `programas`, `periodos`, `convenios`, `tipos_documento`, `inscripciones`; **sin soft delete** en `pagos`, `conceptos_pago`, `observaciones`, `auditoria`, `contactos`, `informacion_medicas`, `documentos`.
18. **Cancelación:** solo ADMIN, motivo obligatorio, conceptos `PENDIENTE → CANCELADO`, pagos `VALIDADO` intactos, no cancelable desde `ENROLLED`, sin reembolsos (D10 aprobado).
19. **Cierre de periodo:** `periodos.cerrado_at` bloquea nuevas inscripciones y modificaciones; ADMIN ve histórico (D15 aprobado).
20. **Pruebas:** **Pest + MySQL** con base de datos **exclusiva de testing**, escritas junto con cada módulo (D16 aprobado).
21. **Seguridad mínima:** hash nativo, rate limiting en login, CSRF, autorización y validación server-side, archivos privados, sesiones seguras, auditoría; **sin 2FA**; secretos solo en `.env` (D17 aprobado).
22. **Localización:** **es-MX**, `America/Mexico_City`, **MXN** (D18 aprobado).
23. **Información médica restringida**: ADMIN, ADMISIONES y CONTROL_ESCOLAR (lectura en revisión final); **ADMINISTRACION no la ve** (ajuste de seguridad aprobado).
24. **Sin datos de prueba sensibles reales**: seeds con información ficticia.
25. **Dependencia de PDF**: `barryvdh/laravel-dompdf` en versión compatible con Laravel 12 (P1 aprobado).
26. **Base de pruebas**: `sistema_escolar_testing` (MySQL), exclusiva y nunca la de desarrollo/producción (P3 aprobado).

---

## 22. Decisiones aprobadas (registro)

> Resolución definitiva incorporada a este documento. **No hay decisiones bloqueantes abiertas.** Pendiente solo la aprobación final de este documento para iniciar la Fase 1.

### 🔴 Decisiones bloqueantes

**1. Modelo de roles — APROBADO**
`users.rol` con **un único rol por usuario**: `ADMIN`, `ADMISIONES`, `ADMINISTRACION`, `CONTROL_ESCOLAR`. Sin `spatie/laravel-permission`. **ADMIN puede realizar todas las acciones.**

**2. Duplicidad de inscripción — APROBADO**
Un alumno **no** puede tener más de una inscripción en el mismo periodo. Se mantiene `UNIQUE (alumno_id, periodo_id)`. No se contemplan carreras simultáneas en el mismo periodo.

**3. Número de control — APROBADO**
Pertenece **al alumno**, no a la inscripción. Se asigna **una sola vez** al completar exitosamente su primera inscripción. Formato **`YYYY####`** (`20260001`, `20260002`, …), **secuencial y único**. En inscripciones posteriores se conserva el mismo.

**4. Generación de mensualidades — APROBADO**
**Automática** mediante `InscripcionService`. Los conceptos se distinguen claramente: **`INSCRIPCION`** vs **`MENSUALIDAD`**. Las mensualidades son obligaciones futuras y **NO bloquean la inscripción inicial**. Sin captura manual uno por uno.

**5. Pagos — APROBADO**
**Un pago por concepto.** Sin pagos parciales, anticipos, enganches ni división de pagos. La estructura queda preparada para ampliarse a parcialidades sin rehacer el módulo.

**6. Convenios / promociones — APROBADO**
**Máximo un convenio por inscripción.** ADMISIONES lo selecciona/aplica; **ADMINISTRACION debe aprobarlo** antes de que afecte definitivamente los cargos; ADMIN puede hacer ambas acciones. Trazabilidad: quién lo aplicó, quién lo aprobó, cuándo y valor utilizado como *snapshot*.

### 🟡 Decisiones no bloqueantes

**7. Folio de inscripción — APROBADO:** sin folio adicional. `id` interno para la inscripción; folio independiente para cada pago validado; número de control para identificar al alumno cuando quede inscrito.

**8. Montos — APROBADO:** se capturan/configuran al crear la inscripción y se guardan como *snapshot*. Sin estructura compleja de tarifas por programa/periodo; podrá agregarse un catálogo después sin alterar el histórico.

**9. Archivos — APROBADO:** disco local privado, **nunca** en `public/`. Formatos PDF, JPG, JPEG, PNG. **Máximo 10 MB** por archivo. Toda descarga pasa por autorización del backend.

**10. Cancelación — APROBADO:** solo ADMIN, motivo obligatorio. Conceptos `PENDIENTE → CANCELADO`; pagos `RECHAZADO` como historial; pagos `VALIDADO` **nunca** se eliminan ni se modifican. Reembolsos fuera del MVP. Una inscripción `ENROLLED` **no** puede cancelarse por este flujo.

**11. Login de alumnos/tutores — APROBADO:** no. Solo usuarios administrativos.

**12. Notificaciones — APROBADO:** no (correo, SMS ni WhatsApp) fuera del MVP.

**13. PDFs / comprobantes — APROBADO:** sin constancia formal de inscripción, **pero sí comprobante de pago en PDF** cuando el pago sea `VALIDADO`, con: alumno, programa, concepto, monto, forma de pago, referencia, fecha y folio. **Sin facturación fiscal.**

**14. Dashboard y reportes — APROBADO:** sin reportes avanzados ni Excel/CSV; **sí dashboard operativo mínimo** con inscripciones pendientes, pagos por validar, documentos pendientes e inscripciones completadas. Sin gráficas ni BI.

**15. Cierre de periodo — APROBADO:** `periodos.cerrado_at` impide nuevas inscripciones y modificaciones normales del periodo; ADMIN visualiza la información histórica.

**16. Pruebas — APROBADO:** **Pest + MySQL**, con **base de datos exclusiva de testing** (nunca la de desarrollo ni producción), desarrolladas **junto con cada módulo**.

**17. Seguridad extra — APROBADO:** sin 2FA. Como mínimo: contraseñas seguras, hash nativo de Laravel, rate limiting en login, CSRF, autorización server-side, validación server-side, archivos privados, sesiones seguras y auditoría de acciones críticas. Sin secretos en el código.

**18. Localización — APROBADO:** `es-MX`, `America/Mexico_City`, **MXN**.

### ✅ Corrección de reglas (sobre mensualidades) — APROBADA

- **Las mensualidades futuras NO impiden llegar a `ENROLLED`.**
- Para finalizar es obligatorio **únicamente**: concepto **`INSCRIPCION`** pagado/validado; documentos obligatorios aceptados; **sin observaciones `FALTANTE` abiertas**; revisión final de Control Escolar (más convenio **resuelto** — `APROBADO` o `RECHAZADO` — si existe; **`PENDIENTE_APROBACION` bloquea**).
- Las mensualidades pueden existir como conceptos `PENDIENTE` con la inscripción `ENROLLED`.
- **Validar posteriormente una mensualidad NO modifica el estado del proceso.**
- La transición `PAYMENT_REVIEW → PAYMENT_VALIDATED → PENDING_DOCUMENTS` corresponde **solo al pago requerido** para completar la inscripción.
- Actualizadas **RB-3, RB-5, RB-8** y los flujos **FC-4, FC-5, FC-7**.

### ✅ Ajuste de seguridad (información médica) — APROBADO

Lectura/edición de `informacion_medicas` restringida a **ADMIN** y **ADMISIONES**; **CONTROL_ESCOLAR** solo lectura si el proceso lo requiere (revisión final); **ADMINISTRACION no debe visualizarla** (no la necesita para validar pagos). Implementado con `InformacionMedicaPolicy` + `@can` en Blade.

### ✅ Puntos residuales (Fase 1) — APROBADOS

**P1 — Comprobante PDF — APROBADO:** usar `barryvdh/laravel-dompdf` en una versión **compatible con Laravel 12**.

**P2 — Concurrencia del número de control — APROBADO con ajuste:** se agrega la tabla mínima **`consecutivos`** (`id`, `tipo`, `anio`, `ultimo_valor`, `timestamps`, **`UNIQUE (tipo, anio)`**), con `tipo = NUMERO_CONTROL`. `NumeroControlService` debe: (1) iniciar transacción; (2) obtener el consecutivo del año con `lockForUpdate()`; (3) incrementar `ultimo_valor`; (4) generar `YYYY####` con 4 dígitos; (5) asignarlo al alumno; (6) mantener `UNIQUE` en `alumnos.numero_control`; (7) completar todo dentro de la misma transacción de finalización. **No calcular con `MAX(numero_control)`.** **Año del consecutivo (Fase 12, decisión aprobada):** `anio` = año de `periodos.fecha_inicio` del periodo de la inscripción (misma regla que el `YYYY`, ver RB-9); la fila ausente se crea con `insert-or-ignore` + `UNIQUE` antes del `lockForUpdate()`, seguro ante dos procesos que inicializan el mismo año.

**P3 — Base de pruebas — APROBADO:** `sistema_escolar_testing` (MySQL), exclusiva para pruebas; **nunca** contra la BD de desarrollo o producción.

**Plan de implementación — APROBADO:** `docs/plan-implementacion.md` con 16 fases; la **Fase 1** puede iniciarse de inmediato (crear proyecto, configurar MySQL, Livewire, Tailwind/Vite, Pest, login, BD de testing y pruebas), sin avanzar a la Fase 2 hasta que la app, el login y las pruebas estén verdes y `npm run build` termine correctamente.

---

## 23. Identidad institucional y branding (Fase 1.1)

### 23.1 Marca institucional

- **Universidad Villa de Zacatecas** — nombre corto **UVZ** — lema **"Aumentamos la excelencia"** (`#AumentamosLaExcelencia`).
- Sitio oficial de referencia: <https://universidadvilladezacatecas.edu.mx/>
- Teléfono `492 161 0444`, correo `admisiones@universidadvilladezacatecas.edu.mx`, campus `Av. Pedro Coronel #38, Cañada de la Bufa` (tomados del sitio oficial).
- El sistema se presenta como una aplicación administrativa interna de la Universidad (no como un "sistema escolar" genérico).

### 23.2 Fuente única de verdad: `config/branding.php`

Todo el branding vive en `config/branding.php`: `name`, `short_name`, `system_name`, `slogan`, `hashtag`, `website`, `logo`, `logo_vertical`, `logo_dark`, `favicon`, `primary_color`, `secondary_color`, `highlight_color`, `accent_color`, `background_color`, `surface_color`, `text_color`, `muted_color`, `border_color`, `phone`, `email`, `address`.

- Las vistas **solo** consumen `config('branding.*')`; está prohibido repetir textos institucionales o HEX hardcodeados en Blade.
- `resources/views/layouts/app.blade.php` inyecta los colores como variables CSS `--brand-*` en `:root`; `resources/css/app.css` (Tailwind 4) los enlaza en `@theme` como `--color-brand-*`, de donde salen las utilidades (`bg-brand-primary`, `text-brand-muted`, `border-brand-line`, `font-display`, …).

### 23.3 Colores y tipografía (verificados, no inventados)

Colores extraídos de las variables `:root` de `styles.css` del sitio oficial:

| Token | HEX | Origen |
|---|---|---|
| `primary_color` | `#72132e` | `--wine` |
| `secondary_color` | `#4f0a1e` | `--wine-dark` |
| `highlight_color` | `#9b2244` | `--wine-bright` |
| `accent_color` | `#e6ad34` | dorado de `:focus` del sitio |
| `background_color` | `#fbfaf7` | `--paper` |
| `surface_color` | `#f4f0e9` | `--cream` |
| `text_color` | `#171517` | `--ink` |
| `muted_color` | `#696369` | `--muted` |
| `border_color` | `#ddd6ce` | `--line` |

- Tipografía: **DM Sans** (texto) y **Playfair Display** itálica (acentos), las mismas del sitio; archivos `.woff2` **auto-alojados** en `public/fonts/` (sin hotlinking).
- Logos y favicon descargados del sitio oficial y guardados en `public/images/` (PNG con fondo transparente + JPEG originales recortados); favicon generado a partir del logo vertical.
- **PENDIENTE:** el sitio no publica una variante del logo para fondos oscuros → `branding.logo_dark` reutiliza el logo horizontal mientras se resuelve.

### 23.4 Catálogo académico inicial (insumo para el `CatalogosSeeder` de la Fase 3)

Programas que el sitio institucional publica actualmente (datos iniciales, **no** una lista cerrada):

1. Licenciatura en Derecho
2. Licenciatura en Seguridad y Administración Industrial
3. Licenciatura en Ciencia Forense

El catálogo `programas` sigue siendo administrable (CRUD ADMIN, alta/baja); estas tres solo son la semilla.

**Claves internas de la semilla** (identificadores del sistema, **no** RVOE): `DER`, `SAI`, `CF`. `duracion_periodos` se siembra `NULL` mientras no exista fuente institucional; el valor lo captura el ADMIN desde el catálogo.

### 23.5 Comprobante de pago PDF (Fase 9)

El comprobante deberá usar logo UVZ, "Universidad Villa de Zacatecas", colores institucionales y datos institucionales **desde `config('branding.*')`** (ver RB-19). No se implementa en la Fase 1.

### 23.6 Entorno: Docker solo en desarrollo local

- **Aprobado:** MySQL 8 en Docker (contenedor `sistema-escolar-mysql`, puerto `3308`) **únicamente como herramienta de desarrollo local**, con BD `sistema_escolar` (desarrollo) y `sistema_escolar_testing` (pruebas) completamente separadas.
- **Docker NO es una dependencia de arquitectura:** el sistema se desplegará en hosting compartido con el MySQL del hosting. No se agregan dependencias que obliguen a Docker en producción (los drivers son los del cliente MySQL estándar).

---

## 24. ETAPA 2 — Control Escolar (decisión aprobada 02/10/2026)

> Registro mínimo de las decisiones de la **Etapa 2 — Control Escolar** (análisis completo en `docs/analisis-evolucion-control-escolar.md`; fases en `docs/roadmap-sistema-escolar.md`). **2.0-00 ✅ COMPLETADA** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK); **2.0-01 ✅ COMPLETA e integrada en master** · **2.0-02 ✅ COMPLETA e integrada en master** · **2.0-03 ✅ COMPLETA e integrada en master** · **2.0-04 ✅ COMPLETA e integrada en master** · 2.0-05 en adelante NO INICIADAS.

1. **Alcance (C4):** aprobada la **ETAPA 2 — CONTROL ESCOLAR**. F1–F14 permanecen documentadas como **ETAPA 1 — ADMISIÓN E INSCRIPCIÓN**; la historia del MVP no se reescribe.
2. **`inscripciones` reservada al ingreso (C2/C3/D-N1):** la tabla, modelo, `InscripcionService`, máquina `InscripcionEstatus` y RB-8 quedan reservados al proceso de **ADMISIÓN/INGRESO**. No se reutilizan ni se reinterpretan como flujo académico de todos los periodos.
3. **Nueva entidad `matriculas_periodo`:** representa la matrícula académica de un alumno en un periodo, con `UNIQUE(alumno_id, periodo_id)` y **máquina de estados propia** (separada de `InscripcionEstatus`). La **primera** matrícula nace de una inscripción de ingreso que llegó a `ENROLLED`; las siguientes son **reinscripciones** de periodos posteriores. `inscripciones_materias` depende de `matricula_periodo_id`, **no** de la máquina de `inscripciones`.
4. **Roles:** los 4 roles actuales (ADMIN, ADMISIONES, ADMINISTRACION, CONTROL_ESCOLAR) se conservan. **DOCENTE** aprobado para 2.0-06 (D-N2) y **ALUMNO** aprobado para 2.0-16 (D-N5) con **aislamiento estricto** (solo su propia información — C1; la D11 "alumnos sin login" sigue válida para la Etapa 1). **COORDINADOR:** no se crea por ahora.
5. **Formatos (C8):** aprobados los **formatos estándar internos UVZ** con pipeline estricto **DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF**. Fuera: SEP, MEC, MET, SIGED, certificados oficiales, titulación electrónica e integraciones gubernamentales.
6. **Datos institucionales (C7):** **no inventar** planes, materias, créditos, RVOE, fechas, modalidades ni información no proporcionada. Esto **no bloquea 2.0-01**: esa fase crea arquitectura, tablas y CRUD con **catálogos vacíos**; los datos reales se capturan cuando UVZ los proporcione.
7. **F15/F16 → 2.0-00 (C6, Opción A):** el alcance original de F15 (cobertura/matriz/regresiones) y F16 (hardening/RB-21) se ejecuta reubicado como **2.0-00 — Blindaje del núcleo existente**, **antes de 2.0-01**. **✅ COMPLETADA** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK).
8. **Decisiones diferidas (se definen al llegar su fase):** D-N3 (becas/descuentos, 2.0-12), D-N4 (extraordinarios, 2.0-07), D-N6 (exportaciones CSV/Excel, 2.0-17), D-N7 (tipos de folios de actas/constancias, 2.0-05/2.0-08).
9. **Se conservan intactas** todas las reglas críticas §10 y las decisiones §22 de la Etapa 1.

---

## Evolución futura del Sistema Escolar

El **Sistema Escolar UVZ** está concebido como un sistema escolar completo; el MVP (ETAPA 1) cubre solo la primera etapa (**Admisión e Inscripción**, F1–F14 completas). La **ETAPA 2 — Control Escolar fue aprobada en planeación (02/10/2026)** — ver **§24** y [`docs/roadmap-sistema-escolar.md`](roadmap-sistema-escolar.md) (fases 2.0-00 … 2.0-17); el análisis detallado está en [`docs/analisis-evolucion-control-escolar.md`](analisis-evolucion-control-escolar.md). **2.0-00 ✅ COMPLETADA** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK); **2.0-01 ✅ COMPLETA e integrada en master** · **2.0-02 ✅ COMPLETA e integrada en master** · **2.0-03 ✅ COMPLETA e integrada en master** · **2.0-04 ✅ COMPLETA e integrada en master** · 2.0-05 en adelante NO INICIADAS.

**Preparado para crecer, pero implementar únicamente lo que el MVP necesita.**

---

*Documento v3 — arquitectura consolidada con todas las decisiones aprobadas (incluye §23 identidad institucional, Fase 1.1; §24 registro de la ETAPA 2 — Control Escolar, 02/10/2026). `docs/plan-implementacion.md` contiene el orden definitivo por fases (Fase 1 y 1.1 completas; Fase 2 no iniciada).*