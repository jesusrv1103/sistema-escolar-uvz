# Análisis y diseño — Macrobloque 2.0-13 → 2.0-16 (Cierre de portales)

> **Gate de diseño previo a migraciones** (§4 de la autorización del macrobloque).
> Fases: **2.0-13** Servicio social + prácticas profesionales · **2.0-14** Egreso + titulación internos + constancia de terminación · **2.0-15** Portal de docente · **2.0-16** Portal de alumno.
> Rama: `feat/2.0-13-16-cierre-portales` (desde `master` @ `dd57e65`). **2.0-17 NO autorizada.**
> Fuentes leídas por completo: `reglas-desarrollo.md`, `arquitectura-mvp.md`, `plan-implementacion.md`, `estado-proyecto.md`, `roadmap-sistema-escolar.md`, `analisis-evolucion-control-escolar.md`, más código real (migraciones, modelos, enums, services, policies, Livewire, rutas, vistas, tests).
> Baseline verificado antes de diseñar: **629 tests / 2971 assertions / 0 fallas · 67 rutas · build OK · view:cache OK · árbol limpio · HEAD `dd57e65`**.

## A. Alcance, orden y gates

- Orden obligatorio: **2.0-13 → 2.0-14 → 2.0-15 → 2.0-16**; gate verde por fase (pruebas dirigidas + `composer test` + `npm run build` + `route:list` + `view:cache` + docs + commit local) para continuar automáticamente.
- Sin merge, push, remoto, rebase, force, borrado de ramas, deploy, seeders, `migrate:fresh/refresh/db:wipe`, ni contacto con el demo/hosting.
- Sin COORDINADOR, notificaciones, e-mails automáticos, APIs ni paquetes nuevos. `Gate::before` de ADMIN permanece; auditoría append-only; `label()` es_MX.

## B. Reutilización (sin duplicar lógica)

| Necesidad | Reutilizar (existente, verificado) |
|---|---|
| Kardex, avance %, promedio, créditos | `HistorialAcademicoService` (métodos `obtenerHistorial`, `porcentajeAvance`, `promedioPonderado`, `creditosAprobados`, `creditosCursados`, `creditosPendientes`, `creditosTotalesPlan`) — **solo lectura** |
| Baja definitiva | `BajaService::bloqueaNuevaMatricula(Alumno): bool` (ya público) — **sin método nuevo** |
| Folios/consecutivos | `ConsecutivoService` + `TipoConsecutivo` — sólo formatos que ya lo exigen (`CONSTANCIA_INSCRIPCION`, `ACTA_CALIFICACIONES`) |
| PDF | `FormatoService::generar()` (DATOS → SERVICIO → PLANTILLA BLADE → DomPDF), `documentos_generados`, disco `private`, `DescargaDocumentoGeneradoController`, `DocumentoGeneradoPolicy` |
| Equivalencias | `equivalencias` (2.0-11) con `EstatusEquivalencia::APROBADA` cuentan para egreso |
| Auditoría | `AuditoriaService::registrar()` + trait `HasAudit` (acción por escritura) |
| CRUD Livewire | Patrón `EquivalenciaAdmin`/`BecaAdmin`/`MateriasIndex`: `#[Layout('layouts.app')]`, `WithPagination`, `Gate::authorize` en acciones, `x-ui.*`, estado vacío `.ui-empty`, filtros + `resetPage`, selección de modelos por acción (`seleccionarAlumno`) y no `wire:model` |
| Navegación | `app-shell.blade.php` → grupos con `['label','icon','route','match','can']` |
| Vínculo docente | `docentes.user_id` + `User::docente()` + `User::docenteOrCreate()` **ya existen** |
| Formato nuevo | `TipoFormato` (11 casos hoy) + fila 13 del listado (`analisis-evolucion` línea 228) |

**Hallazgos que obligan a decisiones explícitas:**

