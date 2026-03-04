<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Math\Internal\Calculator;
use Cline\Math\Internal\CalculatorRegistry;

test('get with no calculator set detects calculator', function (): void {
    $currentCalculator = CalculatorRegistry::get();

    CalculatorRegistry::set(null);
    self::assertInstanceOf(Calculator::class, CalculatorRegistry::get());

    CalculatorRegistry::set($currentCalculator);
});
