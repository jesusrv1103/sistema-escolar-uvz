# Diseño — Fase 2.0-17 · Reportes operativos

> **Fecha:** 2026-10-07 · **Rama:** `feat/2.0-17-reportes-operativos` (desde `master` @ `77d45cd`).
> **Autorización:** iniciar e implementar únicamente la Fase 2.0-17 (última de la ETAPA 2).
> **Fuentes con orden de prioridad:** `reglas-desarrollo.md` > `arquitectura-mvp.md` >
> `plan-implementacion.md` > `estado-proyecto.md` > `roadmap-sistema-escolar.md` + código real.
>
> Este documento **concreta lo ya autorizado** en la instrucción de fase. No propone reglas nuevas,
> no cambia reglas de negocio, no agrega dependencias, no pide migraciones destructivas ni amplía
> roles fuera de la matriz aprobada (§13: ninguno de los cuatro motivos de detención aplica).

---

## 0. Verificación de contradicciones (§13)

| Punto | Fuente | Resultado |
|---|---|---|
| «Reportes operativos» es la fase 2.0-17 de la ETAPA 2 | `roadmap` línea 57, `analisis-evolucion` §M y fila 2.0-17 | **Coincide** con la instrucción. |
| D-N6 (CSV/Excel) diferida a 2.0-17 | `analisis-evolucion` §D-N6, `arquitectura` §24 | **Coincide**: aquí se resuelve. |
| «Sin reportes/Excel» de la Etapa 1 | `arquitectura` §14 / línea 1403 | **Histórico de la Etapa 1**, marcado en docs: el alcance aprobado de la Etapa 2 incluye `M. Reportes`. No contradice. |
| Control Escolar en el reporte de Ingreso (R1) | §8.2 le da lectura de `inscripciones`, pero la matriz objetivo de 2.0-17 limita CE a «Académicos» y R1 lo deja opcional («sólo si justifica…, no ampliar por conveniencia») | **No es contradicción**: se adopta la opción más restrictiva (CE **no** entra a Ingreso), que es además la que exige el QA de cierre. |
| Control Escolar y finanzas | §8.2 le da `estado_cuenta.ver`; la instrucción prohíbe «acceso global financiero salvo capacidad vigente explícita» y `estado_cuenta.ver` **no** es un reporte global | **No es contradicción**: CE no obtiene el módulo Financieros; `estado_cuenta.ver` queda intacto en su pantalla. |
| `docs/plan-implementacion.md` §«2.0-09 en adelante NO INICIADAS» | texto histórico | Ya marcado como histórico en el saneamiento pre-2.0-17; aquí se actualiza el estado vigente. |

**No se detectó ninguna contradicción IMPORTANTE: se continúa con la implementación.**

---

## A. Inventario de fuentes de datos existentes (sólo lectura)

| Fuente | Tabla / servicio | Uso en 2.0-17 |
|---|---|---|
| `DashboardService` + `PanelOperativo` | 4 contadores | **Patrón** (consultas en servicio, no en Blade). No se reutilizan sus contadores. |
| `HistorialAcademicoService` | `inscripciones_materias` + reglas RB-A4/RB-A5/D-N4 | Regla canónica de estatus (`APROBADA/REPROBADA/BAJA/EXTRAORDINARIO`); **no** se recalcula promedio ni créditos. |
| `EstadoCuentaService` | `conceptos_pago`, `pagos`, `ajustes_financieros` | **Fórmula canónica** `saldo = cargos − pagos VALIDADO − ajustes`; se replica en SQL agregado y se **prueba la igualdad** con el servicio. |
| `ExtraordinarioService` | `inscripciones_materias.estatus` | Origen del estatus `EXTRAORDINARIO` (sólo se **reporta**). |
| `EgresoService`, `TitulacionService`, `ServicioSocialService`, `PracticaProfesionalService` | `egresos`, `titulaciones`, `servicios_sociales`, `practicas_profesionales` | Se **leen** sus entidades; no se duplican reglas de estatus ni requisitos. |
| `BajaService` | `bajas` | Lectura de movimientos (bajas + reingreso). |
| Bajas/Equivalencias/Becas | `bajas`, `equivalencias`, `becas_alumnos`, `becas` | Lectura de movimientos y de becas. |
| Matrícula/carga | `matriculas_periodo`, `inscripciones_materias`, `grupos`, `materias`, `plan_versiones` | R2/R3/R4. |
| Asistencia | `asistencias` (`inscripcion_materia_id`, `fecha`, `presente`) | R4. |
| Ingreso | `inscripciones` (+ `alumnos`, `programas`, `periodos`) | R1 (`InscripcionEstatus`). |
| Finanzas | `conceptos_pago`, `pagos`, `ajustes_financieros`, `becas_alumnos` | R6A/R6B/R6C. |
| `AuthServiceProvider::CAPACIDADES` + Policies | única fuente de permisos | Nuevas capacidades **aditivas** (§F). |
| Navegación `app-shell.blade.php` | arreglo `$nav` con `can` por ítem | Nuevo grupo `Reportes` (sólo UX). |

