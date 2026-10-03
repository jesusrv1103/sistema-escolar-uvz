# Análisis — Evolución hacia Sistema de Control Escolar UVZ (Roadmap 2.0)

> **Documento de análisis, SIN implementar.** No contiene migraciones ni código; solo documentación.
> **Actualizado con las decisiones aprobadas (02/10/2026)** — contradicciones C1–C8 resueltas (§16), decisiones D-N* aprobadas/diferidas (§17).
>
> **Alcance del nuevo escenario:** conservar y reutilizar todo lo construido en F1–F14 y evolucionarlo hacia un Sistema de Control Escolar universitario (**ETAPA 2 — CONTROL ESCOLAR**). Los documentos académicos usarán **formatos estándar internos de UVZ**; **fuera de alcance**: formatos oficiales SEP, certificados oficiales, titulación electrónica, MEC, MET, SIGED e integraciones gubernamentales.
>
> **Fuentes revisadas por completo:** `docs/reglas-desarrollo.md`, `docs/arquitectura-mvp.md` (v3, §1–§23), `docs/plan-implementacion.md`, `docs/estado-proyecto.md`, `docs/roadmap-sistema-escolar.md`; código de apoyo: `app/Providers/AuthServiceProvider.php`, `app/Enums/*`, `app/Services/*`, `app/Livewire/*`, `database/migrations/*`.
> **F1–F14 = ETAPA 1 — ADMISIÓN E INSCRIPCIÓN (COMPLETA)** · **F15/F16 → 2.0-00 (NO iniciadas — estado histórico previo a ejecución)** · **2.0-00 ✅ COMPLETADA (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK)** · **2.0-01 ✅ COMPLETA e integrada en master** · **2.0-02 en adelante NO INICIADAS.**

---

## 1. Qué partes de F1–F14 se reutilizan sin cambios

| Activo (F1–F14) | Reutilización | Notas |
|---|---|---|
| Stack: Laravel 12, Livewire 4, Blade, Tailwind 4, MySQL 8, Pest, Vite, monolito | **Total, sin cambios** | `reglas` §3: mantener; el nuevo alcance no exige ningún cambio de stack |
| Seguridad: login, rate limiting, regeneración de sesión, middleware `role`, `Gate::before` (ADMIN), RB-21, `@can` + server-side | **Total** | El patrón de 3 capas (policy server-side → `@can` UX → validación server-side) se replica en cada módulo nuevo |
| Mapa único `CAPACIDADES` en `AuthServiceProvider` (20 capacidades, §8.2) | **Total (aditivo)** | Las capacidades nuevas se **agregan** al mapa; el patrón y la fuente única se conservan |
| 4 roles (`users.rol`, D1: un rol por usuario, sin spatie) | **Total** | Se conservan ADMIN, ADMISIONES, ADMINISTRACION y CONTROL_ESCOLAR; ampliaciones aprobadas: `DOCENTE` (2.0-06, D-N2) y `ALUMNO` (2.0-16, D-N5); `COORDINADOR` no se crea por ahora |
| Identidad institucional: `config/branding.php`, layout, tokens `--brand-*`, fuentes/assets locales | **Total** | Todo formato nuevo consume `config('branding.*')` (§23) |
| Expediente del alumno: `alumnos` (CURP, `numero_control`), `contactos`, `informacion_medicas` (RB-18) | **Total** | Base del aspirante/alumno; la información médica **no** entra en formatos ni portales salvo permiso explícito |
| `AuditoriaService` + trait `HasAudit` + tabla `auditoria` morph (RB-12, append-only) | **Total, transversal** | Cualquier entidad nueva audita con el mismo mecanismo (acción, responsable, estado anterior→nuevo) |
| **`inscripciones` + máquina `InscripcionEstatus` + `InscripcionService` + RB-8** | **Total** | **Reservada al proceso de ADMISIÓN/INGRESO (Etapa 1).** No se reutiliza ni se reinterpreta como flujo académico de todos los periodos (C2/C3): la matrícula por periodo es la entidad `matriculas_periodo` (§4) |
| Catálogos: base `CatalogoAdmin` + rutas `/admin/*` (`role:ADMIN`) | **Total como patrón** | Se reutiliza para planes, materias, docentes, aulas, grupos |
| `periodos` (catálogo genérico, `cerrado_at`) | **Total** | Reutilizable en grupos, cargas, calificaciones y `matriculas_periodo`; `cerrado_at` bloquea **solo inscripciones** (R2 documentado) |
| `programas` | **Total, con FK pendiente** | Podrá relacionarse con `planes_estudio` sin cambios en la Etapa 1 |
| `consecutivos` + `NumeroControlService` (lockForUpdate, sin `MAX()`) | **Total como patrón** | Reutilizable para folios de actas, constancias, kardex (ampliando `TipoConsecutivo`; detalle diferido D-N7) |
| Pagos: `conceptos_pago`, `pagos`, `PagoService`, four-eyes, folio `PAG-…`, comprobante PDF | **Total** | Base del estado de cuenta; pagos validados siguen intactos (RB-15) |
| Documentos: `DocumentoService` (disco privado, MIME `finfo`, compensación, upsert) | **Total como motor** | Con ajuste puntual para expediente académico (riesgo **R1**, ver §2) |
| Observaciones append-only (`ObservacionService`) | **Total** | Reutilizable como faltantes académicos |
| `ComprobantePagoService` (datos → plantilla Blade → DomPDF bajo demanda) | **Total como patrón** | Es exactamente la arquitectura de formatos requerida (§10.2) |
| `DashboardService` + `PanelOperativo` (lectura, `viewAny`) | **Total como patrón** | Reportes/avance parten de este estilo (consultas en servicio, no en Blade) |
| Wizard con borrador en BD, `ListaInscripciones`, `DetalleInscripcion`, `PanelDocumentos`, `PanelEstados` | **Total** | El flujo de admisión permanece intacto |
| Número de control: por alumno, `YYYY####`, una sola vez (D3) | **Total** | Las matrículas/reinscripciones posteriores conservan el NC |
| `UNIQUE(alumno_id, periodo_id)` en `inscripciones` (D2) | **Total** | Sigue gobernando el vínculo de **ingreso**; el vínculo académico por periodo tiene su propia UNIQUE en `matriculas_periodo` (§4) |
| Pruebas Pest + BD exclusiva `sistema_escolar_testing` (P3), cierre de fase, Git por ramas, docs por fase | **Total** | Se mantienen tal cual para todas las fases de la Etapa 2 |