1. `HistorialAcademicoService::creditosTotalesPlan()` consulta `matriculas_periodo` con `alumno_id = auth()->user()?->id ?? $alumno->id`: **con sesión iniciada toma el id del usuario, no el del alumno** (datos incorrectos en navegador y en el portal). Es un defecto de implementación, no una regla de negocio: se corrige a `$alumno->id` con prueba que cubre "usuario ≠ alumno".
2. **No existe** `EstadoCuentaService`: la fórmula (cargos − pagos VALIDADO − ajustes `efectoSobreSaldo()`) está inline en 3 partials (`beca-estado-cuenta`, `beca-estado-cuenta-modal`, `beca-carta-no-adeudo`). Los tres obtienen idénticos totales (la carta filtra `VALIDADO` en la consulta; el estado de cuenta en memoria). Se extrae **un único** `EstadoCuentaService::paraAlumno(Alumno)` y los 3 partials pasan a usarlo, para que el portal alumno **no** duplique la regla financiera (2.0-12 aprobada intacta; `BecaAdminTest` debe seguir verde).
3. `HistorialAcademicoService::obtenerHistorialCompleto()` es un stub que **no** integra equivalencias. No se modifica: el requisito de egreso "todas las materias cubiertas" se evalúa por materia en `EgresoService` (aprobada en historial **o** `Equivalencia::APROBADA` con `materia_id`); las `APROBADA` sin `materia_id` (crédito libre) suman sus `creditos` al acumulado.

## C. Migraciones (5 nuevas, incrementales, posteriores a `2026_10_04_133911`)

| Tabla | Columnas (núcleo) |
|---|---|
| `servicios_sociales` | `id`, `alumno_id` FK **unique**, `actividad` (500), `dependencia` (500), `fecha_inicio`, `fecha_fin`, `avance` `unsignedTinyInteger` 0–100 default 0, `estatus`, `validado_por` FK nullable, `validado_at` nullable, timestamps |
| `practicas_profesionales` | `id`, `alumno_id` FK **unique**, `empresa` (500), `fecha_inicio`, `fecha_fin`, `asesor` (500) nullable, `calificacion` (100) nullable **texto**, `estatus`, `validado_por` FK nullable, `validado_at` nullable, timestamps |
| `egresos` | `id`, `alumno_id` FK **unique**, `plan_version_id` FK, `creditos_requeridos`, `creditos_acumulados`, `requisitos` json (snapshot de la verificación), `registrado_por` FK nullable, `registrado_at`, timestamps |
| `titulaciones` | `id`, `alumno_id` FK **unique**, `egreso_id` FK, `modalidad` (200) nullable **texto libre**, `folio_interno` (100) nullable **unique**, `estatus`, `registrado_por` FK nullable, `registrado_at`, timestamps |
| `alumnos.user_id` | `foreignId` nullable + **unique** → `users`, `nullOnDelete()` (el expediente sobrevive al usuario) |

Sin editar migraciones ejecutadas; sin seeders; sin `migrate:fresh/refresh/wipe`.

## D. Vínculos de identidad

- **Docente (2.0-15):** ya resuelto (`User::docente()`); el portal toma `auth()->user()->docente` y exige que exista para pertenecer a un grupo.
- **Alumno (2.0-16):** `User::alumno()` (`HasOne` vía `alumnos.user_id`). **No** se auto-crean alumnos ni usuarios. Flujo administrativo controlado en el expediente (`FormAlumno`, sólo ADMIN): vincular cuenta `ALUMNO` existente, **crear** cuenta (nombre + e-mail + contraseña capturada por el administrador, `Hash::make`, nunca hardcodeada/automática) o desvincular. Contraseñas jamás en argv, docs ni reportes.

## E. Servicios canónicos del macrobloque

- **Nuevos:** `ServicioSocialService` (`crear`, `actualizar`, `registrarAvance`, `validar`), `PracticaProfesionalService` (`crear`, `actualizar`, `validar`), `EgresoService` (`verificar`, `registrar`), `TitulacionService` (`crear`, `actualizar`), `EstadoCuentaService` (extracción sin cambio de fórmula). Todos: `Gate::authorize` server-side + `DB::transaction` + `AuditoriaService` + excepción de dominio (`app/Exceptions/*InvalidaException`), siguiendo `BajaService`.
- **Reglas conservadoras aplicadas (§5):** sin % mínimo automático ni horas obligatorias; `avance` 0–100 almacenable que **no** auto-valida; validación explícita `CONTROL_ESCOLAR`/`ADMIN`; una vez `VALIDADO` el registro no admite edición ni nuevo avance; **sin** escala de calificación de prácticas (campo de texto); **sin** four-eyes salvo fuente; sin permisos nuevos de escritura para ADMISIONES/ADMINISTRACION/DOCENTE; baja definitiva sólo vía `BajaService`; la deuda **no** es requisito de egreso.