**No se usa** `informacion_medicas`, `contactos`, `documentos`, `observaciones`, `users.password`, `auditoria`.

---

## B. Catálogo final de reportes

Una sola pantalla `/reportes` con **categoría → reporte → filtros → resumen → tabla → CSV**.

| # | Slug | Categoría | Nombre | Capacidad |
|---|---|---|---|---|
| R1 | `ingreso` | Ingreso | Inscripciones y aspirantes | `reporte_ingreso.ver` |
| R2 | `matricula` | Académicos | Matrícula y carga académica | `reporte_academico.ver` |
| R3 | `rendimiento` | Académicos | Rendimiento académico | `reporte_academico.ver` |
| R4 | `asistencia` | Académicos | Asistencia | `reporte_academico.ver` |
| R5 | `movimientos` | Académicos | Movimientos y avance (subtipo: bajas, equivalencias, servicio social, prácticas, egreso, titulación) | `reporte_academico.ver` |
| R6A | `cartera` | Financieros | Estado de cartera | `reporte_financiero.ver` |
| R6B | `pagos` | Financieros | Pagos | `reporte_financiero.ver` |
| R6C | `becas` | Financieros | Becas y descuentos | `reporte_financiero.ver` |

- **No es BI**: sin gráficas, sin KPIs inventados, sin diseñador de reportes, sin predicciones.
- R5 se resuelve como **subreportes por `tipo`** (una tabla por tipo, columnas propias): no se
  mezclan estructuras incompatibles en una sola tabla.

---

## C. Columnas exactas por dataset

Convención de tipos de celda: `texto` (default), `fecha` (`Y-m-d`), `fecha_hora` (`Y-m-d H:i`),
`moneda` (número), `entero`, `porcentaje`. La **misma** definición alimenta tabla y CSV.

**R1 `ingreso`** — `N. de control · Alumno · Programa · Periodo · Estatus · Alta · Finalizada`
(sin CURP, sin correo, sin domicilio, sin información médica, sin documentos).

**R2 `matricula`** — `N. de control · Alumno · Programa · Periodo (matrícula) · Versión de plan · Grupo · Materia · Estatus de carga`.

**R3 `rendimiento`** — `N. de control · Alumno · Programa · Periodo (grupo) · Materia · Grupo · Docente · Estatus · Calificación final`.

**R4 `asistencia`** — `N. de control · Alumno · Grupo · Materia · Docente · Sesiones · Presentes · Ausentes · % de asistencia`.

**R5 `movimientos`** (columnas según `tipo`):

