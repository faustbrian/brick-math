<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math\Exception;

use Cline\Math\BigInteger;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

use function sprintf;

/**
 * Exception thrown when an integer overflow occurs.
 */
final class IntegerOverflowException extends RuntimeException implements MathException
{
    /**
     * @pure
     */
    public static function integerOutOfRange(BigInteger $value): self
    {
        $message = '%s is out of range [%d, %d] and cannot be represented as an integer.';

        return new self(sprintf($message, $value->toString(), PHP_INT_MIN, PHP_INT_MAX));
    }
}
