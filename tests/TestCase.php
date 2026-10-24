<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests should not need `npm run build` to pass.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