| `tipo` | Columnas |
|---|---|
| `bajas` | `Fecha · Tipo · N. de control · Alumno · Materia · Motivo · Reingreso` |
| `equivalencias` | `N. de control · Alumno · Materia · Origen · Créditos · Estatus · Dictamen · Fecha de dictamen` |
| `servicio-social` | `N. de control · Alumno · Actividad · Dependencia · Inicio · Fin · Avance · Estatus` |
| `practicas` | `N. de control · Alumno · Empresa · Asesor · Inicio · Fin · Calificación · Estatus` |
| `egreso` | `N. de control · Alumno · Versión de plan · Créditos requeridos · Créditos acumulados · Registrado` |
| `titulacion` | `N. de control · Alumno · Modalidad · Folio interno · Estatus · Registrado` |

**R6A `cartera`** — `N. de control · Alumno · Cargos · Pagos validados · Ajustes · Saldo exigible`.

**R6B `pagos`** — `Folio · N. de control · Alumno · Concepto · Monto · Método · Estatus · Fecha`.

**R6C `becas`** — `N. de control · Alumno · Beca · Periodo · Tipo · Alcance · Valor · Estatus · Aplicado en ledger`.

---

## D. Filtros

Todos con opción **«Todos»** (`''`), `resetPage()` al cambiar, IDs reales de catálogo y
**la misma lista de claves** para tabla y CSV (lista blanca: cualquier clave desconocida se ignora
y cualquier valor fuera de dominio es rechazado con validación, nunca se «salta» un filtro).

| Filtro | Clave / regla | R1 | R2 | R3 | R4 | R5 | R6A | R6B | R6C |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Periodo | `periodo` · `integer ≥ 1` | ✔ (`inscripciones.periodo_id`) | ✔ (`matricula_periodo.periodo_id`) | ✔ (`grupos.periodo_id`) | ✔ (`grupos.periodo_id`) | ✔ (`alumno.matriculas.periodo_id`) | ✔ (con cargo en el periodo) | ✔ (vía concepto→inscripción) | ✔ (`becas_alumnos.periodo_id`) |
| Programa | `programa` · `integer ≥ 1` | ✔ | ✔ | ✔ | ✔ (matrícula) | ✔ (matrícula del alumno) | ✔ (con cargo en el programa) | ✔ | – (becas no tienen programa) |
| Estatus | `estatus` · `in:` (enum del reporte) | `InscripcionEstatus` | `EstatusInscripcionMateria` | `EstatusInscripcionMateria` | – | según `tipo` | – | `EstatusPago` | `EstatusBeca` |
| Búsqueda | `busqueda` · `string ≤ 100`, sólo `nombre`, `apellidos`, `numero_control` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Plan | `plan` · `integer ≥ 1` | – | ✔ | – | – | – | – | – | – |
| Grupo | `grupo` · `integer ≥ 1` | – | ✔ | ✔ | ✔ | – | – | – | – |
| Materia | `materia` · `integer ≥ 1` | – | – | ✔ | ✔ | – | – | – | – |
| Docente | `docente` · `integer ≥ 1` | – | – | ✔ | ✔ | – | – | – | – |
| Estatus de matrícula | `estatusMatricula` · `in:` | – | ✔ | – | – | – | – | – | – |
| Subtipo de movimiento | `tipo` · `in:` (6 tipos) | – | – | – | – | ✔ | – | – | – |
| Tipo de baja | `tipoBaja` · `in:TEMPORAL,DEFINITIVA,MATERIA` | – | – | – | – | ✔ (sólo `bajas`) | – | – | – |
| Método de pago | `metodo` · `in:` `MetodoPago` | – | – | – | – | – | – | ✔ | – |
| Tipo de concepto | `concepto` · `in:` `TipoConcepto` | – | – | – | – | – | – | ✔ | – |
| Tipo de beca | `tipoBeca` · `in:` `TipoBeca` | – | – | – | – | – | – | – | ✔ |
| Rango de fechas | `desde`/`hasta` · `date` y `desde ≤ hasta` | – | – | – | ✔ (`asistencias.fecha`) | – | – | ✔ (`pagos.fecha_pago`) | – |

