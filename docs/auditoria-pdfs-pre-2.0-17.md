# Auditoría de PDFs — pre-2.0-17 (BUG-04)

> **Alcance:** inventario y salud de los 12 formatos internos UVZ, con prioridad en **Kardex**.
> Correcciones visuales/estructurales **sin nueva funcionalidad**: no se agregan formatos,
> campos, datos personales, paquetes, ni Tailwind/Vite en el PDF.
> Fecha de auditoría: 2026-10-06 · Rama: `fix/pre-2.0-17-demo-qa`.

## 1. Método

1. Inventario de `App\Enums\TipoFormato` (12 casos) contra las plantillas de `resources/views/pdf/formatos/`.
2. Verificación estructural de cada plantilla: balance de `<div>`, `<table>` y directivas Blade.
3. Verificación de clases CSS usadas contra las que define `resources/views/pdf/layouts/pdf.blade.php`.
4. Generación efectiva de los 12 formatos y validación de `%PDF-`, tamaño > 0 y trazabilidad
   (`DocumentoGenerado`) — `tests/Feature/FormatoCatalogoPdfTest.php`.
5. Revisión de contenido del Kardex (prioridad §10/§11): información real, sin placeholders
   nuevos y **sin ampliar datos personales** (ya estaban en el DTO: no se agregó ninguno).

Restricciones respetadas: DomPDF (paquete ya instalado), branding de `config/branding.php`,
layout compartido `pdf.layouts.pdf`, sin estilos de la interfaz web ni assets nuevos.

## 2. Inventario de los 12 formatos

| Clave | Etiqueta | Plantilla | Folio | Pantallas que lo emiten | Generación cubierta por |
|---|---|---|---|---|---|
| `CONSTANCIA_INSCRIPCION` | Constancia de Inscripción | `constancia_inscripcion` | Sí (`CI-…`) | `/admin/formatos` | `FormatoServiceTest`, `DescargaDocumentoGeneradoTest` |
| `COMPROBANTE_INSCRIPCION` | Comprobante de Inscripción | `comprobante_inscripcion` | No | `/admin/formatos` | `FormatoServiceTest` |
| `FICHA` | Ficha del Alumno | `ficha` | No | `/admin/formatos` | `FormatoServiceTest` |
| `HORARIO` | Horario del Alumno | `horario` | No | `/admin/formatos` | `FormatoServiceTest`, `FormatoAcademicoTest` |
| `LISTA_GRUPO` | Lista de Grupo | `lista_grupo` | No | `/admin/formatos` | `FormatoServiceTest`, `FormatoAcademicoTest` |
| `KARDEX` | Kardex | `kardex` | No | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest`, `PortalAlumnoTest` |
| `BOLETA` | Boleta de Calificaciones | `boleta` | No | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest` |
| `HISTORIAL` | Historial Académico | `historial` | No | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest` |
| `ACTA` | Acta de Calificaciones | `acta` | Sí (`ACT-…`) | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest` |
| `TIRA` | Carga/Tira de Materias | `tira` | No | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest` |
| `AVANCE` | Reporte de Avance Académico | `avance` | No | `/admin/formatos`, `/formatos-academicos` | `FormatoAcademicoTest` |
| `CONSTANCIA_TERMINACION` | Constancia de Terminación | `constancia_terminacion` | No | `/formatos-academicos` | `EgresoTitulacionTest` |

Los 12 se generan hoy sin excepción y con cabecera `%PDF-`:
`tests/Feature/FormatoCatalogoPdfTest.php` (dataset derivado de `TipoFormato::cases()`, así que
un formato nuevo obliga a cubrirlo).

## 3. Defectos detectados

### 3.1 Estructurales (8 de 12 plantillas) — corregidos

Cada una de `acta`, `avance`, `boleta`, `historial`, `horario`, `kardex`, `lista_grupo` y `tira`
abría un `<div class="section">` alrededor de su tabla y **nunca lo cerraba**: el `</div>` de
cierre faltaba antes del `@else`/`@endif`.

```blade
@if($historial && $historial->count())
<div class="section">
    …
    </div>   ← cierra .table-wrapper
@else         ← faltaba </div> aquí: .section quedaba abierto
```

DomPDF recibe HTML malformado: los bloques siguientes (`Resumen`, `Firmas`, `@section` de pie)
quedan anidados dentro de la sección anterior y el espaciado/orden del documento se desalinea.

**Corrección:** se añadió el `</div>` faltante en las 8 plantillas. Verificado por
`PdfPlantillasTest > toda plantilla PDF equilibra <div> y <table>`.

### 3.2 Clases de estado inexistentes (8 de 12 plantillas) — corregido

Las plantillas usaban clases de Bootstrap que **el layout de PDF nunca define**:

- Filas: `table-success`, `table-danger`, `table-warning`
- Insignias: `bg-success`, `bg-danger`, `bg-warning`, `text-dark`

Resultado: en el PDF impreso **todas** las calificaciones salían con el mismo fondo (sin distinción
aprobada/reprobada/en curso) y las insignias de estatus salían sin color (sólo texto).

**Corrección:** se añadieron las reglas al layout `pdf.layouts.pdf` reutilizando la paleta UVZ ya
existente (`#72132e` institucional, verdes/rojos/ámbar de los `.badge-*`), colocadas después de
`tr:nth-child(even)` para poder sobreescribirlo. Sin CSS de la interfaz web, sin Tailwind, sin Vite.
Verificado por `PdfPlantillasTest > las clases de estado que usan las plantillas están definidas en el layout`.