## 2. Qué partes requieren ampliación

| Activo | Ampliación requerida | Impacto en lo existente |
|---|---|---|
| `RolUsuario` (enum) | Agregar `DOCENTE` (2.0-06) y `ALUMNO` (2.0-16) — patrón ya previsto en **R3** | Nulo en esquema; sí toca `CAPACIDADES`, `AdminSeeder`, middleware `role` y pruebas de matriz |
| `AuthServiceProvider::CAPACIDADES` | Nuevas capacidades (matricular, calificar, cerrar actas, gestionar grupos, becas, etc.) | Aditivo, sin romper las 20 actuales |
| `documentos` (UNIQUE `(inscripcion_id, tipo_documento_id)`) | Riesgo **R1** documentado: `alumno_id` **nullable** o tabla `expediente_documentos` con FK a `alumnos` | Requiere migración incremental aprobada; la Etapa 1 no cambia |
| `programas` | Relación con `planes_estudio`; campos institucionales (RVOE, modalidad, campus) solo cuando exista fuente (**no inventar**, C7) | Ninguna ruptura |
| `periodos` | Definir cierres **por módulo** (R2): `cerrado_at` sigue bloqueando solo inscripciones; cada módulo nuevo define su cierre | Semántica ya documentada |
| `consecutivos` / `TipoConsecutivo` | Nuevos tipos de folio (acta, constancia, kardex…) — tipos exactos diferidos (**D-N7**) | Enum aditivo, sin tocar el algoritmo |
| Navegación del layout | Hoy **sin menú de módulos** (Fase 1.1): se requiere un menú por módulos | Extensión prevista desde Fase 1.1 |
| `TipoConcepto` / cargos | Concepto de reinscripción, recargos, becas/ajustes | Diseño diferido (**D-N3**) |
| `Alumno` | Vínculo opcional a `users` (portal docente/alumno) y expediente académico (servicio social, prácticas, titulación) | Tablas nuevas, sin tocar `alumnos` salvo FKs |
| Listados y dashboard | Enlaces y contadores académicos adicionales | Aditivo |

## 3. Nuevos módulos necesarios

- **A. Estructura académica:** planes de estudio, **versiones** de planes, materias, créditos, seriación/prerrequisitos.
- **B. Recursos:** docentes, aulas.
- **C. Organización de grupos:** grupos, horarios, carga académica (asignación docente/materia/periodo/aula).
- **D. Matrícula académica:** `matriculas_periodo` (matrícula por periodo y reinscripciones), carga del alumno, inscripción de materias.
- **E. Evaluación:** evaluaciones, calificaciones, extraordinarios.
- **F. Operación de aula:** asistencia, actas de calificaciones.
- **G. Históricos:** kardex, historial académico, avance académico, promedio, créditos cursados/aprobados/pendientes.
- **H. Estatus del alumno:** bajas temporales/definitivas, bajas de materias, equivalencias/revalidaciones internas.
- **I. Finanzas ampliadas:** becas/descuentos, estado de cuenta, adeudos.
- **J. Documentos académicos:** motor de formatos + los 15 formatos internos UVZ (§10.2).
- **K. Servicios de cierre:** servicio social, prácticas profesionales, egreso, titulación.
- **L. Portales:** portal de alumno (2.0-16), portal de docente (2.0-15).
- **M. Reportes:** reportes operativos (académicos y administrativos).

## 4. Nuevas entidades/tablas propuestas

> Convenciones actuales aplicadas: PK `id` BIGINT UNSIGNED, `utf8mb4_unicode_ci`, `DECIMAL(10,2)`, estados en `VARCHAR` + Backed Enums (sin `ENUM` nativo), FK con `onDelete` explícito, índices en columnas de filtrado. Tablas nuevas con **soft delete** solo donde tenga sentido (catálogos y expediente), **nunca** en tablas financieras ni de calificaciones/actas (R15).
> **Total propuesto: 23 tablas nuevas** (los detalles de campos pertenecientes a cada fase se diseñan en su fase; aquí el alcance conceptual aprobado).

