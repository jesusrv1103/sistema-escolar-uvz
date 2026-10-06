# Roadmap — Sistema Escolar UVZ

> **Universidad Villa de Zacatecas (UVZ)** · "Aumentamos la excelencia".
> Documento de evolución futura. **La ETAPA 2 (Control Escolar) fue APROBADA en planeación (02/10/2026); 2.0-00 COMPLETADA e integrada en master; 2.0-01 COMPLETA e integrada en master; 2.0-02 COMPLETA e integrada en master; 2.0-03 COMPLETA e integrada en master; 2.0-04 COMPLETA e integrada en master; 2.0-05 COMPLETA e integrada en master; 2.0-06 COMPLETA e integrada en master; 2.0-07 COMPLETA e integrada en master; 2.0-08 COMPLETA e integrada en master; 2.0-09 COMPLETA e integrada en master; 2.0-10 COMPLETA e integrada en master; 2.0-11 COMPLETA e integrada en master; 2.0-12 COMPLETA e integrada en master; **2.0-13 ✅ COMPLETA en `feat/2.0-13-16-cierre-portales`** (macrobloque 2.0-13→2.0-16 en curso, sin merge); 2.0-14 en adelante NO INICIADAS**.
> Principio rector: **"Preparado para crecer, pero implementar únicamente lo que el MVP necesita."**

## ETAPA 1 — ADMISIÓN E INSCRIPCIÓN (MVP, Fases 1–14 ✅ COMPLETAS)

La primera etapa cubre únicamente la **Admisión e Inscripción**:

- Usuarios, roles y autorización.
- Catálogos (programas, periodos, tipos de documento, convenios).
- Expediente del alumno (datos, contactos, información médica restringida).
- Wizard de inscripción, convenios con aprobación, generación de cargos.
- Pagos con validación, comprobante PDF, documentos, observaciones.
- Revisión final, número de control (`YYYY####`), cancelación, panel operativo, auditoría.

Detalle: `docs/plan-implementacion.md` · Reglas: `docs/arquitectura-mvp.md`.

> **F15 y F16 (Opción A aprobada, C6):** su alcance original (cobertura/regresiones + hardening/RB-21) se conserva y se ejecuta reubicado como **2.0-00 — Blindaje del núcleo existente**, antes de 2.0-01. **✅ COMPLETADA (2026-10-02)** — 386 pruebas / 2326 assertions, 0 migraciones, 33 rutas, build OK.

## ETAPA 2 — CONTROL ESCOLAR (Roadmap 2.0 — APROBADO en planeación)

> Alcance aprobado: evolucionar el sistema reutilizando F1–F14 hacia control escolar completo universitario.
> **Documento de análisis completo:** `docs/analisis-evolucion-control-escolar.md` (reutilización, modelo de datos, ER, estados, roles, flujo aspirante→egreso, riesgos, decisiones).

**Reglas aprobadas de la Etapa 2:**

- `inscripciones` + máquina `InscripcionEstatus` + RB-8 quedan **reservados al proceso de ADMISIÓN/INGRESO**; no se reutilizan ni reinterpretan como flujo académico por periodo (C2/C3).
- Nueva entidad **`matriculas_periodo`** (matrícula académica del alumno en un periodo), con `UNIQUE(alumno_id, periodo_id)` y **máquina de estados propia**; la primera matrícula nace de una inscripción de ingreso `ENROLLED`; las siguientes son reinscripciones. `inscripciones_materias` depende de `matricula_periodo_id`.
- Roles: existentes (ADMIN, ADMISIONES, ADMINISTRACION, CONTROL_ESCOLAR) se conservan; **DOCENTE** aprobado (2.0-06, D-N2); **ALUMNO** aprobado (2.0-16, D-N5, aislamiento estricto: solo su propia información); **COORDINADOR** no se crea por ahora.
- Formatos: **estándar internos UVZ** con pipeline estricto **DATOS/REGLAS → SERVICIO → PLANTILLA BLADE → PDF**. Fuera de alcance: SEP, MEC, MET, SIGED, certificados oficiales, titulación electrónica e integraciones gubernamentales (C8).
- Datos institucionales: **no inventar** planes, materias, créditos, RVOE, fechas, modalidades ni información no proporcionada. **Esto no bloquea 2.0-01**: la fase crea arquitectura, tablas y CRUD con catálogos vacíos; los datos reales se capturan cuando UVZ los proporcione (C7).
- Decisiones diferidas a su fase: **D-N3 (becas/descuentos — RESUELTA en 2.0-12)**, **D-N4 (extraordinarios — RESUELTA en 2.0-07)**, D-N6 (exportaciones CSV/Excel), **D-N7 (folios actas/constancias — RESUELTA en 2.0-05/08)**.

