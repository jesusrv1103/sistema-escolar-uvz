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