# Análisis y diseño — Fase 2.0-01: Estructura académica (Planes, Versiones, Materias, Créditos, Seriación)

> **Documento de diseño — NO implementar.** Solo preparación técnica detallada para aprobación.
> Basado en: `docs/analisis-evolucion-control-escolar.md` §4–§10, `docs/roadmap-sistema-escolar.md`, `docs/arquitectura-mvp.md` §24.
> **Decisiones aprobadas:** C7 (catálogos vacíos permitidos), D-N2 (rol DOCENTE en 2.0-06), D-N5 (rol ALUMNO en 2.0-16), C8 (formatos internos UVZ).

---

## 1. TABLAS PROPUESTAS (4 tablas nuevas)

Convenciones: PK `id` BIGINT UNSIGNED, `utf8mb4_unicode_ci`, `DECIMAL(10,2)` para montos, estados en `VARCHAR` + Backed Enums, FK con `onDelete` explícito, índices en columnas de filtrado, soft delete solo en catálogos/expediente.

| # | Tabla | Propósito |
|---|-------|-----------|
| 1 | `planes_estudio` | Catálogo de planes por programa (1 programa → N planes) |
| 2 | `plan_versiones` | Versionado de planes (PROPUESTA/VIGENTE/SUPERADA); alumno se vincula a versión vigente |
| 3 | `materias` | Materias de una versión de plan (créditos, horas, semestre/bloque, obligatoria) |
| 4 | `materia_prerrequisitos` | Autoreferencia para seriación/colocación (materia → prerrequisito) |

### 1.1 `planes_estudio`

| Campo | Tipo | Restricciones | Notas |
|-------|------|---------------|-------|
| `id` | bigint | PK | |
| `programa_id` | bigint | FK → `programas.id`, **restrict** | Un programa tiene N planes |
| `clave` | varchar(20) | **unique**, not null | Identificador interno (ej. `DER-2024`) |
| `nombre` | varchar(150) | not null | Nombre del plan |
| `resolucion_rvoe` | varchar(100) | nullable | Resolución/RVOE oficial (no inventar — C7) |
| `fecha_resolucion` | date | nullable | |
| `activo` | boolean | not null, default true | |
| `deleted_at` | timestamp | nullable | **soft delete** |
| `created_at` / `updated_at` | | | |

**Índices:** `unique (programa_id, clave)` — clave única por programa.

### 1.2 `plan_versiones`

| Campo | Tipo | Restricciones | Notas |
|-------|------|---------------|-------|
| `id` | bigint | PK | |
| `plan_id` | bigint | FK → `planes_estudio.id`, **restrict** | |
| `version` | varchar(20) | not null | Ej. `1.0`, `2024-1` |
| `fecha_vigencia` | date | not null | Desde cuándo aplica |
| `estatus` | varchar(20) | not null | `PROPUESTA \| VIGENTE \| SUPERADA` (BackedEnum) |
| `aprobado_por` | bigint | FK → `users.id`, nullable | ADMIN que aprueba |
| `aprobado_at` | timestamp | nullable | |
| `deleted_at` | timestamp | nullable | **soft delete** |
| `created_at` / `updated_at` | | | |

**Reglas:**
- **Solo una `VIGENTE` por plan** (validación en servicio + UNIQUE parcial: `plan_id` + `estatus = VIGENTE`).
- Transiciones: `PROPUESTA → VIGENTE` (aprobación ADMIN), `VIGENTE → SUPERADA` (al crear nueva VIGENTE).
- El alumno se vincula a la versión **vigente** al matricularse.

### 1.3 `materias`

