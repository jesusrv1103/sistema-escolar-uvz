<?php

/*
|--------------------------------------------------------------------------
| Reglas financieras y académicas de la institución (configuración central)
|--------------------------------------------------------------------------
|
| UVZ opera actualmente por CUATRIMESTRES (decisión UVZ registrada en la
| arquitectura §4.9/RB-3): cada inscripción genera 1 concepto INSCRIPCION +
| exactamente 4 MENSUALIDADES (los cuatro meses del cuatrimestre), con
| vencimiento el día institucional indicado abajo.
|
| Estas son reglas de negocio, no configuración de entorno: NO viven en
| `.env` ni se repiten en servicios/vistas.
|
| "Preparado para crecer, pero implementar únicamente lo que el MVP necesita."
|
| Calificaciones: decisiones aprobadas para 2.0-06 (Escala 0-100, mínimo 60,
| 2 decimales, redondeo HALF_UP convencional sobre resultado final ponderado,
| >=60 = APROBADA). No hardcodear en servicios; consumir desde aquí.
|
| "Preparado para crecer, pero implementar únicamente lo que el MVP necesita."
|
*/

return [
    // Día institucional de vencimiento de las mensualidades (día 10).
    // Si el día no existe en un mes futuro, se usa el último día válido del mes.
    'dia_vencimiento_mensualidad' => 10,

    // Exactamente 4 mensualidades por inscripción (cuatrimestre UVZ).
    // Se derivan del mes de `periodos.fecha_inicio` (+0, +1, +2, +3 meses).
    'mensualidades_por_periodo' => 4,

    // ============================================================
    // CONFIGURACIÓN DE CALIFICACIONES (2.0-06, decisiones aprobadas)
    // ============================================================

    'calificaciones' => [
        // Escala mínima y máxima
        'min' => 0.00,
        'max' => 100.00,

        // Mínimo aprobatorio
        'aprobatoria' => 60.00,

        // Precisión en decimales
        'decimales' => 2,

        // Modo de redondeo: HALF_UP (convencional) | HALF_EVEN (bancario) | FLOOR | CEIL
        'redondeo' => 'HALF_UP',

        // Regla de aprobación: GE (>=) | GT (>)
        'regla_aprobatoria' => 'GE',
    ],
];