Semántica del filtro **Periodo** en R3/R4: es el periodo **del grupo** (donde se imparte la materia
y se registra la asistencia); en R1/R2 es el periodo de la inscripción/matrícula. El label en UI lo
indica explícitamente («Periodo (grupo)» / «Periodo (matrícula)»).

---

## E. Métricas (resumen)

Sólo conteos y sumas **directas** de los datos filtrados; nada inferido.

| Reporte | Resumen |
|---|---|
| R1 | Total; conteo por estatus de inscripción. |
| R2 | Total de inscripciones de carga; alumnos distintos; conteo por estatus de carga. |
| R3 | Inscritos; aprobados; reprobados; bajas; extraordinarios (conteos de `EstatusInscripcionMateria`). |
| R4 | Alumnos; registros de asistencia; presentes; ausentes; % general = presentes/registros. |
| R5 | Total de filas del subtipo. |
| R6A | Alumnos; total cargos; total pagos validados; total ajustes; saldo exigible. |
| R6B | Movimientos; monto total; monto validado; monto pendiente; monto rechazado. |
| R6C | Total; aprobadas; pendientes; rechazadas; importe aplicado en ledger. |

**Promedio: NO se calcula a nivel de reporte.** El único promedio aprobado es el ponderado por
créditos RB-A4 (`HistorialAcademicoService::calcularPromedio`, por alumno). Un promedio agregado de
filas **no sería** esa regla y estaría «invirtiendo» el cálculo canónico; queda fuera y se documenta.
Tampoco se crean índice de riesgo, predicciones, semáforos ni umbrales.

---

## F. Roles y capacidades (fuente única: `AuthServiceProvider::CAPACIDADES`)

```php
'reporte_ingreso.ver'    => [RolUsuario::ADMISIONES],
'reporte_academico.ver'  => [RolUsuario::CONTROL_ESCOLAR],
'reporte_financiero.ver' => [RolUsuario::ADMINISTRACION],
'reporte.exportar'       => [RolUsuario::ADMISIONES, RolUsuario::ADMINISTRACION, RolUsuario::CONTROL_ESCOLAR],
```

| Rol | Ingreso | Académicos | Financieros | CSV |
|---|:-:|:-:|:-:|:-:|
| ADMIN | ✔ | ✔ | ✔ | ✔ (los 3) |
| ADMISIONES | ✔ | ✖ | ✖ | ✔ sólo Ingreso |
| ADMINISTRACION | ✖ | ✖ | ✔ | ✔ sólo Financieros |
| CONTROL_ESCOLAR | ✖ | ✔ | ✖ | ✔ sólo Académicos |
| DOCENTE | ✖ | ✖ | ✖ | ✖ |
| ALUMNO | ✖ | ✖ | ✖ | ✖ |

- `Gate::before` de ADMIN sigue intacto; **no** se crea `COORDINADOR`.
- El CSV exige **`reporte.exportar` Y la capacidad de la categoría** (misma autorización que la pantalla).
- Backend SIEMPRE valida: `Gate::authorize` en `mount()`, en cada acción Livewire y en el controlador CSV.
  El `@can` del menú/botón es sólo UX.

---

## G. Arquitectura de consultas

```
CatalogoReportes (metadatos: categoría, capacidad, columnas, filtros y reglas)
        ↓
ReporteService (un Builder por reporte + resumen; nada de SQL en Blade)
        ↓
PanelReportes (Livewire) → vista Blade genérica  ─┐
                                                   ├→ mismas claves de filtro
CsvReporteController (streaming + fputcsv)  ←──────┘
```

- **Sin repositorios genéricos**: un servicio con métodos por reporte, como `DashboardService`.
- Eager loading en todos los builders (`with(...)`) → sin N+1 (filas 15 por página; CSV en bloques
  de 500 con `lazy()`, que reutiliza el eager loading por bloque).
- Resúmenes por `GROUP BY estatus` (1 consulta) o `count()` (máx. 2 consultas por render).
- Sin cache, sin colas, sin índices nuevos (ver §K).

