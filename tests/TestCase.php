<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // HTML behavior is tested independently of asset compilation.
        // Vite bundles are built in the dedicated frontend CI step.
        $this->withoutVite();
    }
}