| Campo | Tipo | Restricciones | Notas |
|-------|------|---------------|-------|
| `id` | bigint | PK | |
| `plan_version_id` | bigint | FK → `plan_versiones.id`, **restrict** | |
| `clave` | varchar(20) | **unique**, not null | Clave interna (ej. `MAT-101`) |
| `nombre` | varchar(150) | not null | |
| `creditos` | smallint unsigned | not null, min 1 | Créditos académicos |
| `horas_teoricas` | smallint unsigned | nullable | |
| `horas_practicas` | smallint unsigned | nullable | |
| `semestre` | smallint unsigned | nullable | 1..N dentro del plan (no semestre calendario) |
| `bloque` | varchar(30) | nullable | Alternativa a semestre (ej. `TRONCO_COMUN`, `ESPECIALIDAD`) |
| `obligatoria` | boolean | not null, default true | |
| `activo` | boolean | not null, default true | |
| `deleted_at` | timestamp | nullable | **soft delete** |
| `created_at` / `updated_at` | | | |

**Índices:** `unique (plan_version_id, clave)`.

### 1.4 `materia_prerrequisitos` (autoreferencia)

| Campo | Tipo | Restricciones | Notas |
|-------|------|---------------|-------|
| `id` | bigint | PK | |
| `materia_id` | bigint | FK → `materias.id`, **cascade** | Materia que requiere prerrequisito |
| `prerrequisito_id` | bigint | FK → `materias.id`, **restrict** | Materia prerrequisito |
| `tipo` | varchar(20) | not null | `OBLIGATORIO \| RECOMENDADO` (BackedEnum) |
| `created_at` | | | |

**Reglas de integridad (validar en servicio):**
- **No autorreferencia:** `materia_id ≠ prerrequisito_id`.
- **No ciclos A→B→A:** detectar ciclos en grafo dirigido (DFS) antes de insertar.
- **Misma versión de plan:** `materia.plan_version_id = prerrequisito.plan_version_id` (no cruzar versiones).
- **Prerrequisito obligatorio:** si `tipo = OBLIGATORIO`, la inscripción a la materia exige que el prerrequisito esté `APROBADA` (ver 2.0-04).

**Índices:** `unique (materia_id, prerrequisito_id)`.

---

## 2. RELACIONES (ER)

```
programas 1 ── n planes_estudio 1 ── n plan_versiones 1 ── n materias
materias  n ── n materias                 (materia_prerrequisitos, autoreferencia)

plan_versiones 1 ── n materias
materias 1 ── n materia_prerrequisitos (como materia_id)
materias 1 ── n materia_prerrequisitos (como prerrequisito_id)
```

**Validación cruzada con arquitectura actual (§5 ER):**
- `programas` ya existe (Fase 3) → FK `programa_id` en `planes_estudio` no rompe nada.
- `periodos` ya existe (Fase 3) → reutilizable en 2.0-03 (grupos).
- `inscripciones` (Etapa 1) queda **reservada a admisión/ingreso**; no se toca.

---

## 3. MODELOS PROPUESTOS

### 3.1 `PlanEstudio`
- `$fillable`: `programa_id`, `clave`, `nombre`, `resolucion_rvoe`, `fecha_resolucion`, `activo`
- `$casts`: `fecha_resolucion` → `date`, `activo` → `boolean`
- Relaciones: `belongsTo(Programa)`, `hasMany(PlanVersion)`, `hasMany(Materia)` (through plan_versiones)
- Scopes: `activos()`, `conVersionVigente()`

### 3.2 `PlanVersion`
- `$fillable`: `plan_id`, `version`, `fecha_vigencia`, `estatus`, `aprobado_por`, `aprobado_at`
- `$casts`: `fecha_vigencia` → `date`, `estatus` → `EstatusPlanVersion`, `aprobado_at` → `datetime`, `activo` → `boolean`
- **Estados (BackedEnum `EstatusPlanVersion`):** `PROPUESTA`, `VIGENTE`, `SUPERADA` → `label()` en español (Regla §18).
- Relaciones: `belongsTo(PlanEstudio)`, `belongsTo(User, 'aprobado_por')`, `hasMany(Materia)`, `hasOne(PlanVersion, 'vigente')` (scope)
- Scopes: `vigente()` → `where('estatus', EstatusPlanVersion::VIGENTE)`, `porPlan($planId)`

