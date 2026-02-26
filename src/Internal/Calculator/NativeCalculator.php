<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math\Internal\Calculator;

use Cline\Math\Internal\Calculator;
use Override;

use const PHP_INT_SIZE;
use const STR_PAD_LEFT;

use function assert;
use function in_array;
use function intdiv;
use function is_int;
use function mb_ltrim;
use function mb_str_pad;
use function mb_strlen;
use function mb_substr;
use function str_repeat;
use function strcmp;

/**
 * Calculator implementation using only native PHP code.
 *
 * @internal
 * @psalm-immutable
 */
final readonly class NativeCalculator extends Calculator
{
    /**
     * The max number of digits the platform can natively add, subtract, multiply or divide without overflow.
     * For multiplication, this represents the max sum of the lengths of both operands.
     *
     * In addition, it is assumed that an extra digit can hold a carry (1) without overflowing.
     * Example: 32-bit: max number 1,999,999,999 (9 digits + carry)
     *          64-bit: max number 1,999,999,999,999,999,999 (18 digits + carry)
     */
    private int $maxDigits;

    /**
     * @pure
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->maxDigits = match (PHP_INT_SIZE) {
            4 => 9,
            8 => 18,
        };
    }

    #[Override()]
    public function add(string $a, string $b): string
    {
        /**
         * @var numeric-string $a
         * @var numeric-string $b
         */
        $result = $a + $b;

        if (is_int($result)) {
            return (string) $result;
        }

        if ($a === '0') {
            return $b;
        }

        if ($b === '0') {
            return $a;
        }

        [$aNeg, $bNeg, $aDig, $bDig] = $this->init($a, $b);

        $result = $aNeg === $bNeg ? $this->doAdd($aDig, $bDig) : $this->doSub($aDig, $bDig);

        if ($aNeg) {
            return $this->neg($result);
        }

        return $result;
    }

    #[Override()]
    public function sub(string $a, string $b): string
    {
        return $this->add($a, $this->neg($b));
    }

    #[Override()]
    public function mul(string $a, string $b): string
    {
        /**
         * @var numeric-string $a
         * @var numeric-string $b
         */
        $result = $a * $b;

        if (is_int($result)) {
            return (string) $result;
        }

        if ($a === '0' || $b === '0') {
            return '0';
        }

        if ($a === '1') {
            return $b;
        }

        if ($b === '1') {
            return $a;
        }

        if ($a === '-1') {
            return $this->neg($b);
        }

        if ($b === '-1') {
            return $this->neg($a);
        }

        [$aNeg, $bNeg, $aDig, $bDig] = $this->init($a, $b);

        $result = $this->doMul($aDig, $bDig);

        if ($aNeg !== $bNeg) {
            return $this->neg($result);
        }

        return $result;
    }

    #[Override()]
    public function divQ(string $a, string $b): string
    {
        return $this->divQR($a, $b)[0];
    }

    #[Override()]
    public function divR(string $a, string $b): string
    {
        return $this->divQR($a, $b)[1];
    }

    #[Override()]
    public function divQR(string $a, string $b): array
    {
        if ($a === '0') {
            return ['0', '0'];
        }

        if ($a === $b) {
            return ['1', '0'];
        }

        if ($b === '1') {
            return [$a, '0'];
        }

        if ($b === '-1') {
            return [$this->neg($a), '0'];
        }

        /** @var numeric-string $a */
        $na = $a * 1; // cast to number

        if (is_int($na)) {
            /** @var numeric-string $b */
            $nb = $b * 1;

            if (is_int($nb)) {
                // the only division that may overflow is PHP_INT_MIN / -1,
                // which cannot happen here as we've already handled a divisor of -1 above.
                $q = intdiv($na, $nb);
                $r = $na % $nb;

                return [
                    (string) $q,
                    (string) $r,
                ];
            }
        }

        [$aNeg, $bNeg, $aDig, $bDig] = $this->init($a, $b);

        [$q, $r] = $this->doDiv($aDig, $bDig);

        if ($aNeg !== $bNeg) {
            $q = $this->neg($q);
        }

        if ($aNeg) {
            $r = $this->neg($r);
        }

        return [$q, $r];
    }

    #[Override()]
    public function pow(string $a, int $e): string
    {
        if ($e === 0) {
            return '1';
        }

        if ($e === 1) {
            return $a;
        }

        $odd = $e % 2;
        $e -= $odd;

        $aa = $this->mul($a, $a);

        $result = $this->pow($aa, $e / 2);

        if ($odd === 1) {
            return $this->mul($result, $a);
        }

        return $result;
    }

    /**
     * Algorithm from: https://www.geeksforgeeks.org/modular-exponentiation-power-in-modular-arithmetic/.
     */
    #[Override()]
    public function modPow(string $base, string $exp, string $mod): string
    {
        // normalize to Euclidean representative so modPow() stays consistent with mod()
        $base = $this->mod($base, $mod);

        // special case: the algorithm below fails with power 0 mod 1 (returns 1 instead of 0)
        if ($exp === '0' && $mod === '1') {
            return '0';
        }

        $x = $base;

        $res = '1';

        // numbers are positive, so we can use remainder instead of modulo
        $x = $this->divR($x, $mod);

        while ($exp !== '0') {
            if (in_array($exp[-1], ['1', '3', '5', '7', '9'], true)) { // odd
                $res = $this->divR($this->mul($res, $x), $mod);
            }

            $exp = $this->divQ($exp, '2');
            $x = $this->divR($this->mul($x, $x), $mod);
        }

        return $res;
    }

    /**
     * Adapted from https://cp-algorithms.com/num_methods/roots_newton.html.
     */
    #[Override()]
    public function sqrt(string $n): string
    {
        if ($n === '0') {
            return '0';
        }

        // initial approximation
        $x = str_repeat('9', intdiv(mb_strlen($n), 2) ?: 1);

        $decreased = false;

        while (true) {
            $nx = $this->divQ($this->add($x, $this->divQ($n, $x)), '2');

            if ($x === $nx || $this->cmp($nx, $x) > 0 && $decreased) {
                break;
            }

            $decreased = $this->cmp($nx, $x) < 0;
            $x = $nx;
        }

        return $x;
    }

    /**
     * Performs the addition of two non-signed large integers.
     *
     * @pure
     */
    private function doAdd(string $a, string $b): string
    {
        [$a, $b, $length] = $this->pad($a, $b);

        $carry = 0;
        $result = '';

        for ($i = $length - $this->maxDigits; ; $i -= $this->maxDigits) {
            $blockLength = $this->maxDigits;

            if ($i < 0) {
                $blockLength += $i;
                $i = 0;
            }

            /** @var numeric-string $blockA */
            $blockA = mb_substr($a, $i, $blockLength);

            /** @var numeric-string $blockB */
            $blockB = mb_substr($b, $i, $blockLength);

            $sum = (string) ($blockA + $blockB + $carry);
            $sumLength = mb_strlen($sum);

            if ($sumLength > $blockLength) {
                $sum = mb_substr($sum, 1);
                $carry = 1;
            } else {
                if ($sumLength < $blockLength) {
                    $sum = str_repeat('0', $blockLength - $sumLength).$sum;
                }

                $carry = 0;
            }

            $result = $sum.$result;

            if ($i === 0) {
                break;
            }
        }

        if ($carry === 1) {
            return '1'.$result;
        }

        return $result;
    }

    /**
     * Performs the subtraction of two non-signed large integers.
     *
     * @pure
     */
    private function doSub(string $a, string $b): string
    {
        if ($a === $b) {
            return '0';
        }

        // Ensure that we always subtract to a positive result: biggest minus smallest.
        $cmp = $this->doCmp($a, $b);

        $invert = $cmp === -1;

        if ($invert) {
            $c = $a;
            $a = $b;
            $b = $c;
        }

        [$a, $b, $length] = $this->pad($a, $b);

        $carry = 0;
        $result = '';

        $complement = 10 ** $this->maxDigits;

        for ($i = $length - $this->maxDigits; ; $i -= $this->maxDigits) {
            $blockLength = $this->maxDigits;

            if ($i < 0) {
                $blockLength += $i;
                $i = 0;
            }

            /** @var numeric-string $blockA */
            $blockA = mb_substr($a, $i, $blockLength);

            /** @var numeric-string $blockB */
            $blockB = mb_substr($b, $i, $blockLength);

            $sum = $blockA - $blockB - $carry;

            if ($sum < 0) {
                $sum += $complement;
                $carry = 1;
            } else {
                $carry = 0;
            }

            $sum = (string) $sum;
            $sumLength = mb_strlen($sum);

            if ($sumLength < $blockLength) {
                $sum = str_repeat('0', $blockLength - $sumLength).$sum;
            }

            $result = $sum.$result;

            if ($i === 0) {
                break;
            }
        }

        // Carry cannot be 1 when the loop ends, as a > b
        /** @phpstan-ignore-next-line possiblyImpure.functionCall */
        assert($carry === 0);

        $result = mb_ltrim($result, '0');

        if ($invert) {
            return $this->neg($result);
        }

        return $result;
    }

    /**
     * Performs the multiplication of two non-signed large integers.
     *
     * @pure
     */
    private function doMul(string $a, string $b): string
    {
        $x = mb_strlen($a);
        $y = mb_strlen($b);

        $maxDigits = intdiv($this->maxDigits, 2);
        $complement = 10 ** $maxDigits;

        $result = '0';

        for ($i = $x - $maxDigits; ; $i -= $maxDigits) {
            $blockALength = $maxDigits;

            if ($i < 0) {
                $blockALength += $i;
                $i = 0;
            }

            $blockA = (int) mb_substr($a, $i, $blockALength);

            $line = '';
            $carry = 0;

            for ($j = $y - $maxDigits; ; $j -= $maxDigits) {
                $blockBLength = $maxDigits;

                if ($j < 0) {
                    $blockBLength += $j;
                    $j = 0;
                }

                $blockB = (int) mb_substr($b, $j, $blockBLength);

                $mul = $blockA * $blockB + $carry;
                $value = $mul % $complement;
                $carry = ($mul - $value) / $complement;

                $value = (string) $value;

                /** @phpstan-ignore-next-line impure.functionCall */
                $value = mb_str_pad($value, $maxDigits, '0', STR_PAD_LEFT);

                $line = $value.$line;

                if ($j === 0) {
                    break;
                }
            }

            if ($carry !== 0) {
                $line = $carry.$line;
            }

            $line = mb_ltrim($line, '0');

            if ($line !== '') {
                $line .= str_repeat('0', $x - $blockALength - $i);
                $result = $this->add($result, $line);
            }

            if ($i === 0) {
                break;
            }
        }

        return $result;
    }

    /**
     * Performs the division of two non-signed large integers.
     *
     * @return array<string> The quotient and remainder.
     *
     * @pure
     */
    private function doDiv(string $a, string $b): array
    {
        $cmp = $this->doCmp($a, $b);

        if ($cmp === -1) {
            return ['0', $a];
        }

        $x = mb_strlen($a);
        $y = mb_strlen($b);

        // we now know that a >= b && x >= y

        $q = '0'; // quotient
        $r = $a; // remainder
        $z = $y; // focus length, always $y or $y+1

        /** @var numeric-string $b */
        $nb = $b * 1; // cast to number

        // performance optimization in cases where the remainder will never cause int overflow
        if (is_int(($nb - 1) * 10 + 9)) {
            $r = (int) mb_substr($a, 0, $z - 1);

            for ($i = $z - 1; $i < $x; ++$i) {
                $n = $r * 10 + (int) $a[$i];

                /** @var int $nb */
                $q .= intdiv($n, $nb);
                $r = $n % $nb;
            }

            return [mb_ltrim($q, '0') ?: '0', (string) $r];
        }

        while (true) {
            $focus = mb_substr($a, 0, $z);

            $cmp = $this->doCmp($focus, $b);

            if ($cmp === -1) {
                if ($z === $x) { // remainder < dividend
                    break;
                }

                ++$z;
            }

            $zeros = str_repeat('0', $x - $z);

            $q = $this->add($q, '1'.$zeros);
            $a = $this->sub($a, $b.$zeros);

            $r = $a;

            if ($r === '0') { // remainder == 0
                break;
            }

            $x = mb_strlen($a);

            if ($x < $y) { // remainder < dividend
                break;
            }

            $z = $y;
        }

        return [$q, $r];
    }

    /**
     * Compares two non-signed large numbers.
     *
     * @return -1|0|1
     *
     * @pure
     */
    private function doCmp(string $a, string $b): int
    {
        $x = mb_strlen($a);
        $y = mb_strlen($b);

        $cmp = $x <=> $y;

        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp($a, $b) <=> 0; // enforce -1|0|1
    }

    /**
     * Pads the left of one of the given numbers with zeros if necessary to make both numbers the same length.
     *
     * The numbers must only consist of digits, without leading minus sign.
     *
     * @return array{string, string, int}
     *
     * @pure
     */
    private function pad(string $a, string $b): array
    {
        $x = mb_strlen($a);
        $y = mb_strlen($b);

        if ($x > $y) {
            $b = str_repeat('0', $x - $y).$b;

            return [$a, $b, $x];
        }

        if ($x < $y) {
            $a = str_repeat('0', $y - $x).$a;

            return [$a, $b, $y];
        }

        return [$a, $b, $x];
    }
}