## F. Pipeline de formatos (2.0-14)

- `TipoFormato::CONSTANCIA_TERMINACION` (fila 13: *avance + servicio social + prácticas*), `label()` es_MX.
- `requiereFolio()`/`tipoConsecutivo()` siguen el `default => false/null` → **sin folio automático nuevo** (no se inventa `TIT-YYYY-*`; si UVZ define formato, se habilita después).
- Cambios mínimos obligatorios: `FormatoService::generarNombreArchivo()` (match **sin** `default`, añadir caso), `FormatoService::prepararDatos()`, plantilla `resources/views/pdf/formatos/constancia_terminacion.blade.php` con el branding/tokens de las 9 plantillas existentes, botón en `FormatoAcademico`.
- **Guarda de veracidad:** sólo se emite si el alumno tiene `egresos` registrado (constancia de *terminación* de un alumno no egresado sería falso).

## G. Autorización (matriz §8.2, ampliación aditiva documentada)

| Fase | Capacidad nueva | Roles |
|---|---|---|
| 2.0-13 | `servicio_social.ver` / `practica.ver` | CONTROL_ESCOLAR, ADMINISTRACION |
| 2.0-13 | `servicio_social.crear|actualizar|validar`, `practica.crear|actualizar|validar` | CONTROL_ESCOLAR |
| 2.0-14 | `egreso.ver`, `titulacion.ver` | CONTROL_ESCOLAR, ADMINISTRACION |
| 2.0-14 | `egreso.crear`, `titulacion.crear|actualizar` | CONTROL_ESCOLAR |
| 2.0-15 | `portal_docente.acceder`, `docente.grupo.ver` | DOCENTE |
| 2.0-16 | `portal_alumno.acceder` | ALUMNO |
| 2.0-16 | `alumno.ver`, `inscripcion.ver`, `pago.ver`, `documento.ver` | ADMISIONES, ADMINISTRACION, CONTROL_ESCOLAR |

Las 4 últimas **no otorgan nada nuevo**: son la lectura de la fila "Lectura de alumnos / inscripciones / pagos / documentos ✔(4)" de §8.2, hoy delegada en `$user->activo` (cualquier rol autenticado). Policies nuevas: `ServicioSocialPolicy`, `PracticaProfesionalPolicy`, `EgresoPolicy`, `TitulacionPolicy`; `GrupoPolicy::verComoDocente(User, Grupo)` (¿`docente.grupo.ver` y `docente_id === user->docente->id`?); `DocumentoGeneradoPolicy` gana rama ALUMNO **sólo** con `alumno_id === user->alumno->id`. Todas delegadoras; `Gate::before` ADMIN intacto.

## H. Aislamiento de portales (2.0-15 y 2.0-16)

- Rutas **sin** `{alumno}` en el portal alumno: identidad siempre desde `auth()->user()->alumno`.
- Huecos reales detectados hoy (cualquier autenticado, incl. DOCENTE/ALUMNO) y a cerrar con gate en `mount`/policy: `HistorialAcademico` (**ningún** `Gate::authorize`), `BecaAdmin`, `BajaAdmin`, `EquivalenciaAdmin` (`mount` sin gate), `PagoPolicy::view|descargarComprobante` y `DocumentoPolicy::view` (= `activo`), `AlumnoPolicy::viewAny|view` e `InscripcionPolicy::viewAny|view` (= `activo`), `PanelOperativo`.
- Resultado esperado: `/dashboard` y todas las pantallas administrativas → 403 para DOCENTE y ALUMNO; post-login `LoginController` redirige DOCENTE → `docente.inicio` y ALUMNO → `alumno.inicio` (otros → `dashboard`, como hoy).
- Sidebar por rol: items sin `can` reciben `can` (`alumnos.lista` → `alumno.ver`, `inscripciones.lista` → `inscripcion.ver`), grupos nuevos DOCENTE/ALUMNO, menús admin invisibles para ambos (8.2).
- Captura reutilizada pero acotada: `EvaluacionAdmin`, `CalificacionAdmin`, `AsistenciaAdmin`, `ActaAdmin` (aceptan `?grupo=`) exigirán `GrupoPolicy::verComoDocente` cuando el usuario sea DOCENTE; ningún componente de captura se duplica.

## I. Requisitos de egreso realmente soportados por datos existentes

