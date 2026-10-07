# Guion de presentación — DEMO-PRESENTACION-2026

> Escenario **100 % sintético**: marcador `DEMO-PRESENTACION-2026` en notas/observaciones,
> claves `DEMO-*`/`DEM-*`, correos `@uvz-demo.test`, CURPs y teléfonos ficticios.
> **Sin datos personales reales.** Nada de esto se versiona con datos reales.
>
> **Credenciales (ROL · EMAIL · PASSWORD · URL):** `~/.ssh/uvz_demo_presentacion` (permiso 600).
> No están en este documento, en el repositorio, en el log ni en el reporte. Copiar de ahí al portapapeles.
>
> **Duración estimada:** 15–20 min · **15 pasos** · recorrido por rol (ADMIN → CONTROL_ESCOLAR →
> ADMINISTRACION → DOCENTE → ALUMNO), cerrando con aislamiento y responsive.
>
> Entorno: demo `https://darkcyan-mongoose-808972.hostingersite.com`, código desplegado en
> `d5fb5d4` (macrobloque 2.0-13 → 2.0-16) al momento de escribir el guion. **Correcciones QA
> pre-2.0-17 aplicadas y desplegadas:** ver «Correcciones QA pre-2.0-17» al final.

## Antes de empezar (1 min)

1. Abrir el archivo de credenciales y tener a mano las contraseñas de los 6 roles.
2. Backup previo ya creado: `backups/bd-presentacion-20261006-160508.sql.gz` (600).
3. Navegador en 1440 px (el responsive se muestra en el paso 15).

## Recorrido (15 pasos)

| # | Rol | Pantalla | Qué se muestra |
|---|---|---|---|
| 1 | ADMIN | `/login` → `/dashboard` | Login con la cuenta `ADMIN`; panel operativo con contadores y el menú lateral con todos los módulos. |
| 2 | ADMIN | `/alumnos` | 16 expedientes (13 demo); abrir **Castillo Demo** → contactos e info médica con datos ficticios (curp/telefono/fecha de nacimiento inventados). |
| 3 | ADMIN | `/inscripciones` | Los 13 expedientes demo en **9 estados distintos**: `DRAFT` Torres · `DATA_COMPLETE` Mendoza · `PENDING_PAYMENT` López · `PAYMENT_REVIEW` Hernández · `PENDING_DOCUMENTS` Martínez · `DOCUMENT_REVIEW` Ramírez · `FINAL_REVIEW` Gutiérrez · `ENROLLED` ×5 (Castillo, Vargas, Navarro, Silva, Ortiz) · `CANCELLED` Cruz. |
| 4 | ADMIN | `/inscripciones/{López}/documentos` | Expediente de admisión con **14 documentos sintéticos** (`demo_*.pdf`), panel de validación, observaciones y revisión final; el expediente de López muestra un **pago RECHAZADO** (el de Hernández, uno PENDIENTE). |
| 5 | ADMIN | `/admin/periodos` + `/admin/planes-estudio` | Periodos `DEMO-2026-1` (histórico, cerrado) y `DEMO-2026-2` (vigente); plan `DEM-2026` con versión `DEMO-1` **VIGENTE**. |
| 6 | ADMIN | `/admin/materias` + `/admin/grupos` + `/admin/aulas` | 6 materias `DEM-M1…DEM-M6` con **prerrequisito obligatorio M3 ← M1**; 11 grupos `DEM-G1…DEM-G11` (6 en el periodo histórico y 5 en el vigente) repartidos entre 2 docentes, con horarios y aulas `DEMO-A1/A2`. |
| 7 | ADMIN | `/admin/actas` + `/historial` | Acta histórica **CERRADA con folio `ACT-2026-…`** y acta vigente **ABIERTA**; en Historial, el Kardex de Jorge Castillo con materias aprobadas y una reprobada (promedio, avance y créditos). |
| 8 | CONTROL_ESCOLAR | `/servicio-social` + `/practicas-profesionales` | 4 servicios sociales (3 `VALIDADO`, 1 `EN_PROCESO` al 60 %) y 4 prácticas (3 `VALIDADO`, 1 `EN_PROCESO`), filtros y modal de alta/captura. |
| 9 | CONTROL_ESCOLAR | `/egreso` + `/titulacion` | 2 egresos: Ricardo Vargas **cumple todos los requisitos** vs. Gabriela Ortiz **con 4 requisitos faltantes**; 2 titulaciones: Héctor Silva `TITULADO` con folio `DEMO-TIT-001` y Valeria Navarro `EN_TRAMITE` sin folio. |
| 10 | CONTROL_ESCOLAR | `/formatos-academicos` | Generar `CONSTANCIA_INSCRIPCION` y `KARDEX` de Jorge → descarga inmediata en PDF (cabecera `%PDF`, disco privado). Desde la corrección QA pre-2.0-17 también se generan **TIRA** y **ACTA** (el acta reutiliza su folio `ACT-2026-…`, se puede volver a generar y la descarga aparece en el mismo aviso). **No intentar HORARIO ni LISTA_GRUPO**: sólo existen en `/admin/formatos` (ADMIN) y en la DEMO ninguna matrícula coincide con el periodo de las inscripciones `CURSANDO`, así que la pantalla responde «El alumno no está inscrito en este grupo.» (límite de los datos de la DEMO, no un defecto). |
| 11 | ADMINISTRACION | `/becas-descuentos/becas` + `/becas-descuentos/estado-cuenta` | Beca `DEMO-BEC-01` 20 % tipo `PORCENTAJE` alcance `AMBOS`; en estado de cuenta se ven los **5 ajustes `DESCUENTO_BECA`** (300 + 4×240 = **1,260** aplicados sobre los cargos). |
| 12 | ADMINISTRACION / ADMISIONES | `/inscripciones/{Castillo}` | Estado de cuenta de Jorge: `INSCRIPCION` 1,500 **PAGADA**, mensualidades 1 y 2 **PAGADAS**, 3 y 4 **PENDIENTES** (1,200 c/u) → 3 pagos `VALIDADO`; botón de comprobante institucional `/pagos/{id}/comprobante-pdf` (PDF generado bajo demanda). |
| 13 | DOCENTE | `/docente` → `/docente/grupos/{DEM-G1}` | Portal del docente con **sus** grupos (`DEM-G1`, `DEM-G7`, `DEM-G8`); detalle con evaluaciones `Parcial 1/2 DEMO`, calificaciones capturadas (92, 88, 85, 78, 66…), asistencia y acta. Abrir después `/docente/grupos/{DEM-G2}` → **403** (grupo de otro docente: aislamiento). |
| 14 | ALUMNO | `/alumno` → `/alumno/kardex`, `/horario`, `/estado-cuenta`, `/documentos` | Portal de Jorge Castillo: Kardex con el plan DEMO, horario con los grupos vigentes (`DEM-G8/G9/G10`), estado de cuenta con la beca y el saldo pendiente, y 7 formatos emitidos descargables (`%PDF`). Abrir `/inscripciones` o `/dashboard` → **403** (aislamiento C1: identidad siempre desde `auth()->user()->alumno`). |
| 15 | ADMIN | Responsive + cierre | 1920 / 768 / 390 px en dashboard, inscripciones, grupos, servicio social y portal alumno: **sin desbordes horizontales**. Cierre: 0 HTTP ≥ 500, 0 `console.error`, 0 PDFs servidos desde `public_html`, log `production.ERROR` **sin variación frente al baseline 138**. |

