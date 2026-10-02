# Roadmap — Sistema Escolar UVZ

> **Universidad Villa de Zacatecas (UVZ)** · "Aumentamos la excelencia".
> Documento de evolución futura. **Nada de este roadmap está implementado ni aprobado**: solo fija la dirección.
> Principio rector: **"Preparado para crecer, pero implementar únicamente lo que el MVP necesita."**

## Etapa actual — MVP (Fases 1–16)

El MVP cubre únicamente la **primera etapa: Admisión e Inscripción**:

- Usuarios, roles y autorización.
- Catálogos (programas, periodos, tipos de documento, convenios).
- Expediente del alumno (datos, contactos, información médica restringida).
- Wizard de inscripción, convenios con aprobación, generación de cargos.
- Pagos con validación, comprobante PDF, documentos, observaciones.
- Revisión final, número de control (`YYYY####`), cancelación, panel operativo, auditoría.

Detalle: `docs/plan-implementacion.md` · Reglas: `docs/arquitectura-mvp.md`.

## Etapas futuras (evolución, SIN implementar)

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
4. Prohibido romper: `UNIQUE(alumno_id, periodo_id)`, la máquina de estados única, RB-8 y la unicidad del número de control.