1. Versión del plan identificable (matrícula activa → `plan_version_id`).
2. Materias del plan cubiertas: `InscripcionMateria` APROBADA (histórico) **o** `Equivalencia::APROBADA` con `materia_id`.
3. `creditosAprobados (+ equivalencias) >= creditosTotalesPlan`.
4. Servicio social `VALIDADO`.
5. Prácticas `VALIDADAS`.
6. Guarda: `BajaService::bloqueaNuevaMatricula()` → sin egreso con baja vigente/definitiva.

No se evalúan requisitos administrativos/documentales (constancias, pagos, expediente completo): **no hay fuente que los defina** → pendiente institucional en §J. `registrar()` es idempotente por `UNIQUE(alumno_id)` y sólo se ejecuta con los 6 puntos cumplidos; si falta algo, la UI explica exactamente qué falta sin persistir.

## J. Datos institucionales NO inventados (bloqueantes sólo si la fase los exige)

Horas mínimas de servicio social · porcentaje mínimo académico para iniciar prácticas o servicios · documentos obligatorios de egreso · escala numérica de calificación de prácticas · modalidades oficiales de titulación (campo textual libre, nullable) · formato de folio automático de titulación (sólo captura manual con unicidad) · validez/ventana de inscripción a prácticas · transiciones de rechazo/cancelación de servicios. Ninguno es indispensable para representar las fases de forma neutral: se muestran como "pendiente institucional" en UI/docs.

## K. UI y rutas (sin COORDINADOR ni notificaciones)

| Fase | Rutas (grupo `auth`, gate en `mount`) | Sidebar |
|---|---|---|
| 2.0-13 | `/servicio-social` (`servicio-social.index`), `/practicas-profesionales` (`practicas.index`) | Académico: "Servicio social", "Prácticas profesionales" |
| 2.0-14 | `/egreso` (`egreso.index`), `/titulacion` (`titulacion.index`) | Académico: "Egreso", "Titulación" |
| 2.0-15 | `/docente` (`docente.inicio`), `/docente/grupos/{grupo}` (+ `/evaluaciones`, `/calificaciones`, `/asistencia`, `/actas` → componentes existentes) | Grupo DOCENTE |
| 2.0-16 | `/alumno`, `/alumno/kardex`, `/alumno/horario`, `/alumno/estado-cuenta`, `/alumno/documentos` | Grupo ALUMNO |

Vistas con `x-ui.*` y `label()` es_MX; estados vacíos `.ui-empty`; nombres técnicos `servicio_social*`, `practica*`, `egreso*`, `titulacion*`.

## L. Matriz de pruebas, riesgos y decisiones

- **Pruebas por fase** (Pest, BD `sistema_escolar_testing`): por rol (ADMIN 200/accionable; CONTROL_ESCOLAR según capacidad; ADMISIONES/ADMINISTRACION según §J; DOCENTE y ALUMNO 403), guarda de inmutabilidad, unicidad por alumno, verificación de egreso (cumple / falta X / baja definitiva / con equivalencias), formato #13 (plantilla, nombre de archivo, sin folio, sólo egresado), dashboard docente con grupos propios y 403 en ajeno, batería de aislamiento alumno A vs B (kardex/horario/estado/documento/matrícula, id secuencial, payload Livewire, query params), 403 a usuarios sin rol, y fórmula de estado de cuenta idéntica a 2.0-12.
- **Riesgos:** (1) endurecer policies puede afectar pruebas existentes → corregir sólo si la intención aprobada lo respeta; (2) la corrección de `creditosTotalesPlan()` cambia valores mostrados (hoy incorrectos) → prueba explícita; (3) extracción de `EstadoCuentaService` en 3 partials → `BecaAdminTest` como red; (4) `app-shell.blade.php` está abierto por el usuario → ediciones mínimas y puntuales.
- **Decisiones tomadas:** shared enums por entidad `EstatusServicioSocial`/`EstatusPracticaProfesional` (`EN_PROCESO`, `VALIDADO`; la ausencia de registro se muestra "Sin registro"), `EstatusEgreso` no aplica (la fila **es** el egreso), `EstatusTitulacion` (`EN_TRAMITE`, `TITULADO`), egreso sólo cuando todo cumple, constancia de terminación sin folio y sólo para egresados, lectura endurecida según §8.2.