| # | Tabla | Campos clave (resumen) | Observaciones |
|---|---|---|---|
| 1 | `planes_estudio` | `programa_id` FK, `clave`, `nombre`, `resolucion`/`rvoe` nullable, `activo` | 1 programa → n planes |
| 2 | `plan_versiones` | `plan_id` FK, `version`, `fecha_vigencia`, `estatus` (PROPUESTA/VIGENTE/SUPERADA), `aprobado_por/at` | Versionado; el alumno se vincula a la versión vigente |
| 3 | `materias` | `plan_version_id` FK, `clave`, `nombre`, `creditos`, `horas_teoricas/horas_practicas`, `semestre`/`bloque`, `obligatoria` | Créditos por materia |
| 4 | `materia_prerrequisitos` | `materia_id`, `prerrequisito_id` (FK a `materias`), `tipo` | Autoreferencia; seriación/colocación |
| 5 | `docentes` | `user_id` FK **nullable**, datos de identificación, especialidad, `activo` | Vínculo a cuenta con el portal de docente |
| 6 | `aulas` | `clave`, `edificio`, `capacidad`, `activo` | Catálogo |
| 7 | `grupos` | `materia_id`, `periodo_id`, `docente_id`, `aula_id` FKs, `clave`, `cupo`, `turno`, `estatus` | Lógica única `materia+periodo+clave` |
| 8 | `grupo_horarios` | `grupo_id` FK, `dia` (enum), `hora_inicio`, `hora_fin` | Validación de conflicto aula/docente/alumno |
| 9 | **`matriculas_periodo`** | `alumno_id` FK, `periodo_id` FK, `programa_id` FK, `plan_version_id` FK nullable, `inscripcion_origen_id` FK **nullable** → `inscripciones` (ingreso que llegó a `ENROLLED`), `estatus` (**máquina propia**, detalle en 2.0-04), fechas/responsables (`creado_por`, `matriculada_por/at`), timestamps, auditoría `HasAudit` | **UNIQUE `(alumno_id, periodo_id)`**. Representa la matrícula académica de un alumno en un periodo. Primera matrícula nace de una inscripción de ingreso `ENROLLED`; las siguientes son reinscripciones de periodos posteriores (C2/C3/D-N1) |
| 10 | `inscripciones_materias` (carga académica) | **`matricula_periodo_id` FK** (NO depende de `inscripciones`), `grupo_id` FK, `estatus` (`CARGADA`/`CURSANDO`/`APROBADA`/`REPROBADA`/`BAJA`/`EXTRAORDINARIO`), `calificacion_final` nullable, `cargado_por/at`, `baja_*` | **UNIQUE `(matricula_periodo_id, grupo_id)`**; alumno se deriva de la matrícula; tabla de cálculo de avance y kardex |
| 11 | `evaluaciones` | `grupo_id` FK, `nombre`, `tipo` (PARCIAL/EXAMEN/PROYECTO/…), `ponderacion`, `fecha` | Pesos validados a 100 % por grupo |
| 12 | `calificaciones` | `inscripcion_materia_id` FK, `evaluacion_id` FK, `valor` DECIMAL(5,2), `capturada_por/at`, `observacion` nullable | **UNIQUE `(inscripcion_materia_id, evaluacion_id)`**; cambios auditados |
| 13 | `actas` | `grupo_id`, `periodo_id` FKs, `folio` (consecutivo; tipos exactos → D-N7), `estatus` (`ABIERTA`/`CERRADA`), `cerrada_por/at` | Cierre four-eyes: docente captura → Control Escolar cierra |
| 14 | `asistencias` | `inscripcion_materia_id` FK, `fecha`, `presente` boolean, `registrado_por` | Diaria por alumno de grupo |
| 15 | `bajas` | `alumno_id` o `inscripcion_materia_id`, `tipo` (`TEMPORAL`/`DEFINITIVA`/`MATERIA`), `motivo`, `fecha`, `reingreso_at` nullable, `registrada_por` | Historial; la baja de materia actualiza `inscripciones_materias.estatus` |
| 16 | `equivalencias` | `alumno_id` FK, `materia_id` FK nullable, `origen` (texto), `creditos`, `dictamen`, `autorizada_por` | Revalidaciones internas |
| 17 | `becas` (catálogo) | `nombre`, `tipo` (`PORCENTAJE`/`MONTO`), `valor`, `activo` | Reglas detalladas diferidas (**D-N3**) |
| 18 | `becas_alumnos` (asignación) | `alumno_id`, `beca_id`, `vigencia`, aprobación (ADMINISTRACION), responsables | Diseño definitivo en 2.0-12 (**D-N3**) |
| 19 | `servicio_social` | `alumno_id`, actividad, dependencia, fechas, avance %, estatus, validación | Cierre de egreso |
| 20 | `practicas_profesionales` | `alumno_id`, empresa, fechas, asesor, calificación, estatus | Cierre de egreso |
| 21 | `egresos` | `alumno_id`, `plan_version_id`, fecha/estatus de egreso, requisitos cumplidos, responsables | Verificación de créditos + servicios |
| 22 | `titulaciones` | `alumno_id`, modalidad interna, folio interno, estatus (`EN_TRAMITE`/`TITULADO`), fechas | **Formatos internos** (sin MEC/MET/SIGED) |
| 23 | `documentos_generados` (control de formatos) | `alumno_id`/`matricula_periodo_id`/`grupo_id`, `tipo` (enum del formato), `folio` (consecutivo), `emitida_por/at`, snapshot de datos | Trazabilidad de emisiones; el PDF se genera bajo demanda |

**No se proponen** (siguen fuera): tablas de notificaciones, pasarela de pagos, CFDI, multi-campus, RBAC granular.

## 5. Relaciones principales (ER)

```
programas 1 ── n planes_estudio 1 ── n plan_versiones 1 ── n materias
materias  n ── n materias                 (materia_prerrequisitos, autoreferencia)

inscripciones (INGRESO, Etapa 1, máquina intacta)
   │  1 ── 0..n  (solo la primera matrícula nace de una inscripción ENROLLED)
   ▼
matriculas_periodo  n ── 1 alumnos · n ── 1 periodos · n ── 1 programas · n ── 0..1 plan_versiones
   UNIQUE(alumno_id, periodo_id) · máquina de estados PROPIA · auditoría HasAudit
   │  1 ── n
   ▼
inscripciones_materias (carga) n ── 1 grupos
grupos  n ── 1 materias (vía plan_version) · n ── 1 periodos · n ── 0..1 docentes · n ── 0..1 aulas
grupos  1 ── n grupo_horarios · 1 ── n evaluaciones · 1 ── 0..1 actas (por periodo)
inscripciones_materias 1 ── n calificaciones (n ── 1 evaluaciones) · 1 ── n asistencias

alumnos 1 ── n inscripciones (Etapa 1, UNIQUE alumno+periodo) · 1 ── n matriculas_periodo
alumnos 1 ── n bajas · 1 ── n equivalencias · 1 ── n becas_alumnos
alumnos 1 ── 1 servicio_social · 1 ── 1 practicas_profesionales · 1 ── 0..1 egresos · 1 ── 0..1 titulaciones
users   1 ── n docentes (opcional, portal) · roles DOCENTE/ALUMNO aprobados para 2.0-06 / 2.0-16
auditoria morph sigue siendo transversal a todas las entidades
```

