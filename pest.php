<?php

use Pest\Pest;

uses()->in('tests/Unit', 'tests/Feature')->group('unit', 'feature');

/*
|--------------------------------------------------------------------------
| Parallel Testing
|--------------------------------------------------------------------------
|
| By default, Pest runs tests in parallel. This can cause issues with
| database transactions and test isolation. Disable parallel testing
| for better test isolation.
|
*/
pest()->parallel(false);

/*
|--------------------------------------------------------------------------
| Global Test Isolation
|--------------------------------------------------------------------------
|
| Reset factory static counters before each test to ensure deterministic
| test execution regardless of order.
|
*/
beforeEach(function () {
    foreach ([
        \Database\Factories\ProgramaFactory::class,
        \Database\Factories\PlanEstudioFactory::class,
        \Database\Factories\PlanVersionFactory::class,
        \Database\Factories\MateriaFactory::class,
        \Database\Factories\GrupoFactory::class,
        \Database\Factories\GrupoHorarioFactory::class,
        \Database\Factories\EvaluacionFactory::class,
        \Database\Factories\BecaFactory::class,
        \Database\Factories\ConvenioFactory::class,
        \Database\Factories\DocenteFactory::class,
        \Database\Factories\AulaFactory::class,
        \Database\Factories\TipoDocumentoFactory::class,
    ] as $factory) {
        if (method_exists($factory, 'resetCounter')) {
            $factory::resetCounter();
        } else if (property_exists($factory, 'i')) {
            $factory::$i = 0;
        }
    }
});