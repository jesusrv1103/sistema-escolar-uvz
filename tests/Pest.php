<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Todas las pruebas Feature corren sobre la base de datos EXCLUSIVA de
| pruebas `sistema_escolar_testing` (MySQL), definida en phpunit.xml.
| Prohibido ejecutarlas contra la base de desarrollo o producción.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');
