<?php

use Tests\FeatureTestCase;

/*
 | Pest runs the PHPUnit test classes of this project unchanged. A test written in Pest's function style under `tests/Feature` gets the
 | same base class as the class-based ones (the dedicated test database, one transaction per test; see `FeatureTestCase`). A unit test
 | in function style that needs the application opts in with `uses(Tests\TestCase::class)`.
 */
pest()->extend(FeatureTestCase::class)->in('Feature');