## 6. Nuevos estados y reglas de negocio

**Principio:** una **máquina de estados por entidad**, copiando el patrón aprobado (constante `TRANSICIONES`, transacción + auditoría + motivo en retrocesos). La máquina de `inscripciones` **no se toca** (regla crítica §10) y sigue siendo la única vía de modificar `inscripciones.estatus`.

| Entidad | Estados (mínimos) | Reglas |
|---|---|---|
| **`matriculas_periodo`** | **Máquina PROPIA, separada de `InscripcionEstatus`**; diseño de estados y transiciones en **2.0-04** (aquí solo se aprueba la entidad y su UNIQUE) | Origen: la primera nace de una inscripción de ingreso `ENROLLED`; las siguientes son reinscripciones; ninguna transición reutiliza `InscripcionService` |
| `grupos` | `CREADO → ABIERTO → EN_CURSO → CERRADO` (+ `CANCELADO`) | Inscripción a materias solo en `ABIERTO`; cierre solo Control Escolar |
| `inscripciones_materias` (carga) | `CARGADA → CURSANDO → APROBADA \| REPROBADA` (+ `BAJA`, `EXTRAORDINARIO`) | Alta solo con seriación cumplida, cupo y sin conflicto de horario; `BAJA` no aporta créditos; `REPROBADA` permite volver a cursar |
| `evaluaciones`/`calificaciones` | sin máquina; **captura restringida** | Solo el docente del grupo (y CE/ADMIN) captura; cambios auditados; ponderaciones = 100 % |
| `actas` | `ABIERTA → CERRADA` (cierre definitivo en Etapa 2) | Four-eyes docente→Control Escolar; folio con `consecutivos` (D-N7) |
| `plan_versiones` | `PROPUESTA → VIGENTE → SUPERADA` | Solo una `VIGENTE` por plan; aprobación ADMIN |
| `bajas` | registro con `tipo` y fecha; `TEMPORAL` con `reingreso_at` | Efecto: bloquea carga hasta reingreso; definitiva cierra el expediente académico |
| `titulaciones` | `EN_TRAMITE → TITULADO` (+ `NO_TITULADO`) | Formato interno; sin integraciones SEP/MEC/MET/SIGED |

**Reglas de negocio nuevas (propuestas, aprobadas en bloque — detalle en su fase):**
- **RB-A1 Seriación:** no inscribir materia sin prerrequisitos aprobados (excepción: autorización con motivo).
- **RB-A2 Cupo y horario:** no exceder `cupo`; sin conflicto de horario para alumno, docente ni aula.
- **RB-A3 Four-eyes académico:** calificaciones = docente del grupo; cierre de actas = Control Escolar; becas = aprueba ADMINISTRACION.
- **RB-A4 Avance/promedio:** `promedio` ponderado por créditos; `avance %` = créditos aprobados ÷ créditos del plan versión; fórmula única en un servicio (patrón `DashboardService`).
- **RB-A5 Créditos:** solo cuenta lo `APROBADO`; bajas y reprobaciones no aportan.
- **RB-A6 Integridad financiera:** becas/recargos no alteran pagos `VALIDADO` (RB-15 intacto); las mensualidades siguen no bloqueando `ENROLLED`.
- **RB-A7 Formatos:** todo PDF pasa por **DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF** (§10.2); sin datos médicos (RB-18).

**Se conservan intactas** las reglas críticas §10 (NC del alumno, `UNIQUE(alumno_id, periodo_id)` de ingreso, INSCRIPCION≠MENSUALIDAD, mensualidades no bloquean, pagos validados inmutables, auditoría append-only, máquina única de `inscripciones`, `ENROLLED`/`CANCELLED` terminales, sin `MAX(numero_control)`).

## 7. Roles actuales que pueden conservarse

| Rol | Se conserva | Función académica natural |
|---|---|---|
| **ADMIN** | Sí, sin cambios (`Gate::before`) | Todo, incluida aprobación de planes/versiones |
| **ADMISIONES** | Sí | Captura del aspirante, proceso de ingreso y reinscripción operativa |
| **ADMINISTRACION** | Sí | Aprueba becas/descuentos y conceptos de periodo; valida pagos (four-eyes existente) |
| **CONTROL_ESCOLAR** | Sí, **protagonista académico** | Matrículas, cierra actas, kardex/historial/avance, bajas, equivalencias, emisión de formatos |

La matriz §8.2 **se amplía aditivamente**; ninguna capacidad actual cambia de sentido.

## 8. Nuevos roles que realmente serían necesarios (y justificación)

| Rol | Decisión | Justificación |
|---|---|---|
| **DOCENTE** | **APROBADO** (D-N2, se crea en **2.0-06**) | Capturar evaluaciones y asistencia **solo de sus grupos** (RB-A3). Sin él, CE capturaría todo (pérdida de responsabilidad real) o se asignaría a usuarios existentes (incorrecto). Habilita el portal de docente. Extensión del enum prevista en **R3**; sigue habriendo un rol por usuario (no es RBAC granular) |
| **ALUMNO** | **APROBADO** (D-N5, se crea en **2.0-16**) | Lectura de su propia información únicamente, con **aislamiento estricto** (solo su kardex/horario/estado de cuenta/formatos). La decisión histórica "alumnos sin login" (D11) sigue siendo válida **para la Etapa 1**; se amplía para la Etapa 2 |
| **COORDINADOR** | **NO crear por ahora** | ADMIN + CONTROL_ESCOLAR cubren coordinación; crearlo ahora añadiría complejidad de permisos que §19 mantiene fuera. Se evalúa si la operación real lo demuestra |