---

## H. D-N6 — RESUELTA

> **D-N6 RESUELTA:** **CSV aprobado** e implementado para los reportes operativos;
> **XLSX fuera de alcance actual** en 2.0-17. Motivo: CSV cubre la exportación tabular y es
> interoperable con Excel, LibreOffice y Google Sheets **sin dependencias nuevas**.
> **No se instalan** Laravel Excel, PhpSpreadsheet ni ningún otro paquete (dependencias nuevas = 0).

---

## I. Estrategia CSV

| Punto | Decisión |
|---|---|
| Un CSV por dataset/reporte | `reporte-<slug>[-<subtipo>]-<AAAA-MM-DD>.csv` (p. ej. `reporte-movimientos-bajas-2026-10-07.csv`); sin datos sensibles en el filename. |
| Codificación | UTF-8 **con BOM** (`EF BB BF`): mejora la lectura de acentos en Excel sin afectar los tests (se decodifica con `utf-8-sig`). |
| Separador / enclosure | **Coma** `,` y comilla doble `"` — decisión única, estándar (RFC 4180). Escritura **siempre** con `fputcsv` (nunca concatenando strings). |
| Cabeceras | Etiquetas en español provenientes del **mismo** arreglo de columnas que la tabla. |
| Streaming | `response()->streamDownload()` + `fopen('php://output','w')` + `lazy(500)`: **sin archivos permanentes**, sin colecciones completas en memoria. |
| Alcance | Exporta **todas** las filas que cumplen los filtros actuales, independientemente de la página. |
| Content-Type / Disposition | `text/csv; charset=UTF-8` / `attachment`. |
| **CSV injection** | Toda celda que empiece con `=`, `+`, `-`, `@`, `TAB` o `CR` se neutraliza anteponiendo `'` (compatible con Excel/LibreOffice), **salvo** que la celda sea un número plano (`-123.45`), que no es ejecutable. |
| Campos sensibles | Ninguno: el CSV exporta exactamente las columnas del reporte (§C), nunca campos ocultos. |
| Autorización | Misma que la pantalla (`reporte.exportar` + capacidad de categoría) y **los mismos** filtros validados. |

---

## J. Privacidad / PII

- Identidad mínima: **número de control + nombre completo**. Nada más.
- **No** se exportan ni muestran: CURP, correo, teléfono, domicilio, fecha de nacimiento, sexo,
  nacionalidad, información médica, contactos, documentos, contraseñas/hashes/tokens, rutas
  privadas, snapshots (`documentos_generados.snapshot_datos`) ni campos de auditoría internos.
- **Búsqueda** también minimizada: sólo `nombre`, `apellidos` y `numero_control`
  (no reutiliza `Alumno::scopeBuscar`, que además mira CURP).
- Sin datos personales reales: la DEMO sólo contiene el fixture sintético existente.

---

## K. Rendimiento

- Índices existentes que ya cubren los filtros: `inscripciones(periodo_id,estatus)`,
  `inscripciones(programa_id)`, `inscripciones(alumno_id,periodo_id)`,
  `matriculas_periodo(periodo_id,programa_id,estatus)`, `inscripciones_materias(matricula_periodo_id,estatus)`,
  `inscripciones_materias(grupo_id,estatus)`, `grupos(periodo_id,estatus)`, `grupos(docente_id,periodo_id)`,
  `asistencias(inscripcion_materia_id,fecha)`, `becas_alumnos(alumno_id,periodo_id,estatus)`,
  `ajustes_financieros(beca_alumno_id,tipo)`, `bajas(alumno_id,fecha)`,
  `equivalencias(alumno_id,estatus)` + PK/únicos.
- **Sin índices nuevos**: las búsquedas son `LIKE '%…%'` (no aprovecharían un índice) y los filtros
  por FK ya están indexados. No se crean índices especilativos.
- Sin `N+1`: eager loading y agregados en SQL. El único subquery correlacionado por fila es el
  importe aplicado de beca (R6C) sobre un conjunto acotado.