### 3.3 `Materia`
- `$fillable`: `plan_version_id`, `clave`, `nombre`, `creditos`, `horas_teoricas`, `horas_practicas`, `semestre`, `bloque`, `obligatoria`, `activo`
- `$casts`: `creditos` → `integer`, `horas_teoricas`/`horas_practicas` → `integer`, `semestre` → `integer`, `obligatoria` → `boolean`, `activo` → `boolean`
- Relaciones: `belongsTo(PlanVersion)`, `belongsToMany(Materia::class, 'materia_prerrequisitos', 'materia_id', 'prerrequisito_id')->withPivot('tipo')->withTimestamps()` (prerrequisitos), `belongsToMany(Materia::class, 'materia_prerrequisitos', 'prerrequisito_id', 'materia_id')->withPivot('tipo')` (dependientes)
- Scopes: `activas()`, `obligatorias()`, `delSemestre($n)`, `delBloque($bloque)`

### 3.4 Enums nuevos
- `EstatusPlanVersion`: `PROPUESTA` (label: "Propuesta"), `VIGENTE` ("Vigente"), `SUPERADA` ("Superada")
- `TipoPrerrequisito`: `OBLIGATORIO` ("Obligatorio"), `RECOMENDADO` ("Recomendado")

---

## 4. ESTADOS Y TRANSICIONES (`plan_versiones`)

**Máquina propia (patrón `TRANSICIONES` como `InscripcionEstatus`):**

```
PROPUESTA ───► VIGENTE       (ADMIN aprueba)
VIGENTE ──────► SUPERADA     (al crear nueva VIGENTE para el mismo plan)
```

- `PROPUESTA`: borrador, no visible para matrícula.
- `VIGENTE`: única por plan; la que usan los alumnos al matricularse.
- `SUPERADA`: histórica; no editable, no asignable.
- **Regla:** solo ADMIN (vía `Gate::before` o capability `plan_version.aprobar`) puede transicionar.
- **Validación:** al crear `VIGENTE`, la anterior (si existe) pasa a `SUPERADA` en la misma transacción.

---

## 5. AUTORIZACIÓN (Nuevas capacidades)

Agregar a `AuthServiceProvider::CAPACIDADES` (aditivo, sin romper 20 actuales):

| Capacidad | Roles permitidos (sin ADMIN) | Descripción |
|-----------|------------------------------|-------------|
| `plan_estudio.create` | ADMIN | Crear plan de estudio |
| `plan_estudio.update` | ADMIN | Editar plan (solo PROPUESTA) |
| `plan_estudio.delete` | ADMIN | Soft delete |
| `plan_version.create` | ADMIN | Crear versión (PROPUESTA) |
| `plan_version.aprobar` | ADMIN | `PROPUESTA → VIGENTE` |
| `materia.create` | ADMIN | Crear materia en versión PROPUESTA |
| `materia.update` | ADMIN | Editar materia (solo versión PROPUESTA) |
| `materia.delete` | ADMIN | Soft delete |
| `prerrequisito.gestionar` | ADMIN | Agregar/quitar prerrequisitos |

**Policies nuevas:** `PlanEstudioPolicy`, `PlanVersionPolicy`, `MateriaPolicy` (delegan a gates).
**No crear rol COORDINADOR ni DOCENTE todavía** (DOCENTE en 2.0-06, D-N2).

---

## 6. VALIDACIONES

| Entidad | Reglas server-side |
|---------|-------------------|
| `planes_estudio` | `clave` único por programa, formato `A-Z0-9_-`; `nombre` requerido; `resolucion_rvoe` nullable; `fecha_resolucion` date nullable |
| `plan_versiones` | `version` único por plan; `fecha_vigencia` date; `estatus` ∈ enum; **única VIGENTE por plan** (validación + UNIQUE parcial); `aprobado_por` solo ADMIN |
| `materias` | `clave` único por versión; `nombre` requerido; `creditos` 1–65535; `horas_teoricas/horas_practicas` ≥ 0; `semestre` ≥ 1 si presente; `bloque` ∈ lista permitida si presente; `obligatoria` boolean; **clave única por plan_version** |
| `materia_prerrequisitos` | `materia_id ≠ prerrequisito_id`; **no ciclos** (DFS); `materia.plan_version_id = prerrequisito.plan_version_id`; `tipo` ∈ enum; **no duplicados** |