## 9. Flujo completo: aspirante → alumno → matrícula académica → egreso

1. **Aspirante:** ADMISIONES captura expediente (`alumnos`) e inscripción en `DRAFT` — *no se propone tabla "aspirante": el expediente es `alumnos` y la `inscripciones` en `DRAFT` representa el proceso en admisión*.
2. **Inscripción de ingreso (Etapa 1, F1–F14 intacto):** wizard 1–6 → `DATA_COMPLETE` → cargos (1 INSCRIPCION + 4 MENSUALIDAD, convenio four-eyes) → pago INSCRIPCION validado (folio) → documentos obligatorios aceptados → revisión final → **`ENROLLED` + número de control**. `inscripciones` queda **reservada** a este proceso (C2/C3).
3. **Matrícula académica (`matriculas_periodo`):** la **primera** matrícula del alumno en su periodo de ingreso se genera a partir de la inscripción que llegó a `ENROLLED`; de ahí en adelante, cada periodo posterior se cubre con una **reinscripción** (UNIQUE `alumno+periodo`, máquina propia).
4. **Carga académica:** con la matrícula activa, el alumno se inscribe a materias (`inscripciones_materias` ← `matricula_periodo_id`) con seriación, cupo y horario.
5. **Cursación:** asistencia + evaluaciones por grupo (captura del docente) → calificación final por materia (`APROBADA`/`REPROBADA`/extraordinario).
6. **Actas:** cierre four-eyes (docente → Control Escolar) con folio → calificaciones firmes.
7. **Históricos:** kardex/historial, promedio ponderado, avance y créditos cursados/aprobados/pendientes.
8. **Ajustes:** bajas (temporal/definitiva/de materia), reintentos y equivalencias/revalidaciones internas.
9. **Cierre financiero:** becas/descuentos aplicados, estado de cuenta y carta de no adeudo.
10. **Servicios:** servicio social y prácticas profesionales validados.
11. **Egreso:** verificación de requisitos del plan (créditos, servicios) → `EGRESADO`.
12. **Titulación:** modalidad interna → `TITULADO` (formatos internos; fuera SEP/MEC/MET/SIGED).
13. **Formatos en cada hito:** constancia/comprobante de inscripción (2–3), horario/lista/carga (4), boleta/acta (6), kardex/historial/avance (7), estado de cuenta/no adeudo (9), constancia de terminación (12), ficha (en cualquier punto).

## 10. Arquitectura propuesta

### 10.1 Base técnica (sin cambios) y patrón por módulo

- **Se mantiene Laravel 12 + Livewire 4 + Blade + Tailwind 4 + MySQL 8 + Pest + Vite, monolito** (`reglas` §3). El nuevo alcance **no requiere** microservicios, API separada, Redis, colas, React/Vue ni paquetes nuevos: DomPDF (P1) ya está instalado y basta para los formatos bajo demanda.
- **Patrón repetido en cada módulo nuevo** (idéntico a F2–F14): Backed Enum → migración incremental + modelo (`$fillable` explícito, casts) → **Service como único punto de negocio** (gate → transacción → `lockForUpdate` donde aplique → auditoría `HasAudit` → excepciones de dominio) → capacidad en `CAPACIDADES` + policy delegadora → Livewire con `authorize()` obligatorio y `@can` solo UX → rutas `auth` + `role` → pruebas Pest **junto al módulo** sobre `sistema_escolar_testing`.
- **Máquinas de estados:** una por entidad con el patrón `TRANSICIONES` (ya probado: U1–U3, F21); `matriculas_periodo` tendrá la suya propia (2.0-04).
- **Cálculos académicos** (avance, promedio, créditos): en **servicios** con consulta única y reutilizable (patrón `DashboardService`), nunca en Blade.
- **Navegación:** primera incorporación de menú de módulos en `layouts/app.blade.php` (previsto desde Fase 1.1).
- **Hosting compartido sin cambiar:** sin caché externa, sin jobs obligatorios, PDF bajo demanda.

### 10.2 Documentos y formatos — separación estricta (C8 aprobado)

```
DATOS Y REGLAS ACADÉMICAS  →  SERVICIO  →  PLANTILLA BLADE  →  PDF
(services de dominio,        (datos()      (resources/views/    (DomPDF,
 estados, cálculos,           normaliza     pdf/plantillas/,     bajo demanda,
 auditoría)                   el array)     solo presentación   sin persistir)
                                               + branding)
```

- **Regla:** el servicio de dominio solo expone `datos()` (array normalizado); la plantilla **no** contiene lógica de negocio ni queries; cambiar el diseño de un formato **no** toca reglas académicas y viceversa. Patrón idéntico al de `ComprobantePagoService` (Fase 9), ya probado.
- **Comunes a todos los formatos:** branding exclusivamente desde `config('branding.*')` (§23.5), logo como data URI local, contenido escapado (`{{ }}`), **sin información médica (RB-18)**, generación **bajo demanda**, descarga siempre por ruta + policy, folio interno vía `consecutivos` cuando corresponda (tipos exactos → D-N7), trazabilidad en `documentos_generados`.
- **Aprobados:** formatos estándar **internos UVZ**. **Quedan fuera:** SEP, MEC, MET, SIGED, certificados oficiales, titulación electrónica e integraciones gubernamentales.

