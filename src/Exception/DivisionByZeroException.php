<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math\Exception;

use RuntimeException;

/**
 * Exception thrown when a division by zero occurs.
 */
final class DivisionByZeroException extends RuntimeException implements MathException
{
    /**
     * @pure
     */
    public static function divisionByZero(): self
    {
        return new self('Division by zero.');
    }

    /**
     * @pure
     */
    public static function zeroModulus(): self
    {
        return new self('The modulus must not be zero.');
    }

    /**
     * @pure
     */
    public static function zeroDenominator(): self
    {
        return new self('The denominator of a rational number must not be zero.');
    }

    /**
     * @pure
     */
    public static function reciprocalOfZero(): self
    {
        return new self('The reciprocal of zero is undefined.');
    }
}
