<?php

declare(strict_types=1);

use Cline\Math\Internal\Calculator\GmpCalculator;
use Cline\Math\Internal\Calculator\BcMathCalculator;
use Cline\Math\Internal\Calculator\NativeCalculator;
use Cline\Math\Internal\Calculator;
use Cline\Math\Internal\CalculatorRegistry;

require __DIR__ . '/vendor/autoload.php';

function getCalculatorImplementation(): Calculator
{
    switch ($calculator = getenv('CALCULATOR')) {
        case 'GMP':
            $calculator = new GmpCalculator();

            break;

        case 'BCMath':
            $calculator = new BcMathCalculator();

            break;

        case 'Native':
            $calculator = new NativeCalculator();

            break;

        default:
            if ($calculator === false) {
                echo 'CALCULATOR environment variable not set!' . PHP_EOL;
            } else {
                echo 'Unknown calculator: ' . $calculator . PHP_EOL;
            }

            echo 'Example usage: CALCULATOR={calculator} vendor/bin/phpunit' . PHP_EOL;
            echo 'Available calculators: GMP, BCMath, Native' . PHP_EOL;
            exit(1);
    }

    echo 'Using ', $calculator::class, PHP_EOL;

    return $calculator;
}

CalculatorRegistry::set(getCalculatorImplementation());

$scale = getenv('BCMATH_DEFAULT_SCALE');

if ($scale !== false) {
    echo sprintf('Using bcscale(%s)', $scale), PHP_EOL;
    bcscale((int) $scale);
}