| # | Formato interno UVZ | Fuente de datos principal |
|---|---|---|
| 1 | Constancia de estudios | `alumnos` + plan versión + créditos/avance |
| 2 | Constancia de inscripción | `matriculas_periodo` (periodo/programa/estatus) |
| 3 | Kardex | `inscripciones_materias` + `calificaciones` (histórico completo) |
| 4 | Boleta de calificaciones | `calificaciones` por periodo/materia |
| 5 | Historial académico | kardex + estatus de bajas/equivalencias |
| 6 | Acta de calificaciones | `actas` + grupo + calificaciones firmadas |
| 7 | Carga/tira de materias | plan versión vs. materias cursadas/aprobadas/perdidas |
| 8 | Horario del alumno | `inscripciones_materias` + `grupo_horarios` |
| 9 | Lista de grupo | `grupos` + alumnos cargados |
| 10 | Comprobante de inscripción | `matriculas_periodo` vigente |
| 11 | Estado de cuenta | `conceptos_pago` + `pagos` + becas/ajustes |
| 12 | Carta de no adeudo | estado de cuenta sin saldo |
| 13 | Constancia de terminación | avance + servicio social + prácticas |
| 14 | Reporte de avance académico | servicios de avance/promedio/créditos |
| 15 | Ficha del alumno | expediente (sin datos médicos) |

## 11. Riesgos de migración respecto al sistema actual

| # | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| M1 | Romper reglas críticas §10 (NC, máquina única de `inscripciones`, `UNIQUE`, RB-8) al extender | Alto | Fases pequeñas con las 288 pruebas existentes siempre verdes; **ninguna fase de la Etapa 2 modifica `InscripcionService` ni la máquina de `inscripciones`** |
| M2 | ~~Acoplar reinscripción a `inscripciones`~~ — **RESUELTO (C2/C3):** entidad nueva `matriculas_periodo` con máquina propia; `inscripciones` reservada a admisión/ingreso | Riesgo residual: implementar mal la separación en 2.0-04 | Pruebas de que el flujo de ingreso F1–F14 no cambia + pruebas de la nueva máquina; sin transiciones cruzadas |
| M3 | Riesgo **R1**: `documentos` acoplada a `inscripcion_id` (el expediente académico no cabe) | Medio | `alumno_id` nullable + ampliación de ámbitos, o tabla `expediente_documentos`; migración incremental aprobada |
| M4 | Semántica `periodos.cerrado_at` interpretada como cierre académico (R2) | Medio | Documentar que bloquea **solo inscripciones**; cierres propios por módulo (calificaciones/actas) |
| M5 | Ampliación de `users.rol` (DOCENTE/ALUMNO) rompiendo pruebas de matriz y `AdminSeeder` | Medio | Enum aditivo, actualización de `CAPACIDADES`/pruebas en la misma fase; sin spatie (D1 intacto) |
| M6 | Estado de cuenta/becas alterando pagos validados o montos snapshot | Alto | RB-15 + ajustes como **registros nuevos** (nunca modificar `pagos`/`conceptos_pago` históricos); pruebas de integridad |
| M7 | Concurrencia en captura de calificaciones y cierre de actas | Medio | `UNIQUE` por matrícula+grupo/evaluación, `lockForUpdate` en cierre de acta, auditoría de cambios |
| M8 | Rendimiento: kardex/avance/estado de cuenta sobre volúmenes grandes + auditoría creciente (R9) | Medio | Índices en columnas de filtrado, consultas en servicios, medición temprana en 2.0-09 |
| M9 | DomPDF bajo demanda para volúmenes de boletas masivas | Bajo–Medio | Generación individual bajo demanda (patrón F9); lotes requerirán aprobación de cola/worker (hoy no permitido sin aprobación) |
| M10 | Datos institucionales inexistentes (`duracion_periodos`, periodos, RVOE, planes, materias, créditos) | Medio | **C7:** no bloquea 2.0-01 — arquitectura, tablas y CRUD con **catálogos vacíos**; los datos reales se capturan cuando UVZ los proporcione; **nada inventado** (§6 de reglas) |
| M11 | Producción futura con datos reales vs. staging actual | Medio | Migraciones incrementales y reversibles cuando sea razonable; ensayar el camino completo en staging antes de cada fase |
| M12 | Portales exponiendo datos ajenos (RB-18, aislamiento) | Alto | Policies por modelo + pruebas de 403 por rol en cada portal; **rol ALUMNO con aislamiento estricto: solo su propia información** (D-N5); sin datos médicos |
| M13 | Origen de la primera matrícula (sincronización `ENROLLED` → `matriculas_periodo`) inconsistente o duplicada | Medio | UNIQUE `(alumno_id, periodo_id)` + generación transaccional e idempotente en 2.0-04; reinscripciones solo de periodos posteriores |

## 12. Roadmap 2.0 propuesto (fases pequeñas y verificables)

> Numeración propia `2.0-NN` de la **ETAPA 2 — CONTROL ESCOLAR** (no reutiliza la F1–F16 de la Etapa 1). Cada fase: pruebas junto al módulo, `composer test` + `npm run build` + `route:list` verdes, documentación de cierre; ninguna inicia sin aprobación. **Total: 18 fases (2.0-00 … 2.0-17).**

