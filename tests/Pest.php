<?php

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific PHPUnit test
 | case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
 | need to change it using the "uses()" function to bind a different classes or traits.
 |
 | Note: Individual test files declare their own uses() to avoid conflicts.
 |
 */

/*
 |--------------------------------------------------------------------------
 | Test Groups
 |--------------------------------------------------------------------------
 |
 | Tests are organized into groups for selective execution:
 |
 | - unit: Fast unit tests that don't require Laravel (pure PHP)
 | - integration: Tests that require Laravel/HTTP mocking
 | - fast: Quick tests (< 100ms each)
 | - slow: Slower tests requiring full application bootstrap
 | - pure: Pure unit tests with no framework dependencies
 |
 | Usage:
 |   composer test:unit         # Run only unit tests
 |   composer test:integration  # Run only integration tests
 |   composer test:fast         # Run fast tests only
 |   vendor/bin/pest --group=unit --group=fast
 |
 */

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain conditions. The
 | "expect()" function gives you access to a set of "expectations" methods that you can use
 | to assert different things. Of course, you may extend the Expectation API at any time.
 |
 */

// Add any custom expectations here

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code specific to your
 | project that you don't want to repeat in every file. Here you can also expose helpers as
 | global functions to help you to reduce the number of lines of code in your test files.
 |
 */

// Add any global test helpers here