---

## 7. SERIACIÓN (Diseño anti-ciclos)

**Algoritmo de detección de ciclos (DFS) antes de insertar en `materia_prerrequisitos`:**

```php
function validarSinCiclos(int $materiaId, int $nuevoPrerrequisitoId): void {
    $visitados = [];
    $pila = [$nuevoPrerrequisitoId];
    while ($pila) {
        $actual = array_pop($pila);
        if ($actual === $materiaId) {
            throw new \DomainException('Crearía un ciclo de prerrequisitos');
        }
        if (isset($visitados[$actual])) continue;
        $visitados[$actual] = true;
        $siguientes = MateriaPrerrequisito::where('materia_id', $actual)->pluck('prerrequisito_id');
        $pila = array_merge($pila, $siguientes->toArray());
    }
}
```

**Reglas adicionales:**
- Solo entre materias de la **misma `plan_version_id`**.
- `TipoPrerrequisito::OBLIGATORIO` → bloquea inscripción si no `APROBADA` (2.0-04).
- `TipoPrerrequisito::RECOMENDADO` → solo advertencia, no bloquea.

---

## 8. UI (Componentes Livewire 4 + navegación administrativa)

Navegación: agregar menú "Estructura académica" en cabecera (previsto Fase 1.1) → tarjetas en dashboard ADMIN.

| Componente | Ruta | Descripción |
|------------|------|-------------|
| `PlanEstudio` (CRUD) | `/admin/planes-estudio` | Listado, crear, editar, activar/desactivar, eliminar |
| `PlanVersion` (CRUD + aprobar) | `/admin/planes-estudio/{plan}/versiones` | Versiones por plan; botón "Aprobar" (PROPUESTA→VIGENTE) |
| `Materia` (CRUD) | `/admin/planes-estudio/{plan}/versiones/{version}/materias` | Materias de la versión; solo editable si versión = PROPUESTA |
| `Prerrequisitos` (gestión) | `/admin/materias/{materia}/prerrequisitos` | Modal para agregar/quitar prerrequisitos; validación anti-ciclos en vivo |

**Regla §18:** Todos los selects/estados usan `label()` en español; `value` técnico intacto.

---

## 9. MATRIZ DE PRUEBAS (Pest + `sistema_escolar_testing`)

| Tipo | Qué valida |
|------|------------|
| Unitarias (U) | Enum labels (§18), transiciones `PlanVersion`, detección de ciclos en prerrequisitos, UNIQUE keys |
| Feature (F) | CRUD `PlanEstudio` (crear/editar/activar/eliminar + 403 no-ADMIN), CRUD `PlanVersion` (crear PROPUESTA, aprobar→VIGENTE, auto-SUPERADA anterior), CRUD `Materia` (solo en versión PROPUESTA), Prerrequisitos (agregar/quitar, anti-ciclo, misma versión, autorreferencia), Autorización (4 roles + inactivo + guest → 403/302 según matriz), Duplicados (UNIQUE), FK (cascade/restrict) |
| Regresión | Suite F1–F14 + 2.0-00 intacta (386 tests) |

**Total estimado:** ~25–30 tests nuevos (unit + feature).

---

## 10. PLAN DE MIGRACIONES (Orden, dependencias, rollback, riesgos)

| Orden | Archivo | Dependencias | Rollback | Riesgos |
|-------|---------|--------------|----------|---------|
| 1 | `2026_10_XX_XXXXXX_create_planes_estudio_table` | `programas` (existe) | `dropIfExists` | Bajo — tabla hoja |
| 2 | `2026_10_XX_XXXXXX_create_plan_versiones_table` | `planes_estudio` (1) | `dropIfExists` | Bajo — FK restrict |
| 3 | `2026_10_XX_XXXXXX_create_materias_table` | `plan_versiones` (2) | `dropIfExists` | Bajo — FK restrict |
| 4 | `2026_10_XX_XXXXXX_create_materia_prerrequisitos_table` | `materias` (3) | `dropIfExists` | **Medio** — autoreferencia + anti-ciclos; validar datos existentes antes de FK |