## Verificación de aislamiento (si hay tiempo)

| Rol | Ruta | Esperado |
|---|---|---|
| ADMISIONES | `/servicio-social`, `/egreso`, `/historial`, `/formatos-academicos`, `/becas-descuentos/*` | 403 |
| ADMISIONES · CONTROL_ESCOLAR · ADMINISTRACION | `/admin/materias`, `/admin/grupos` (sólo ADMIN) | 403 |
| DOCENTE | `/dashboard`, `/alumnos`, `/alumno` | 403 |
| ALUMNO | `/dashboard`, `/docente`, `/inscripciones`, `/alumnos/{id}` | 403 |
| Sin sesión | `/documentos-generados/{id}`, `/pagos/{id}/comprobante-pdf`, `/documentos/{id}/archivo` | 302 a `/login` y **nunca** `application/pdf` |

## Hallazgos conocidos (NO se muestran; resueltos por la corrección QA pre-2.0-17)

- **DEMO-BUG-01 → BUG-02 del QA** — `FormatoService::prepararDatos()` (`app/Services/FormatoService.php:206`)
  llamaba `$grupo->turno?->label()` pero `Grupo::$turno` era un `string` sin cast de enum →
  `Call to a member function label() on string` (HTTP 500) en **cualquier formato que recibe `grupo`**:
  `HORARIO`, `LISTA_GRUPO` y `ACTA`. **Corregido** (cast `TurnoGrupo` + consumidores ajustados).
- **DEMO-BUG-02 → BUG-03 del QA** — `HistorialAcademicoService::tiraMaterias()` no emitía la clave
  `es_reprobada` y `tira.blade.php` la consultaba → `Undefined array key` (HTTP 500) al generar **TIRA**.
  El test existente no lo cubría porque su matrícula de fábrica no tenía `plan_version_id`.
  **Corregido** (contrato completo del DTO).
- **Asistencia → BUG-05 del QA** — «Guardar asistencia» mostraba *This page has expired* (HTTP 419).
  Causa real: un `TypeError` (`AsistenciaService::registrarMasivo()` recibía la fecha como `string`
  y el payload como `array` de `string`) que **Livewire convierte en `abort(419)`** cuando
  `APP_DEBUG=false` (`HandleRequests.php:221-226`), enmascarando el error real como sesión caducada.
  **Corregido**; además se blindó la autorización del DOCENTE y la validación de fecha/lista vacía.
- Extraordinario: **no construible** dentro de las reglas actuales (sin datos suficientes de la
  convocatoria) — no se simuló en la demo. **El `ENUM inscripciones_materias.estatus` ya sí contiene
  `EXTRAORDINARIO`** desde el saneamiento pre-2.0-17 (migración incremental `2026_10_06_000001_…`);
  el estado es una limitación de datos, no de esquema.