### 3.3 Contrato DTO ↔ plantilla — corregido (relacionado con BUG-02/BUG-03)

| Plantilla | Defecto | Efecto visible |
|---|---|---|
| `lista_grupo` | `$grupo['horarios']->sortBy(...)` sobre un **arreglo** | HTTP 500 (nunca se generaba) |
| `lista_grupo` | `$grupo['docente']['nombreCompleto']` (el DTO entrega `nombre_completo`) | Docente siempre «Sin asignar» |
| `lista_grupo` | `{{ $grupo['aula']['nombre'] . ' (' . … . ')' ?? 'Sin asignar' }}` | Con aula nula imprimía « ()» |
| `acta` | `->esAprobada()` / `->esReprobada()` (en el modelo son `isAprobada`/`isReprobada`) | HTTP 500 |

Correcciones: el DTO de grupo entrega ahora `horarios` como colección con `dia` (enum) y `hora_*`
(Carbon), ordenada por día y hora; la plantilla usa `nombre_completo`, el aula es null-safe y el
acta usa los métodos reales del modelo.

## 4. Kardex (prioridad) — revisión de contenido

- **Datos:** sólo los que el DTO ya entregaba (`nombre_completo`, `curp`, `numero_control`,
  `correo`, `telefono`, `domicilio`, historial y resumen). **No se agregó ningún dato personal** (§11).
- **Sin placeholders nuevos:** la única marca de relleno es `numero_control ?? 'Por asignar'`,
  que describe un dato realmente ausente en la BD.
- **Historial:** una fila por inscripción (no se duplican); los estados salen coloreados gracias a §3.2.
- **Resumen:** `promedio`, `creditos_aprobados / creditos_totales` y `porcentaje_avance`, todos
  claves existentes en `HistorialAcademicoService::resumenAcademico()`.

## 5. Qué NO se tocó en ese macrobloque (histórico) y estado hoy

> **Texto histórico de la auditoría pre-2.0-17.** Tres de los cuatro puntos quedaron **resueltos**
> en el microbloque **«Saneamiento final pre-2.0-17»** (2026-10-07) y uno se mantuvo fuera de
> alcance por decisión explícita. El detalle está en `docs/estado-proyecto.md`.


| Observación | Motivo | Estado |
|---|---|---|
| `obtenerHistorial()` filtra `estatus != 'BAJA'` comparando un enum con `'BAJA'`: no-op, las bajas sí aparecen | Decisión de negocio pendiente (§18): mostrar u ocultar bajas en Kardex/Historial no es un defecto visual. Requiere acuerdo con UVZ, no se decide aquí. | **✅ RESUELTO** (comparación contra el enum + prueba RB-A5: BAJA no aporta promedio ni créditos; la tira sigue mostrándola). |
| `creditosAprobados()` contradice su docblock respecto a EXTRAORDINARIO | Depende del ENUM `inscripciones_materias.estatus`, que **no contiene** `EXTRAORDINARIO`: corregirlo exige una migración (§18/§20). | **✅ RESUELTO** (docblock reconciliado con D-N4/RB-A5 + pruebas). |
| Fuentes `DM Sans` / `Playfair Display` del layout no están embebidas | DomPDF sustituye por la fuente del sistema. Embebirlas implica agregar assets nuevos; fuera del alcance «sin paquetes ni assets nuevos». | **⏸ SE MANTIENE fuera de alcance** (cosmético; no bloquea 2.0-17). |
| `ENUM inscripciones_materias.estatus` sin `EXTRAORDINARIO` | Rompe `ExtraordinarioService` a nivel SQL. Requiere migración → fuera del alcance de este macrobloque. | **✅ RESUELTO** (migración incremental `2026_10_06_000001_…`, sin editar migraciones ejecutadas). |

## 6. Verificación

| Prueba | Qué ampara |
|---|---|
| `FormatoCatalogoPdfTest` | Los 12 formatos generan PDF válido y con trazabilidad |
| `PdfPlantillasTest` | Balance de etiquetas, directivas Blade y clases definidas |
| `FormatoAcademicoTest` | Kardex, Boleta, Historial, Acta, Tira, Avance + regresiones BUG-02 |
| `FormatoServiceTest` | Constancia, Comprobante, Ficha, Horario, Lista de Grupo |
| `DescargaDocumentoGeneradoTest` | Descarga con policy + Content-Type correcto |

No se crearon migraciones, no se agregaron dependencias y no se modificaron datos de la DEMO.

---

## 6.1 Actualización (saneamiento final pre-2.0-17, 2026-10-07)

La última fila de la tabla §5 («obtenerHistorial() filtra `estatus != 'BAJA'` comparando un
enum con 'BAJA': no-op») se **cerró**: la comparación se hizo explícita contra
`EstatusInscripcionMateria::BAJA` y la regla RB-A5 (BAJA no aporta promedio ni créditos) quedó
fijada con prueba de regresión, sin cambiar la regla aprobada. En este microbloque **sí** se
creó una migración (la del `ENUM` de §5) —ver `docs/estado-proyecto.md`—; ni dependencias ni
cambios en los datos de la DEMO.