**Nota:** `programas` ya existe (Fase 3). No tocar `inscripciones`, `periodos`, `users`, etc.

---

## 11. DATOS INSTITUCIONALES (C7 — No inventar)

- **No seedear** planes, materias, créditos, RVOE, fechas, modalidades.
- Tablas y CRUD listos; UVZ captura datos cuando los proporcione.
- `duracion_periodos` en `programas` ya existe (nullable) — se conserva.
- `plan_versiones.fecha_vigencia` y `resolucion_rvoe` nullable.

---

## 12. RIESGOS Y DECISIONES PENDIENTES

| ID | Riesgo / Decisión | Impacto | Mitigación / Estado |
|----|-------------------|---------|---------------------|
| R-D1 | **Anti-ciclos en prerrequisitos** — DFS en servicio; costo O(V+E) por inserción | Medio | Cache de grafo por versión; validación solo en escritura |
| R-D2 | **Única VIGENTE por plan** — UNIQUE parcial no soportado nativamente en MySQL 8.0 sin índice filtrado | Bajo | Validación en servicio + trigger/índice funcional si MySQL 8.0.13+; sino solo servicio |
| D-N3 | **Becas/descuentos** — Diferida a 2.0-12 | — | No bloquea 2.0-01 |
| D-N4 | **Extraordinarios** — Diferida a 2.0-07 | — | No bloquea 2.0-01 |
| D-N7 | **Tipos exactos de folios actas/constancias** — Diferida a 2.0-05/2.0-08 | — | `TipoConsecutivo` ampliable luego |
| D-N6 | **Exportaciones CSV/Excel** — Diferida a 2.0-17 | — | No bloquea 2.0-01 |

**Decisiones que requieren aprobación ANTES de implementar 2.0-01:**
1. ¿Confirmar `semestre` vs `bloque` como organización principal? (Hoy ambos nullable; arquitectura §4.5 sugiere `duracion_periodos` genérico).
2. ¿`TipoPrerrequisito` solo `OBLIGATORIO/RECOMENDADO` o agregar `EQUIVALENTE`?
3. ¿Validación de "única VIGENTE" vía índice filtrado MySQL 8.0.13+ o solo servicio?

---

## 13. ARCHIVO DE ANÁLISIS

Este documento se guarda en: **`docs/analisis-fase-2.0-01-estructura-academica.md`**

**Commit documental propuesto (no ejecuta migraciones ni código):**
```
docs(2.0-01): diseño detallado estructura académica — 4 tablas, enums, estados, prerrequisitos, autorización, validaciones, pruebas, migraciones, riesgos
```

---

## 14. RESUMEN DE ENTREGABLES 2.0-01 (Para aprobación)

| Entregable | Estado |
|------------|--------|
| 4 tablas definidas (columnas, tipos, FK, índices, unique, nullable, onDelete, soft delete) | ✅ Diseñado |
| Relaciones ER | ✅ Definido |
| 3 modelos + enums propuestos (fillable, casts, relaciones, scopes, label() §18) | ✅ Propuesto |
| Estados `plan_versiones` + transiciones | ✅ Definido |
| 9 capacidades nuevas + 3 policies | ✅ Propuesto |
| Validaciones server-side por entidad | ✅ Definido |
| Anti-ciclos en prerrequisitos (DFS) | ✅ Diseñado |
| 4 componentes Livewire + navegación ADMIN | ✅ Diseñado |
| Matriz de pruebas (unit + feature + regresión) | ✅ Definida |
| Plan de 4 migraciones (orden, rollback, riesgos) | ✅ Escrito |
| Datos institucionales: no inventados (C7) | ✅ Confirmado |
| Riesgos (R-D1, R-D2) + decisiones pendientes (D-N3, D-N4, D-N6, D-N7) | ✅ Reportados |

---

**2.0-01 diseñada y lista para aprobación. NO iniciada. NO migraciones ejecutadas. NO modelos creados. NO componentes escritos. NO BD modificada.**