| Fase | Alcance | Guardas de verificación | Depende de | Complejidad |
|---|---|---|---|---|
| **2.0-00** | **BLINDAJE DEL NÚCLEO EXISTENTE** (Opción A aprobada, §13): ejecuta las actuales **F15** (cobertura pendiente, matriz integral del MVP, regresiones) y **F16** (hardening/RB-21, seguridad final del núcleo) **con su alcance original** | Suite 100 % verde · checklist RB-21 firmado | F14 | Baja (F15) · Media (F16) |
| **2.0-01** | Planes de estudio + **versiones** + materias + créditos + seriación/prerrequisitos (CRUD ADMIN) — **C7: no bloqueada; catálogos vacíos permitidos**, datos reales después | CRUD + pruebas de seriación + catálogo idempotente | — | Media |
| **2.0-02** | Docentes y aulas (catálogos) | CRUD + autorización + pruebas | — | Baja |
| **2.0-03** | Grupos + horarios (cupo, turno, estados de grupo, conflictos de aula/docente) | Pruebas de conflicto/cupo/estados | 2.0-01, 2.0-02 | **Alta** |
| **2.0-04** | **`matriculas_periodo`** (máquina propia; primera desde inscripción `ENROLLED`; reinscripciones) + inscripción de materias/carga (`inscripciones_materias` ← `matricula_periodo_id`) | Pruebas de seriación/cupo/horario/UNIQUE + regresión del flujo de ingreso F1–F14 | 2.0-03 (C2/C3/D-N1 **resueltas**) | **Alta** |
| **2.0-05** | **Motor de formatos (pipeline)** + formatos: constancia de inscripción, comprobante de inscripción, ficha, horario, lista de grupo | Pruebas datos/plantilla/autorización/folio | 2.0-03, 2.0-04 | Media |
| **2.0-06** | Evaluaciones + calificaciones + estados de carga (captura DOCENTE) y **rol DOCENTE** | Pruebas four-eyes, ponderación 100 %, 403 por rol | 2.0-04 (**+ decisión D-N2**) | **Alta** |
| **2.0-07** | Extraordinarios (cuando aplique) + asistencia | Pruebas de autorización y efectos | 2.0-06 (**+ decisión D-N4 diferida**) | Media |
| **2.0-08** | Actas de calificaciones (folio, cierre four-eyes docente→CE) | Pruebas de cierre, folio e integridad | 2.0-06 (**+ decisión D-N7 diferida**) | Media |
| **2.0-09** | **Históricos y avance:** kardex, historial, promedio, créditos, avance | Pruebas de cálculo + rendimiento | 2.0-08 | **Alta** |
| **2.0-10** | **Formatos académicos (II):** kardex, boleta, historial, acta, tira de materias, reporte de avance | Pruebas de contenido y autorización | 2.0-09 | Media |
| **2.0-11** | Bajas (temporal/definitiva/de materia) + equivalencias/revalidaciones internas | Pruebas de efectos en avance/carga | 2.0-09 | Media |
| **2.0-12** | Becas/descuentos + estado de cuenta + carta de no adeudo (+ recargos si se aprueban) | Pruebas four-eyes + pagos históricos intactos | 2.0-04 (**+ decisión D-N3 diferida**) | **Alta** |
| **2.0-13** | Servicio social + prácticas profesionales | Pruebas de validación/avance | 2.0-09 | Baja–Media |
| **2.0-14** | Egreso + titulación (internos) + constancia de terminación | Pruebas de requisitos de egreso | 2.0-09, 2.0-13 | Media |
| **2.0-15** | Portal de docente (sus grupos, captura) | Matriz de 403/lectura + pruebas | 2.0-06 | Media |
| **2.0-16** | Portal de alumno (lectura: kardex, horario, estado de cuenta, formatos propios) — **rol ALUMNO con aislamiento estricto** | Matriz de 403/aislamiento estricto | 2.0-09, 2.0-12 (**D-N5 aprobada**) | **Alta** |
| **2.0-17** | Reportes operativos (académicos/administrativos; exportación si se aprueba → **D-N6 diferida**) | Pruebas de datos y autorización | 2.0-09+ | Media |

## 13. Actuales F15 y F16 → **OPCIÓN A APROBADA**

- **Opción A (APROBADA):** F15 y F16 se reubican como **2.0-00 — BLINDAJE DEL NÚCLEO EXISTENTE**, conservando su alcance original:
  - **F15:** cobertura pendiente, matriz integral del MVP (F6–F9) y regresiones.
  - **F16:** hardening / RB-21 y seguridad final del núcleo existente.
- **2.0-00 debe ejecutarse ANTES de 2.0-01.**
- **NO iniciada en esta tarea:** la presente actualización es únicamente de planeación/documentación.
- Las opciones B y C ( moverlas al cierre o redefinirlas ) quedan descartadas por la aprobación de la Opción A.

## 14. Dependencias entre nuevas fases

- **2.0-01 y 2.0-02** → son raíz (sin dependencias).
- **2.0-03** ← 01 + 02 · **2.0-04** ← 03 (decisión de semántica de reinscripción **resuelta**: `matriculas_periodo`) · **2.0-05** ← 03 + 04.
- **2.0-06** ← 04 + **decisión D-N2 (rol DOCENTE, aprobada)**; **2.0-07** ← 06 (+ D-N4 diferida); **2.0-08** ← 06 (+ D-N7 diferida); **2.0-15** ← 06.
- **2.0-09** ← 08 (calificaciones firmes); **2.0-10, 2.0-11, 2.0-13** ← 09; **2.0-14** ← 09 + 13.
- **2.0-12** ← 04 (+ D-N3 diferida; independiente de calificaciones); **2.0-16** ← 09 + 12 (D-N5 aprobada); **2.0-17** ← 09 (+12).
- **2.0-00** (blindaje, F15+F16 con alcance original) precede a todo.
- Orden sugerido de ejecución: **00 → 01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 09 → 10 → 11 → 12 → 13 → 14 → 15 → 16 → 17** (05 y 12 desplazables según prioridad; 16 y 17 siempre al final).

## 15. Estimación cualitativa de complejidad por fase