## Correcciones QA pre-2.0-17 (BUG-01 … BUG-05)

| # | Defecto | Corrección | Prueba |
|---|---|---|---|
| BUG-01 | 403 al generar formatos: la acción exigía `documento_generado.crear` (sólo ADMIN) mientras la pantalla pedía `formato_academico.generar` | Regla única en ambas capas (`formato_academico.generar` **y** `DocumentoGeneradoPolicy::create`), fuera del `try` que tragaba la excepción; el botón se oculta con `@can` | `FormatoPermisosTest` (matriz de 6 roles: entra/genera) |
| BUG-02 | `Grupo::$turno` sin cast → 500 en HORARIO, LISTA_GRUPO y ACTA | Cast `TurnoGrupo` + `$grupo->turno?->label()` en servicio, componente y vistas | `FormatoAcademicoTest` (grupo recargado de la BD) |
| BUG-03 | `tiraMaterias()` sin `es_reprobada` ni el resto del contrato → 500 en TIRA | Contrato completo (aprobada/reprobada/cursando/baja/extaordinario/equivalencia); `total_materias` y `es_extraordinario` corregidos en el PDF | `HistorialAcademicoTest` |
| BUG-04 | 8 de 12 plantillas con `<div class="section">` sin cerrar y 8 con clases de estado inexistentes (sin color en filas ni insignias) | `</div>` faltante + reglas `.table-*`/`.bg-*` en el layout (paleta UVZ, sin Tailwind ni Vite) | `PdfPlantillasTest`, `FormatoCatalogoPdfTest` (los 12 formatos) |
| BUG-05 | «Guardar asistencia» → 419 «This page has expired» | Causa real `TypeError` en el guardado (ya corregido); **no** es CSRF ni sesión caducada y no se tocó seguridad | QA en DEMO con sesión nueva (ver §32) |
| — (§14) | La asistencia **no persistía**: payload `array` en vez de `bool`, `fecha` como `string` y el DOCENTE no se re-autorizaba contra el grupo | Mapeo `id => bool`, `Carbon::parse(fecha)` validada, re-autorización y rechazo de lista vacía | `AsistenciaGuardarReproTest` |
| — (N-1) | El **ACTA** se generaba pero **nunca aparecía «Descargar PDF»** | El acta es un documento del grupo: se registra sin `alumno_id` y el componente filtraba por el alumno seleccionado; el filtro se anula **sólo para ACTA** | `DescargaDocumentoGeneradoTest` |
| — (N-2) | Regenerar el mismo **ACTA** estallaba con `SQLSTATE 23000 / 1062` en pantalla | `folio` es UNIQUE y el acta reutiliza `actas.folio`; persistencia **idempotente por folio** (una sola fila, emisión refrescada, `409` si el folio es de otro documento). Las constancias conservan folio y fila propios por emisión | `DescargaDocumentoGeneradoTest` |
| — (N-3) | En `/admin/formatos`, pulsar «Generar PDF» **no explicaba nada** cuando fallaba la validación | La vista no pintaba `grupoSeleccionado` ni `matriculaSeleccionada`; ahora se pintan con el patrón `@error` + `ui-error` ya usado allí (sin tocar qué campos son requeridos) | `DescargaDocumentoGeneradoTest` |

**N-3 · límite de la DEMO:** HORARIO y LISTA_GRUPO exigen una matrícula en el
mismo periodo que el grupo con inscripción `CURSANDO`; en los datos de la
presentación esas dos condiciones nunca coinciden, así que esos dos formatos
no se muestran en la demo.

Registro completo: `docs/auditoria-pdfs-pre-2.0-17.md` (BUG-04) y `docs/estado-proyecto.md`.

## Verificación ejecutada (§24/§25)

- 6 perfiles × sus pantallas: **136 comprobaciones PASS, 0 FAIL**; 26 rutas esperadas devolvieron
  exactamente su 403; 0 HTTP ≥ 500, 0 `console.error`, 0 `pageerror`.
- PDFs autenticados (`documentos-generados`, `comprobante-pdf`, `documentos/archivo`): 200 +
  `application/pdf` + cabecera `%PDF-`; sin sesión: 302 y sin PDF.
- Responsive 1440 (§24) / 1920 / 768 / 390 sin overflow horizontal.

## Restricciones del escenario

- Datos marcados `DEMO-PRESENTACION-2026`; nada de personas reales ni integración SEP/MEC/MET/SIGED.
- El fixture `uvz_demo_fixture.php` se ejecutó fuera de `public_html` y **ya fue eliminado** del servidor.
- Credenciales únicamente en `~/.ssh/uvz_demo_presentacion` (600); el passfile temporal del servidor se borró.
- Este documento **queda versionado en el repositorio** desde el saneamiento pre-2.0-17 (2026-10-07):
  se revisó antes de commitearlo y **no contiene contraseñas, secretos ni datos personales reales**
  (el escenario es 100 % sintético y las credenciales sólo viven en `~/.ssh/uvz_demo_presentacion`, 600).
