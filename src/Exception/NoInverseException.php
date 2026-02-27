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
 * Exception thrown when attempting to compute a modular inverse that does not exist.
 */
final class NoInverseException extends RuntimeException implements MathException
{
    /**
     * @pure
     */
    public static function noModularInverse(): self
    {
        return new self('This number has no multiplicative inverse modulo the given modulus (they are not coprime).');
    }
}
