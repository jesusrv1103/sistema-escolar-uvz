# Reglas de desarrollo — Sistema Escolar UVZ

> Documento **obligatorio** para todas las fases futuras del Sistema Escolar UVZ.
> Creado al cierre de la Fase 3, antes de iniciar la Fase 4.

---

## 1. Fuentes de verdad

Antes de trabajar en cualquier fase, leer:

- `docs/reglas-desarrollo.md`
- `docs/arquitectura-mvp.md`
- `docs/plan-implementacion.md`
- `docs/estado-proyecto.md`

Cuando aplique:

- `docs/roadmap-sistema-escolar.md`

Orden de prioridad ante contradicciones:

1. `reglas-desarrollo.md`
2. `arquitectura-mvp.md`
3. `plan-implementacion.md`
4. `estado-proyecto.md`
5. `roadmap-sistema-escolar.md`

Si encuentras una contradicción importante: **DETENERTE y reportarla.** No decidir unilateralmente.

## 2. Alcance

- Trabajar únicamente en la fase explícitamente autorizada.
- **NO iniciar la siguiente fase** aunque la actual termine correctamente.
- No implementar funcionalidades del roadmap futuro sin aprobación.

### 2.1 Excepción de MACROBLOQUE (autorización explícita del usuario)

La regla anterior sigue siendo **la norma por defecto**: una fase requiere autorización y la siguiente **no** inicia automáticamente.

Cuando el usuario autorice **explícitamente un MACROBLOQUE** compuesto por varias fases concretamente enumeradas, todas esas fases se consideran **parte del mismo alcance autorizado**:

- Deben conservarse los **gates internos por fase** (pruebas dirigidas + `composer test` + `npm run build` + `route:list` + `view:cache` + documentación de cierre + commit local).
- Si el gate de una fase queda **verde**, puede continuarse **automáticamente** a la siguiente fase **del mismo macrobloque**, sin pedir permiso nuevamente.
- Debe **detenerse** únicamente ante un bloqueo real: contradicción de arquitectura, seguridad, regla de negocio no definida indispensable, migración destructiva, pérdida de datos, dependencia importante no prevista, cambio de alcance o prueba cuya intención aprobada deba modificarse.
- **Nunca** continuar más allá de la última fase explícitamente autorizada.
- Ninguna otra regla de este documento se debilita por esta excepción.

> **Macrobloque vigente:** **2.0-13 → 2.0-16** (Servicio social y prácticas · Egreso y titulación internos · Portal de docente · Portal de alumno), autorizado explícitamente por el usuario y ejecutado en la rama `feat/2.0-13-16-cierre-portales`. **2.0-17 NO está autorizada.** Registro del alcance en `docs/plan-implementacion.md` y `docs/estado-proyecto.md`; auditoría de diseño en `docs/analisis-macrobloque-2.0-13-16.md`.

Principio:

> "Preparado para crecer, pero implementar únicamente lo que el MVP necesita."

No agregar por iniciativa propia:

- microservicios;
- APIs separadas;
- CQRS;
- repositories innecesarios;
- DDD complejo;
- paquetes sin necesidad;
- React/Vue;
- Redis;
- WebSockets;
- Docker como requisito de producción.

## 3. Stack aprobado

Mantener: **Laravel 12**, **PHP 8.2+**, **Livewire 4**, **Blade**, **Tailwind CSS 4**, **MySQL 8**, **Pest**, **Vite**.

Monolito Laravel. Cualquier cambio importante de stack requiere aprobación previa.

## 4. Seguridad

Nunca:

- hardcodear passwords, tokens o API keys;
- guardar secretos en `docs/`;
- guardar secretos en Git;
- mostrar hashes de passwords en reportes;
- colocar documentos privados en `public/`.

Los secretos deben vivir en `.env`:

- `.env` nunca se versiona (está en `.gitignore`).
- `.env.example` únicamente contiene nombres de variables y valores seguros/vacíos.

Información médica debe respetar siempre:

| Rol | Acceso |
|---|---|
| ADMIN | acceso |
| ADMISIONES | acceso |
| ADMINISTRACION | **sin acceso** |
| CONTROL_ESCOLAR | solo lectura cuando el flujo lo requiera |

- Toda autorización crítica debe validarse **server-side**.
- Ocultar botones con `@can` **NO** reemplaza Policies/Gates.

## 5. Datos reales

Durante desarrollo y pruebas: **NO** utilizar información personal real de alumnos; usar datos ficticios/sintéticos.

Especialmente evitar enviar a modelos hospedados: CURP reales, teléfonos reales, domicilios reales, documentos, información médica, datos bancarios, credenciales.

## 6. Base de datos

| Entorno | Base |
|---|---|
| Desarrollo | `sistema_escolar` |
| Testing | `sistema_escolar_testing` |

- **Nunca** ejecutar pruebas contra desarrollo o producción; las pruebas usan exclusivamente la BD de testing.
- Migraciones: incrementales; reversibles cuando sea razonable; respetar FK e índices; **no editar migraciones ya ejecutadas** para simular cambios nuevos.
- **No inventar datos institucionales:** si UVZ no ha confirmado un valor, usar `nullable` o dejarlo pendiente según corresponda.

## 7. Git

- Git local autorizado; si no existe repositorio: `git init`, verificar `.gitignore` y confirmar que `.env` está ignorado.
- Puede crear commits locales **sin autorización individual**.
- Rama por fase: `feat/fase-N-descripcion`.

NO:

- push;
- crear remotos;
- force push;
- rebase destructivo;
- reescribir historial aprobado;
- borrar ramas ni commits;
- mergear automáticamente a main.

