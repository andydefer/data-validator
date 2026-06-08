<?php

declare(strict_types=1);

namespace AndyDefer\DataValidator\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for the Data Validator package.
 *
 * Provides a consistent testing environment for validation and sanitization logic.
 * This package has no Laravel dependencies, so a plain PHPUnit test case is sufficient.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
