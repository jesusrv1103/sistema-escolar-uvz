<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Documentos\DescargaArchivosController;
use App\Http\Controllers\Documentos\DescargaDocumentoGeneradoController;
use App\Http\Controllers\Pagos\ComprobantePagoController;
use App\Http\Controllers\Pagos\ComprobantePdfController;
use App\Livewire\Academico\CrearEvaluacion;
use App\Livewire\Academico\EditarEvaluacion;
use App\Livewire\Academico\CalificacionAdmin;
use App\Livewire\Academico\EvaluacionAdmin;
use App\Livewire\Academico\GenerarFormato;
use App\Livewire\Academico\GrupoAdmin;
use App\Livewire\Academico\GrupoHorarioAdmin;
use App\Livewire\Academico\MateriaAdmin;
use App\Livewire\Academico\MateriasIndex;
use App\Livewire\Academico\PlanEstudioAdmin;
use App\Livewire\Academico\PlanVersionAdmin;
use App\Livewire\Academico\AsistenciaAdmin;
use App\Livewire\Academico\ActaAdmin;
use App\Livewire\Academico\HistorialAcademico;
use App\Livewire\Academico\FormatoAcademico;
use App\Livewire\Academico\BajaAdmin;
use App\Livewire\Academico\EquivalenciaAdmin;
use App\Livewire\Academico\BecaAdmin;
use App\Livewire\Alumnos\FormAlumno;
use App\Livewire\Alumnos\FormContactos;
use App\Livewire\Alumnos\FormInfoMedica;
use App\Livewire\Alumnos\ListaAlumnos;
use App\Livewire\Catalogos\Aulas;
use App\Livewire\Catalogos\Convenios;
use App\Livewire\Catalogos\Docentes;
use App\Livewire\Catalogos\Periodos;
use App\Livewire\Catalogos\Programas;
use App\Livewire\Catalogos\TiposDocumento;
use App\Livewire\Documentos\PanelDocumentos;
use App\Livewire\Inscripciones\ListaInscripciones;
use App\Livewire\Inscripciones\PanelEstados;
use App\Livewire\Inscripciones\WizardInscripcion;
use App\Livewire\Observaciones\ListaObservaciones;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// Rutas de alumnos / expediente (Fase 4) — solo usuarios autenticados;
// la autorización fina (policy/gate) vive en cada componente Livewire.
Route::middleware('auth')->prefix('alumnos')->name('alumnos.')->group(function () {
    Route::get('/', ListaAlumnos::class)->name('lista');
    Route::get('/nuevo', FormAlumno::class)->name('nuevo');
    Route::get('/{alumno}', FormAlumno::class)->name('ver');
    Route::get('/{alumno}/contactos', FormContactos::class)->name('contactos');
    Route::get('/{alumno}/info-medica', FormInfoMedica::class)->name('info-medica');
});

// Inscripciones (Fase 6): listado con filtros + wizard hasta DATA_COMPLETE.
// La autorización fina (InscripcionPolicy / gates) vive en cada componente.
Route::middleware('auth')->prefix('inscripciones')->name('inscripciones.')->group(function () {
    Route::get('/', ListaInscripciones::class)->name('lista');
    Route::get('/nueva', WizardInscripcion::class)->name('nueva');   // antes de {inscripcion}
    Route::get('/{inscripcion}', WizardInscripcion::class)->name('ver');
    // Documentos del expediente (Fase 10): recepción, reemplazo y validación.
    Route::get('/{inscripcion}/documentos', PanelDocumentos::class)->name('documentos');
    // Observaciones / faltantes (Fase 11): APPEND-ONLY — crear, listar y
    // resolver (§4.13); sin edición ni borrado.
    Route::get('/{inscripcion}/observaciones', ListaObservaciones::class)->name('observaciones');
    // Revisión final (Fase 12): guardas RB-8 + número de control → ENROLLED.
    Route::get('/{inscripcion}/revision-final', PanelEstados::class)->name('revision-final');
});

// Descarga del archivo de un documento (Fase 10): disco PRIVADO, policy y
// ruta resuelta desde BD. NO confundir con las rutas de pagos (Fases 8/9).
Route::middleware('auth')->prefix('documentos')->name('documentos.')->group(function () {
    Route::get('/{documento}/archivo', DescargaArchivosController::class)->name('archivo');
});

// Descarga de documentos generados (formatos académicos 2.0-05): disco PRIVADO, policy,
// ruta resuelta desde BD. Semántica distinta a los documentos del expediente.
Route::middleware('auth')->prefix('documentos-generados')->name('documentos.generados.')->group(function () {
    Route::get('/{documento}', DescargaDocumentoGeneradoController::class)->name('descargar');
});