Preferir commits pequeños y coherentes, p. ej.:

```
feat(alumnos): create student domain
feat(alumnos): add Livewire forms
test(alumnos): cover student validation
docs: update phase 4 status
```

Antes de cada commit: `git diff`, revisar secretos, revisar archivos staged y confirmar que el commit contiene cambios relacionados. No commitear archivos temporales, caches ni artefactos innecesarios.

## 8. Cierre de fase

Una fase **no** está completa solo porque "funciona". Antes de marcarla completa:

1. `composer test` (todas las pruebas verdes, incluidas las anteriores);
2. `npm run build`;
3. `php artisan route:list` cuando sea relevante.

- No resolver una prueba rompiendo otra regla de negocio.
- Al terminar correctamente: **commit local de cierre de fase**.
- **No iniciar la siguiente fase.**

## 9. Pruebas

- Se escriben **junto con cada módulo**; no dejar toda la cobertura para el final.
- Incluir cuando corresponda: caso feliz, validaciones, autorización, duplicados, estados inválidos, restricciones de BD, regresiones y reglas de negocio críticas.
- No borrar pruebas para que la suite quede verde. Si una prueba existente parece incorrecta: **reportarla** antes de eliminarla o cambiar su intención.

## 10. Reglas de negocio críticas (no alterar sin aprobación)

- Un alumno mantiene su `numero_control`; `numero_control` pertenece al alumno.
- `UNIQUE(alumno_id, periodo_id)`.
- Inscripción y mensualidades son conceptos distintos.
- Las mensualidades pendientes **NO** bloquean `ENROLLED`.
- Únicamente el pago de `INSCRIPCION` controla el flujo de pago de inscripción.
- Un convenio requiere aprobación antes de aplicar descuento definitivo.
- Los pagos validados no se eliminan.
- La auditoría es **append-only**.
- Los estados de inscripción solo cambian mediante la máquina de estados.
- `ENROLLED` y `CANCELLED` son estados terminales según la arquitectura actual.
- **No usar `MAX(numero_control)`** para generar consecutivos (tabla `consecutivos` con `lockForUpdate()`).

## 11. Arquitectura futura

El producto final será un Sistema Escolar UVZ. El MVP actual **NO** debe impedir posteriormente: planes de estudio, materias, docentes, grupos, horarios, calificaciones, asistencia, kardex, reinscripciones, portales, finanzas escolares ampliadas.

Pero **NO** crear esas tablas o módulos anticipadamente.

## 12. Branding

- Fuente de verdad de la identidad institucional: **`config/branding.php`**.
- No repetir nombre, logo, teléfono, dirección o colores directamente en múltiples vistas: usar los tokens de branding existentes.
- No inventar colores institucionales.

## 13. Documentación

- Al cerrar cada fase actualizar `docs/estado-proyecto.md` y `docs/plan-implementacion.md`.
- Actualizar la arquitectura **únicamente** cuando exista una decisión arquitectónica real.
- Actualizar el roadmap **únicamente** cuando cambie la dirección futura.
- No inflar documentación repitiendo información innecesariamente.

## 14. Respuestas en terminal

- No imprimir archivos completos.
- Si una explicación supera ~50 líneas: guardarla en `docs/` y mostrar solo el resumen.
- Al terminar una tarea reportar: archivos modificados, migraciones, pruebas, build, problemas, commits, rama, `git status` y estado de la fase.
- Preferir respuestas de máximo 20 líneas salvo que se pida detalle.

## 15. Cambios no previstos

Si durante una fase detectas: error de arquitectura, riesgo de seguridad, contradicción, cambio de alcance, dependencia nueva importante, migración destructiva o necesidad de modificar una regla aprobada → **DETENER ese cambio**.

Explicar: (1) problema, (2) impacto, (3) solución mínima recomendada. Esperar aprobación.

## 16. Estado del contexto

- No depender de la memoria de la conversación: los archivos del repositorio son la fuente persistente.
- Si se inicia una sesión nueva o se compacta el contexto: leer primero los documentos de fuente de verdad y revisar el repositorio antes de modificar código.

## 17. Desarrollo responsable

- No afirmar que algo funciona sin haberlo validado.
- No marcar una fase COMPLETA con pruebas fallando.
- No ocultar errores encontrados.
- No modificar código fuera de alcance solo para "limpiar" o refactorizar; los refactors no relacionados se posponen salvo que sean estrictamente necesarios para implementar la fase de forma segura.

## 18. Interfaz en español (regla permanente)

- **Prohibido mostrar `Enum::value` (u otro valor técnico) directamente al usuario.** Toda representación visible —selects, filtros, tablas, badges, flash messages, mensajes de servicio, PDFs y el historial de auditoría— se muestra mediante `Enum::label()` (etiquetas es_MX).
- Los valores técnicos se conservan **sin cambios** en: base de datos, atributo `value=` de `<option>`, `wire:model`, queries/filtros de la URL, payloads de auditoría, transiciones, logs y reglas de negocio. Traducir es únicamente una capa de presentación.
- Aplica al Roadmap 2.0 y a **cualquier enum futuro**: los enums nuevos incluyen `public function label(): string` desde su creación. `Sexo::etiquetas()` y `TipoSangre::etiquetas()` derivan de `label()` (fuente única de etiquetas).
- Si una excepción de dominio también se muestra al usuario: mensaje humano en español para la UI y, cuando aporte, contexto técnico exacto solo en diagnóstico interno (nunca visible).
- Los mensajes de validación nativos de Laravel se definen en `lang/es_MX/validation.php`; sin ese archivo las claves crudas (p. ej. `validation.required`) se renderizaban en pantalla.
