<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math\Internal;

use Cline\Math\Internal\Calculator\BcMathCalculator;
use Cline\Math\Internal\Calculator\GmpCalculator;
use Cline\Math\Internal\Calculator\NativeCalculator;

use function extension_loaded;

/**
 * Stores the current Calculator instance used by BigNumber classes.
 *
 * @internal
 */
final class CalculatorRegistry
{
    /**
     * The Calculator instance in use.
     */
    private static ?Calculator $instance = null;

    /**
     * Sets the Calculator instance to use.
     *
     * An instance is typically set only in unit tests: autodetect is usually the best option.
     *
     * @param null|Calculator $calculator The calculator instance, or null to revert to autodetect.
     */
    public static function set(?Calculator $calculator): void
    {
        self::$instance = $calculator;
    }

    /**
     * Returns the Calculator instance to use.
     *
     * If none has been explicitly set, the fastest available implementation will be returned.
     *
     * Note: even though this method is not technically pure, it is considered pure when used in a normal context, when
     * only relying on autodetect.
     *
     * @pure
     */
    public static function get(): Calculator
    {
        /** @phpstan-ignore impure.staticPropertyAccess */
        if (!self::$instance instanceof Calculator) {
            /** @phpstan-ignore impure.propertyAssign, possiblyImpure.methodCall */
            self::$instance = self::detect();
        }

        /** @phpstan-ignore impure.staticPropertyAccess */
        return self::$instance;
    }

    /**
     * Returns the fastest available Calculator implementation.
     * @codeCoverageIgnore
     */
    private static function detect(): Calculator
    {
        if (extension_loaded('gmp')) {
            return new GmpCalculator();
        }

        if (extension_loaded('bcmath')) {
            return new BcMathCalculator();
        }

        return new NativeCalculator();
    }
}