// Comprobante de pago cargado (Fase 8): archivo PRIVADO servido por un
// controlador mínimo con policy; la ruta interna se resuelve desde BD
// (nunca desde el cliente) y se impide path traversal. NO es el PDF
// institucional (Fase 9).
Route::middleware('auth')->prefix('pagos')->name('pagos.')->group(function () {
    Route::get('/{pago}/comprobante', [ComprobantePagoController::class, 'show'])->name('comprobante');
    // Comprobante institucional PDF (Fase 9): se genera BAJO DEMANDA para
    // pagos VALIDADO, sin persistir copia; semántica propia y separada de la
    // evidencia cargada anterior.
    Route::get('/{pago}/comprobante-pdf', ComprobantePdfController::class)->name('comprobante-pdf');
});

// Historiales y Avance (2.0-09) — acceso académico.
Route::middleware(['auth'])->prefix('historial')->name('historial.')->group(function () {
    Route::get('/', HistorialAcademico::class)->name('index');
    Route::get('/{alumno}', HistorialAcademico::class)->name('ver');
});

// Formatos académicos II (2.0-10)
Route::middleware(['auth'])->prefix('formatos-academicos')->name('formatos-academicos.')->group(function () {
    Route::get('/', FormatoAcademico::class)->name('index');
    Route::get('/kardex/{alumno}', FormatoAcademico::class)->name('kardex');
    Route::get('/boleta/{alumno}', FormatoAcademico::class)->name('boleta');
    Route::get('/historial/{alumno}', FormatoAcademico::class)->name('historial');
    Route::get('/acta/{acta}', FormatoAcademico::class)->name('acta');
    Route::get('/tira/{alumno}', FormatoAcademico::class)->name('tira');
    Route::get('/avance/{alumno}', FormatoAcademico::class)->name('avance');
});

// Bajas y Equivalencias (2.0-11)
Route::middleware(['auth'])->prefix('bajas-equivalencias')->name('bajas-equivalencias.')->group(function () {
    Route::get('/', BajaAdmin::class)->name('index');
    Route::get('/bajas', BajaAdmin::class)->name('bajas');
    Route::get('/equivalencias', EquivalenciaAdmin::class)->name('equivalencias');
});

// Becas y Descuentos (2.0-12, D-N3)
Route::middleware(['auth'])->prefix('becas-descuentos')->name('becas-descuentos.')->group(function () {
    Route::get('/', BecaAdmin::class)->name('index');
    Route::get('/becas', BecaAdmin::class)->name('becas');
    Route::get('/estado-cuenta', BecaAdmin::class)->name('estado-cuenta');
    Route::get('/carta-no-adeudo', BecaAdmin::class)->name('carta-no-adeudo');
});

// Catálogos administrables (Fase 3) — solo ADMIN.
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/programas', Programas::class)->name('programas');
    Route::get('/periodos', Periodos::class)->name('periodos');
    Route::get('/tipos-documento', TiposDocumento::class)->name('tipos-documento');
    Route::get('/convenios', Convenios::class)->name('convenios');

    // Recursos académicos (2.0-02) — solo ADMIN.
    Route::get('/docentes', Docentes::class)->name('docentes');
    Route::get('/aulas', Aulas::class)->name('aulas');

    // Grupos y horarios (2.0-03) — solo ADMIN.
    Route::get('/grupos', GrupoAdmin::class)->name('grupos');
    Route::get('/grupos/{grupo}/horarios', GrupoHorarioAdmin::class)->name('grupos.horarios');

    // Formatos académicos (2.0-05) — solo ADMIN.
    Route::get('/formatos', GenerarFormato::class)->name('formatos');
    Route::get('/evaluaciones', EvaluacionAdmin::class)->name('evaluaciones');
    Route::get('/calificaciones', CalificacionAdmin::class)->name('calificaciones');
    Route::get('/asistencia', AsistenciaAdmin::class)->name('asistencia');
    Route::get('/actas', ActaAdmin::class)->name('actas');

    // Estructura académica (2.0-01) — solo ADMIN.
    Route::get('/planes-estudio', PlanEstudioAdmin::class)->name('planes-estudio');
    Route::get('/planes-estudio/{plan}/versiones', PlanVersionAdmin::class)->name('versiones');
    Route::get('/planes-estudio/{plan}/versiones/{version}/materias', MateriaAdmin::class)->name('materias');

    // Entrada global a Materias (navegabilidad 2.0-12): sólo consulta y
    // enlaces; el CRUD sigue en la ruta anterior con sus políticas intactas.
    Route::get('/materias', MateriasIndex::class)->name('materias.index');
});