| Complejidad | Fases | Criterio |
|---|---|---|
| **Baja** | 2.0-00 (F15), 2.0-02, 2.0-13 (parcial) | Catálogos o cobertura sobre lo ya construido; patrón repetido |
| **Media** | 2.0-00 (F16), 2.0-01, 2.0-05, 2.0-07, 2.0-08, 2.0-10, 2.0-11, 2.0-14, 2.0-15, 2.0-17 | CRUD + reglas puntuales; formatos sobre el pipeline probado |
| **Alta** | 2.0-03, 2.0-04, 2.0-06, 2.0-09, 2.0-12, 2.0-16 | Múltiples entidades + reglas cruzadas + concurrencia/cálculos o alto riesgo de autorización |

Sin plazos (no hay datos de capacidad): **18 fases pequeñas**, cada una con guardas verificables.

## 16. Contradicciones detectadas → **RESUELTAS (aprobación 02/10/2026)**

| ID | Contradicción original | Resolución aprobada |
|---|---|---|
| **C1** | Portal de alumno vs. D11/Supuesto 10 ("alumnos sin login", §22.11/§19) | **RESUELTA — APROBADO** el portal de alumno para la Etapa 2 en **2.0-16** con **aislamiento estricto** (solo su propia información). D11 sigue válida históricamente para F1–F14 (**D-N5**) |
| **C2 / C3 / D-N1** | Reinscripción/carga vs. máquina única (§7.3.6), `ENROLLED`/`CANCELLED` terminales (§10) y `UNIQUE(alumno_id, periodo_id)` (D2) | **RESUELTAS:** `inscripciones` **no** se reutiliza ni reinterpreta — queda **reservada al proceso de ADMISIÓN/INGRESO**. Nueva entidad **`matriculas_periodo`** (matrícula académica por periodo, UNIQUE `alumno+periodo`, **máquina de estados propia**); `inscripciones_materias` depende de **`matricula_periodo_id`**; primera matrícula desde una inscripción `ENROLLED`; siguientes = reinscripciones. Máquina de `inscripciones`, RB-8, pagos, documentos e ingreso **intactos** |
| **C4** | §19 "fuera del MVP" vs. el nuevo alcance | **RESUELTA:** aprobada la **ETAPA 2 — CONTROL ESCOLAR**; F1–F14 permanecen como **ETAPA 1 — ADMISIÓN E INSCRIPCIÓN**. `arquitectura-mvp.md` §19 y `roadmap-sistema-escolar.md` actualizados para dejarlo explícito, sin reescribir la historia del MVP |
| **C5** | `reglas` §14 (explicación larga → `docs/`) vs. "no editar docs persistentes aún" | **RESUELTA:** el análisis se persiste en `docs/analisis-evolucion-control-escolar.md` y se commitea con esta actualización |
| **C6** | F15/F16 "pendientes de aprobación" vs. "NO iniciar F15/F16" | **RESUELTA — Opción A:** se reubican como **2.0-00 (Blindaje del núcleo)** con alcance original, antes de 2.0-01; **✅ COMPLETADA** (386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK) |
| **C7** | Datos institucionales pendientes vs. planes/materias reales | **RESUELTA:** **no bloquea 2.0-01** — arquitectura, tablas y CRUD con catálogos vacíos; datos reales se capturan cuando UVZ los proporcione; **no inventar** planes, materias, créditos, RVOE, fechas, modalidades ni información no proporcionada |
| **C8** | Formatós internos vs. RB-19/§19 (solo comprobante de pago) | **RESUELTA — APROBADOS** los formatos estándar internos UVZ con pipeline estricto **DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF**; fuera: SEP, certificados oficiales, titulación electrónica, MEC, MET, SIGED e integraciones gubernamentales |

## 17. Decisiones D-N*: estado

| ID | Decisión | Estado |
|---|---|---|
| **D-N1** | Semántica de reinscripción (fila en `inscripciones` vs. entidad nueva) | **RESUELTA** → entidad `matriculas_periodo` (C2/C3); detalles de implementación en 2.0-04 |
| **D-N2** | Rol `DOCENTE` | **APROBADO** para su fase (**2.0-06**) |
| **D-N3** | Reglas detalladas de becas/descuentos | **DIFERIDA** — se define cuando llegue su fase (**2.0-12**) |
| **D-N4** | Modalidades/reglas de extraordinarios | **DIFERIDA** — se define en **2.0-07** |
| **D-N5** | Rol `ALUMNO` / portal de alumno | **APROBADO** para **2.0-16**, con aislamiento estricto (C1) |
| **D-N6** | Exportaciones CSV/Excel en reportes | **DIFERIDA** — se define en **2.0-17** |
| **D-N7** | Tipos exactos de folios de actas/constancias | **DIFERIDA** — se define en su fase (**2.0-05 / 2.0-08**) |
| — | Rol `COORDINADOR` | **NO crear por ahora** (registrado; se evalúa con la operación real) |

## 18. Estado de este análisis

- ✅ Análisis actualizado con las decisiones aprobadas (C1–C8 resueltas, D-N1/D-N2/D-N5 aprobadas, D-N3/D-N4/D-N6/D-N7 diferidas, `matriculas_periodo` incorporada al modelo, ER/flujo/riesgos/roadmap/dependencias corregidos, referencias C*/D-N* verificadas).
- ✅ Documentación relacionada actualizada: `roadmap-sistema-escolar.md` (Etapa 2), `arquitectura-mvp.md` (§7.3, §19, §24), `plan-implementacion.md` (F15/F16 → 2.0-00), `estado-proyecto.md` (Etapa 2 y estado del checkpoint).
- ❌ Sin código, sin migraciones, sin tests ejecutados (tarea documental); ❌ sin borrado de información histórica; ✅ commit local solo de documentación.
- **Estado de fases:** Etapa 1: F1–F14 **COMPLETAS** · **2.0-00 NO INICIADA** · 2.0-01…2.0-17 **NO INICIADAS**.
- **Pendiente:** aprobación del usuario sobre esta persistencia documental.