- Sin cache/queues/workers (fuera de alcance).

---

## L. Rutas y componentes propuestos

| Elemento | Nombre / ruta |
|---|---|
| Ruta pantalla | `GET /reportes` → `reportes.index` |
| Ruta CSV | `GET /reportes/csv/{reporte}` → `reportes.csv` |
| Livewire | `App\Livewire\Reportes\PanelReportes` (`#[Layout('layouts.app')]`, `WithPagination`) |
| Vista | `resources/views/livewire/reportes/panel-reportes.blade.php` (componentes `x-ui.*`) |
| Controlador | `App\Http\Controllers\Reportes\CsvReporteController` |
| Servicios | `App\Services\Reportes\CatalogoReportes`, `App\Services\Reportes\ReporteService`, `App\Services\Reportes\CsvReporteService` |
| Navegación | Grupo `Reportes` en `app-shell.blade.php` con 3 ítems (Ingreso/Académicos/Financieros), cada uno con su capacidad; el grupo **desaparece** si el rol no tiene ningún reporte. |
| Migraciones nuevas | **0** · Dependencias nuevas: **0** |

---

## M. Migraciones previstas

**Ninguna.** No hay nuevas tablas ni columnas, y no se detecta la necesidad de índices (§K).
Las 51 migraciones existentes quedan intactas.

---

## N. Plan de tests

**`tests/Feature/ReportesTest.php`**

1. Matriz de 6 roles sobre `/reportes` (ADMIN 200 en las 3 categorías; ADMISIONES sólo Ingreso;
   ADMINISTRACION sólo Financieros; CONTROL_ESCOLAR sólo Académicos; DOCENTE y ALUMNO 403;
   invitado → login) y sobre `?categoria=` manipulado en query string.
2. Grupo `Reportes` visible en el menú para roles con capacidad; ausente para DOCENTE/ALUMNO.
3. Acción Livewire directa `seleccionarCategoria`/`seleccionarReporte` fuera de la capacidad → 403.
4. Filtros: periodo, programa, estatus, búsqueda, rango de fechas, plan/grupo/materia/docente,
   subtipo de movimientos; «Todos» limpia; `resetPage()`; valores inválidos → error (no amplía alcance).
5. Sin datos / una fila / múltiples filas / paginación (20 filas → 15 + 5).
6. Académico: `APROBADA`, `REPROBADA`, `BAJA`, `EXTRAORDINARIO` presentes y contados.
7. Finanzas: `VALIDADO` cuenta como pago; `PENDIENTE` y `RECHAZADO` **no**; cartera == `EstadoCuentaService`.
8. PII: la salida **no** contiene CURP, correo, teléfono ni domicilio del alumno de prueba.
9. Aislamiento: query params y payload manipulados no amplían el alcance.

**`tests/Feature/ReportesCsvTest.php`**

1. Misma matriz de roles en el endpoint CSV (403 sin capacidad, incluso con la URL correcta).
2. Cabeceras en español, BOM, acentos correctos, `Content-Type` y `Content-Disposition`, filename.
3. Exporta **todas** las filas filtradas (≠ página actual) y **ninguna** fuera del filtro.
4. CSV injection (`=`, `+`, `-`, `@`), comas/comillas/saltos de línea, UTF-8.
5. Campos con comillas se pueden releer con `str_getcsv` (ida y vuelta).
6. Filtros inválidos → 422; sin PII prohibida.

**Regresión (sin tocar la intención de pruebas previas):** `HistorialAcademicoTest`,
`FormatoAcademicoTest`, `FormatoCatalogoPdfTest`, `PortalDocenteTest`, `PortalAlumnoTest`,
`BecaAdminTest`, `ExtraordinarioServiceTest`, `Asistencia*Test`, `EgresoTitulacionTest`,
`AutorizacionTest`, `UiShellTest`, `UiBladeCompileTest`, `MateriasNavegacionTest`.