**Fases de la Etapa 2 (18 en total) — 2.0-00 ✅ COMPLETADA e integrada en master, 2.0-01 ✅ COMPLETA e integrada en master, 2.0-02 ✅ COMPLETA e integrada en master, 2.0-03 ✅ COMPLETA e integrada en master, 2.0-04 ✅ COMPLETA e integrada en master, 2.0-05 ✅ COMPLETA e integrada en master, 2.0-06 ✅ COMPLETA e integrada en master, 2.0-07 ✅ COMPLETA e integrada en master, 2.0-08 ✅ COMPLETA e integrada en master, 2.0-09 ✅ COMPLETA e integrada en master, 2.0-10 ✅ COMPLETA e integrada en master, 2.0-11 ✅ COMPLETA e integrada en master, 2.0-12 ✅ COMPLETA e integrada en master, 2.0-13 ✅ COMPLETA en `feat/2.0-13-16-cierre-portales`, 2.0-14 en adelante ⬜ NO INICIADAS:**

| Fase | Alcance | Depende de |
|---|---|---|
| 2.0-00 | Blindaje del núcleo existente (actuales F15 + F16, alcance original) | F14 |
| 2.0-01 | Planes de estudio, versiones, materias, créditos, seriación (catálogos vacíos permitidos) | — |
| 2.0-02 | Docentes y aulas | — |
| 2.0-03 | Grupos y horarios | 2.0-01, 2.0-02 |
| 2.0-04 | `matriculas_periodo` + inscripción de materias/carga | 2.0-03 |
| 2.0-05 | Motor de formatos + primeros formatos internos | 2.0-03, 2.0-04 |
| 2.0-06 | Evaluaciones, calificaciones y rol DOCENTE (D-N2) | 2.0-04 |
| 2.0-07 | Extraordinarios (D-N4) y asistencia | 2.0-06 |
| 2.0-08 | Actas de calificaciones (folio, D-N7) | 2.0-06 |
| 2.0-09 | Kardex, historial, promedio, créditos, avance | 2.0-08 |
| 2.0-10 | Formatos académicos II (kardex, boleta, historial, acta, tira, avance) | 2.0-09 |
| 2.0-11 | Bajas y equivalencias/revalidaciones | 2.0-09 |
| 2.0-12 | Becas/descuentos (D-N3), estado de cuenta, carta de no adeudo | 2.0-04 |
| 2.0-13 | Servicio social y prácticas profesionales | 2.0-09 |
| 2.0-14 | Egreso y titulación internos | 2.0-09, 2.0-13 |
| 2.0-15 | Portal de docente | 2.0-06 |
| 2.0-16 | Portal de alumno (rol ALUMNO, aislamiento estricto) | 2.0-09, 2.0-12 |
| 2.0-17 | Reportes operativos (D-N6 en su caso) | 2.0-09+ |

Detalle de guardas, riesgos y complejidad: `docs/analisis-evolucion-control-escolar.md` §11–§15.

## Etapas futuras (referencia histórica — listado original, incorporado a la Etapa 2)

### Académico

- Reinscripciones
- Planes de estudio
- Materias
- Docentes
- Grupos
- Horarios
- Carga académica
- Inscripción a materias
- Calificaciones
- Asistencia
- Kardex / historial académico
- Bajas y cambios de estatus

### Financiero

- Becas
- Adeudos y estados de cuenta
- Recargos y descuentos

### Reportes y comunicación

- Reportes académicos
- Reportes administrativos
- Avisos y notificaciones
- Constancias y documentos académicos

### Portales

- Portal del alumno
- Portal del docente

> Cada bloque se convertirá en fases nuevas con sus propias pruebas, previa aprobación, sin alterar el alcance del MVP.
>
> **Nota (Etapa 2):** este listado original se incorporó a la ETAPA 2 aprobada (sección superior). Se conserva como referencia histórica; la Etapa 2 añade además versiones de planes, créditos, seriación, extraordinarios, actas, equivalencias, becas de diseño, servicio social, prácticas, egreso, titulación y el motor de formatos internos.

## Revisión de extensibilidad (sobre la arquitectura actual)

Confirmaciones sobre `docs/arquitectura-mvp.md` §4–§5:

| Afirmación | Estado | Evidencia |
|---|---|---|
| `alumnos` es una entidad permanente | ✔ | Tabla independiente con `CURP` unique y soft delete; sobrevive a cualquier inscripción |
| `numero_control` pertenece al alumno | ✔ | Columna en `alumnos` (`char(8)`, unique, nullable), asignada una sola vez (§4.2) |
| `Inscripcion` = alumno + periodo + programa | ✔ | `inscripciones(alumno_id, periodo_id, programa_id)` con `UNIQUE(alumno_id, periodo_id)` (§4.8) |
| `programas` podrá relacionarse con planes de estudio | ✔ | Tabla catálogo simple; futura tabla `planes_estudio(programa_id)` no requiere cambios en el MVP |
| `programas` no se acopla a una organización semestral | ✔ | `duracion_periodos` (`smallint unsigned`, **nullable**) mide duración en periodos académicos; `tipo_periodo`, planes, RVOE, modalidad y campus quedan fuera del MVP y se agregarán en sus fases (ajuste Fase 3) |
| Las `claves` de catálogo no se confunden con datos oficiales | ✔ | `clave` documentada como identificador **interno** del sistema (semilla `DER`/`SAI`/`CF`); RVOE y duraciones no se inventan: se capturan cuando exista fuente institucional |
| `periodos` se reutiliza en grupos, cargas y calificaciones | ✔ | Catálogo genérico (`clave`, fechas, `activo`); `cerrado_at` está descrito como bloqueo de **inscripciones** |
| Finanzas desacoplada del dominio académico | ✔ | `conceptos_pago`/`pagos` solo cuelgan de `inscripciones`; el módulo académico futuro no los toca |
| `auditoria` es transversal | ✔ | Morph `auditable_type/id` + `accion`/`datos` JSON, usable por cualquier modelo futuro (§4.14) |
| `documentos` puede crecer a expediente académico | ⚠ | Hoy está ligada a la inscripción (ver riesgo R1) |
| Ninguna tabla actual impide agregar materias, grupos o calificaciones | ✔ | Serían tablas nuevas con FK a `programas`, `periodos`, `inscripciones`; sin cambios en el esquema actual |

### Riesgos detectados (documentados, NO modificados)

| ID | Riesgo | Impacto | Ajuste mínimo recomendado (cuando se autorice) |
|---|---|---|---|
| **R1** | `documentos` referencia `inscripcion_id` con `UNIQUE(inscripcion_id, tipo_documento_id)`: documentos propios del **alumno** (expediente académico, kardex, constancias) no caben bien en ese ámbito | Bajo–medio | Agregar `alumno_id` **nullable** y ampliar el ámbito de `tipos_documento`, o crear `expediente_documentos` con FK a `alumnos`; conservando el MVP intacto |
| **R2** | `periodos.cerrado_at` podría interpretarse en módulos futuros como cierre general y bloquear la captura de calificaciones | Bajo | Dejar explícito que `cerrado_at` bloquea **solo inscripciones**; cada módulo futuro definirá su propio criterio de cierre. *Semántica ya documentada en Fase 3 (§4.6, modelo `Periodo`, migración y vista del catálogo)* |
| **R3** | `users.rol` son 4 roles fijos del MVP; etapas futuras requerirán roles nuevos (DOCENTE, COORDINADOR, ALUMNO) | Bajo | Extender el enum/validación de rol y el `AdminSeeder`; el esquema (`varchar`) no cambia |
| **R4** | Los portales (alumno/docente) exigirán cuentas de usuario con otro perfil que `users` actual | Bajo | Evaluar dominio/ámbito de acceso por rol; sin cambios hasta autorizar la etapa |

Ningún riesgo es bloqueante; **no se hizo ningún cambio automático**.

## Reglas de evolución

1. Todo el roadmap se implementa **por fases aprobadas**, con pruebas, sin saltarse el orden.
2. La identidad institucional sigue centralizada en `config/branding.php`.
3. La trazabilidad (`auditoria`) y las políticas de autorización se extienden a cada módulo nuevo.
4. Prohibido romper: `UNIQUE(alumno_id, periodo_id)`, la máquina de estados única de `inscripciones` (la Etapa 2 usa su propia máquina en `matriculas_periodo`, sin transiciones cruzadas), RB-8 y la unicidad del número de control.
5. **Etapa 2 (aprobada 02/10/2026):** sus fases (2.0-00 … 2.0-17) también se implementan por fases aprobadas, empezando por 2.0-00 (blindaje del núcleo) antes que cualquier otra.
