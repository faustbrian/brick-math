<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math;

use Cline\Math\Exception\DivisionByZeroException;
use Cline\Math\Exception\IntegerOverflowException;
use Cline\Math\Exception\InvalidArgumentException;
use Cline\Math\Exception\MathException;
use Cline\Math\Exception\NegativeNumberException;
use Cline\Math\Exception\NoInverseException;
use Cline\Math\Exception\NumberFormatException;
use Cline\Math\Exception\RandomSourceException;
use Cline\Math\Exception\RoundingNecessaryException;
use Cline\Math\Internal\Calculator;
use Cline\Math\Internal\CalculatorRegistry;
use LogicException;
use Override;
use Throwable;

use const FILTER_VALIDATE_INT;

use function assert;
use function bin2hex;
use function chr;
use function count_chars;
use function filter_var;
use function hex2bin;
use function in_array;
use function intdiv;
use function is_string;
use function mb_ltrim;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function ord;
use function preg_match;
use function preg_quote;
use function random_bytes;
use function str_repeat;
use function throw_if;

/**
 * An arbitrarily large integer number.
 *
 * This class is immutable.
 * @psalm-immutable
 */
final readonly class BigInteger extends BigNumber
{
    /**
     * Protected constructor. Use a factory method to obtain an instance.
     *
     * @param string $value A string of digits, with optional leading minus sign.
     *
     * @pure
     */
    protected function __construct(
        /**
         * The value, as a string of digits with optional leading minus sign.
         *
         * No leading zeros must be present.
         * No leading minus sign must be present if the number is zero.
         */
        private string $value,
    ) {}

    /**
     * This method is required for serializing the object and SHOULD NOT be accessed directly.
     *
     * @internal
     *
     * @return array{value: string}
     */
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    /**
     * This method is only here to allow unserializing the object and cannot be accessed directly.
     *
     * @internal
     *
     * @param array{value: string} $data
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        /** @phpstan-ignore isset.initializedProperty */
        throw_if(isset($this->value), LogicException::class, '__unserialize() is an internal function, it must not be called directly.');

        $this->value = $data['value'];
    }

    /**
     * Creates a number from a string in a given base.
     *
     * The string can optionally be prefixed with the `+` or `-` sign.
     *
     * Bases greater than 36 are not supported by this method, as there is no clear consensus on which of the lowercase
     * or uppercase characters should come first. Instead, this method accepts any base up to 36, and does not
     * differentiate lowercase and uppercase characters, which are considered equal.
     *
     * For bases greater than 36, and/or custom alphabets, use the fromArbitraryBase() method.
     *
     * @param non-empty-string $number The number to convert, in the given base.
     * @param int<2, 36>       $base   The base of the number, between 2 and 36.
     *
     * @throws InvalidArgumentException If the base is out of range.
     * @throws NumberFormatException    If the number is empty, or contains invalid chars for the given base.
     *
     * @pure
     */
    public static function fromBase(string $number, int $base): self
    {
        if ($base < 2 || $base > 36) { // @phpstan-ignore smaller.alwaysFalse, greater.alwaysFalse, booleanOr.alwaysFalse
            throw InvalidArgumentException::baseOutOfRange($base);
        }

        if ($number === '') { // @phpstan-ignore identical.alwaysFalse
            throw NumberFormatException::emptyNumber();
        }

        $originalNumber = $number;

        if ($number[0] === '-') {
            $sign = '-';
            $number = mb_substr($number, 1);
        } elseif ($number[0] === '+') {
            $sign = '';
            $number = mb_substr($number, 1);
        } else {
            $sign = '';
        }

        if ($number === '') {
            throw NumberFormatException::invalidFormat($originalNumber);
        }

        $number = mb_ltrim($number, '0');

        if ($number === '') {
            // The result will be the same in any base, avoid further calculation.
            return self::zero();
        }

        if ($number === '1') {
            // The result will be the same in any base, avoid further calculation.
            return new self($sign.'1');
        }

        $pattern = '/[^'.mb_substr(Calculator::ALPHABET, 0, $base).']/';

        /** @phpstan-ignore-next-line possiblyImpure.functionCall */
        if (preg_match($pattern, mb_strtolower($number), $matches) === 1) {
            throw NumberFormatException::charNotValidInBase($matches[0], $base);
        }

        if ($base === 10) {
            // The number is usable as is, avoid further calculation.
            return new self($sign.$number);
        }

        $result = CalculatorRegistry::get()->fromBase($number, $base);

        return new self($sign.$result);
    }

    /**
     * Parses a string containing an integer in an arbitrary base, using a custom alphabet.
     *
     * This method is byte-oriented: the alphabet is interpreted as a sequence of single-byte characters.
     * Multibyte UTF-8 characters are not supported.
     *
     * Because this method accepts any single-byte character, including dash, it does not handle negative numbers.
     *
     * @param non-empty-string $number   The number to parse.
     * @param non-empty-string $alphabet The alphabet, for example '01' for base 2, or '01234567' for base 8.
     *
     * @throws InvalidArgumentException If the alphabet does not contain at least 2 chars, or contains duplicates.
     * @throws NumberFormatException    If the given number is empty or contains invalid chars for the given alphabet.
     *
     * @pure
     */
    public static function fromArbitraryBase(string $number, string $alphabet): self
    {
        $base = mb_strlen($alphabet);

        if ($base < 2) {
            throw InvalidArgumentException::alphabetTooShort();
        }

        if (mb_strlen(count_chars($alphabet, 3)) !== $base) {
            throw InvalidArgumentException::duplicateCharsInAlphabet();
        }

        if ($number === '') { // @phpstan-ignore identical.alwaysFalse
            throw NumberFormatException::emptyNumber();
        }

        $pattern = '/[^'.preg_quote($alphabet, '/').']/';

        /** @phpstan-ignore-next-line possiblyImpure.functionCall */
        if (preg_match($pattern, $number, $matches) === 1) {
            throw NumberFormatException::charNotInAlphabet($matches[0]);
        }

        $number = CalculatorRegistry::get()->fromArbitraryBase($number, $alphabet, $base);

        return new self($number);
    }

    /**
     * Translates a string of bytes containing the binary representation of a BigInteger into a BigInteger.
     *
     * The input string is assumed to be in big-endian byte-order: the most significant byte is in the zeroth element.
     *
     * If `$signed` is true, the input is assumed to be in two's-complement representation, and the leading bit is
     * interpreted as a sign bit. If `$signed` is false, the input is interpreted as an unsigned number, and the
     * resulting BigInteger will always be positive or zero.
     *
     * This method can be used to retrieve a number exported by `toBytes()`, as long as the `$signed` flags match.
     *
     * @param non-empty-string $value  The byte string.
     * @param bool             $signed Whether to interpret as a signed number in two's-complement representation with a leading
     *                                 sign bit.
     *
     * @throws NumberFormatException If the string is empty.
     *
     * @pure
     */
    public static function fromBytes(string $value, bool $signed = true): self
    {
        if ($value === '') { // @phpstan-ignore identical.alwaysFalse
            throw NumberFormatException::emptyByteString();
        }

        $twosComplement = false;

        if ($signed) {
            $x = ord($value[0]);

            if ($twosComplement = ($x >= 0x80)) {
                $value = ~$value;
            }
        }

        $number = self::fromBase(bin2hex($value), 16);

        if ($twosComplement) {
            return $number->plus(1)->negated();
        }

        return $number;
    }

    /**
     * Generates a pseudo-random number in the range 0 to 2^bitCount - 1.
     *
     * Using the default random bytes generator, this method is suitable for cryptographic use.
     *
     * @param non-negative-int             $bitCount             The number of bits.
     * @param null|(callable(int): string) $randomBytesGenerator A function that accepts a number of bytes, and returns
     *                                                           a string of random bytes of the given length. Defaults
     *                                                           to the `random_bytes()` function.
     *
     * @throws InvalidArgumentException If $bitCount is negative.
     * @throws RandomSourceException    If random byte generation fails.
     */
    public static function randomBits(int $bitCount, ?callable $randomBytesGenerator = null): self
    {
        if ($bitCount < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeBitCount();
        }

        if ($bitCount === 0) {
            return self::zero();
        }

        /** @var int<1, max> $byteLength */
        $byteLength = intdiv($bitCount - 1, 8) + 1;

        $extraBits = $byteLength * 8 - $bitCount;

        /** @var int<0, 255> $bitmaskValue */
        $bitmaskValue = 0xFF >> $extraBits;
        $bitmask = chr($bitmaskValue);

        $randomBytes = self::randomBytes($byteLength, $randomBytesGenerator);
        $randomBytes[0] = $randomBytes[0] & $bitmask;

        return self::fromBytes($randomBytes, false);
    }

    /**
     * Generates a pseudo-random number between `$min` and `$max`, inclusive.
     *
     * Using the default random bytes generator, this method is suitable for cryptographic use.
     *
     * @param BigNumber|int|string         $min                  The lower bound. Must be convertible to a BigInteger.
     * @param BigNumber|int|string         $max                  The upper bound. Must be convertible to a BigInteger.
     * @param null|(callable(int): string) $randomBytesGenerator A function that accepts a number of bytes, and returns
     *                                                           a string of random bytes of the given length. Defaults
     *                                                           to the `random_bytes()` function.
     *
     * @throws InvalidArgumentException If `$min` is greater than `$max`.
     * @throws MathException            If one of the parameters cannot be converted to a BigInteger.
     * @throws RandomSourceException    If random byte generation fails.
     */
    public static function randomRange(
        BigNumber|int|string $min,
        BigNumber|int|string $max,
        ?callable $randomBytesGenerator = null,
    ): self {
        $min = self::of($min);
        $max = self::of($max);

        if ($min->isGreaterThan($max)) {
            throw InvalidArgumentException::minGreaterThanMax();
        }

        if ($min->isEqualTo($max)) {
            return $min;
        }

        $diff = $max->minus($min);
        $bitLength = $diff->getBitLength();

        // try until the number is in range (50% to 100% chance of success)
        do {
            $randomNumber = self::randomBits($bitLength, $randomBytesGenerator);
        } while ($randomNumber->isGreaterThan($diff));

        return $randomNumber->plus($min);
    }

    /**
     * Returns a BigInteger representing zero.
     *
     * @pure
     */
    public static function zero(): self
    {
        /** @var null|self $zero */
        /** @phpstan-ignore-next-line impure.static */
        static $zero;

        if ($zero === null) {
            $zero = new self('0');
        }

        return $zero;
    }

    /**
     * Returns a BigInteger representing one.
     *
     * @pure
     */
    public static function one(): self
    {
        /** @var null|self $one */
        /** @phpstan-ignore-next-line impure.static */
        static $one;

        if ($one === null) {
            $one = new self('1');
        }

        return $one;
    }

    /**
     * Returns a BigInteger representing ten.
     *
     * @pure
     */
    public static function ten(): self
    {
        /** @var null|self $ten */
        /** @phpstan-ignore-next-line impure.static */
        static $ten;

        if ($ten === null) {
            $ten = new self('10');
        }

        return $ten;
    }

    /**
     * Returns the greatest common divisor of the given numbers.
     *
     * The GCD is always positive, unless all numbers are zero, in which case it is zero.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to a BigInteger.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to a BigInteger.
     *
     * @throws MathException If one of the parameters cannot be converted to a BigInteger.
     *
     * @pure
     */
    public static function gcdAll(BigNumber|int|string $a, BigNumber|int|string ...$n): self
    {
        $result = self::of($a)->abs();

        foreach ($n as $next) {
            $result = $result->gcd(self::of($next));

            if ($result->isEqualTo(1)) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Returns the least common multiple of the given numbers.
     *
     * The LCM is always positive, unless one of the numbers is zero, in which case it is zero.
     *
     * @param BigNumber|int|string $a    The first number. Must be convertible to a BigInteger.
     * @param BigNumber|int|string ...$n The additional numbers. Each number must be convertible to a BigInteger.
     *
     * @throws MathException If one of the parameters cannot be converted to a BigInteger.
     *
     * @pure
     */
    public static function lcmAll(BigNumber|int|string $a, BigNumber|int|string ...$n): self
    {
        $result = self::of($a)->abs();

        foreach ($n as $next) {
            $result = $result->lcm(self::of($next));

            if ($result->isZero()) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Returns the sum of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to add. Must be convertible to a BigInteger.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function plus(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            return $this;
        }

        if ($this->isZero()) {
            return $that;
        }

        $value = CalculatorRegistry::get()->add($this->value, $that->value);

        return new self($value);
    }

    /**
     * Returns the difference of this number and the given one.
     *
     * @param BigNumber|int|string $that The number to subtract. Must be convertible to a BigInteger.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function minus(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            return $this;
        }

        if ($this->isZero()) {
            return $that->negated();
        }

        $value = CalculatorRegistry::get()->sub($this->value, $that->value);

        return new self($value);
    }

    /**
     * Returns the product of this number and the given one.
     *
     * @param BigNumber|int|string $that The multiplier. Must be convertible to a BigInteger.
     *
     * @throws MathException If the multiplier is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function multipliedBy(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isOne()) {
            return $this;
        }

        if ($this->isOne()) {
            return $that;
        }

        $value = CalculatorRegistry::get()->mul($this->value, $that->value);

        return new self($value);
    }

    /**
     * Returns the result of the division of this number by the given one.
     *
     * @param BigNumber|int|string $that         The divisor. Must be convertible to a BigInteger.
     * @param RoundingMode         $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigInteger.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the remainder is not zero.
     *
     * @pure
     */
    public function dividedBy(BigNumber|int|string $that, RoundingMode $roundingMode = RoundingMode::Unnecessary): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        if ($that->isOne()) {
            return $this;
        }

        if ($that->isMinusOne()) {
            return $this->negated();
        }

        $result = CalculatorRegistry::get()->divRound($this->value, $that->value, $roundingMode);

        if ($result === null) {
            throw RoundingNecessaryException::integerDivisionNotExact();
        }

        return new self($result);
    }

    /**
     * Returns this number exponentiated to the given value.
     *
     * @param non-negative-int $exponent
     *
     * @throws InvalidArgumentException If the exponent is negative.
     *
     * @pure
     */
    public function power(int $exponent): self
    {
        if ($exponent === 0) {
            return self::one();
        }

        if ($exponent === 1) {
            return $this;
        }

        if ($exponent < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeExponent();
        }

        return new self(CalculatorRegistry::get()->pow($this->value, $exponent));
    }

    /**
     * Returns the quotient of the division of this number by the given one.
     *
     * Examples:
     *
     * - `7` quotient `3` returns `2`
     * - `7` quotient `-3` returns `-2`
     * - `-7` quotient `3` returns `-2`
     * - `-7` quotient `-3` returns `2`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function quotient(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        if ($that->isOne()) {
            return $this;
        }

        if ($that->isMinusOne()) {
            return $this->negated();
        }

        $quotient = CalculatorRegistry::get()->divQ($this->value, $that->value);

        return new self($quotient);
    }

    /**
     * Returns the remainder of the division of this number by the given one.
     *
     * The remainder, when non-zero, has the same sign as the dividend.
     *
     * Examples:
     *
     * - `7` remainder `3` returns `1`
     * - `7` remainder `-3` returns `1`
     * - `-7` remainder `3` returns `-1`
     * - `-7` remainder `-3` returns `-1`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function remainder(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        if ($that->isOne() || $that->isMinusOne()) {
            return self::zero();
        }

        $remainder = CalculatorRegistry::get()->divR($this->value, $that->value);

        return new self($remainder);
    }

    /**
     * Returns the quotient and remainder of the division of this number by the given one.
     *
     * Examples:
     *
     * - `7` quotientAndRemainder `3` returns [`2`, `1`]
     * - `7` quotientAndRemainder `-3` returns [`-2`, `1`]
     * - `-7` quotientAndRemainder `3` returns [`-2`, `-1`]
     * - `-7` quotientAndRemainder `-3` returns [`2`, `-1`]
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigInteger.
     * @return array{self, self}       An array containing the quotient and the remainder.
     *
     * @pure
     */
    public function quotientAndRemainder(BigNumber|int|string $that): array
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        [$quotient, $remainder] = CalculatorRegistry::get()->divQR($this->value, $that->value);

        return [
            new self($quotient),
            new self($remainder),
        ];
    }

    /**
     * Returns this number modulo the given one.
     *
     * The result is always non-negative, and is the unique value `r` such that `0 <= r < m`
     * and `this - r` is a multiple of `m`.
     *
     * This is also known as Euclidean modulo. Unlike `remainder()`, which can return negative values
     * when the dividend is negative, `mod()` always returns a non-negative result.
     *
     * Examples:
     *
     * - `7` mod `3` returns `1`
     * - `-7` mod `3` returns `2`
     *
     * @param BigNumber|int|string $modulus The modulus. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException  If the modulus is zero.
     * @throws InvalidArgumentException If the modulus is negative.
     * @throws MathException            If the modulus is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function mod(BigNumber|int|string $modulus): self
    {
        $modulus = self::of($modulus);

        if ($modulus->isZero()) {
            throw DivisionByZeroException::zeroModulus();
        }

        if ($modulus->isNegative()) {
            throw InvalidArgumentException::negativeModulus();
        }

        $value = CalculatorRegistry::get()->mod($this->value, $modulus->value);

        return new self($value);
    }

    /**
     * Returns the modular multiplicative inverse of this BigInteger modulo $modulus.
     *
     * @param BigNumber|int|string $modulus The modulus. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException  If the modulus is zero.
     * @throws InvalidArgumentException If the modulus is negative.
     * @throws MathException            If the modulus is not valid, or is not convertible to a BigInteger.
     * @throws NoInverseException       If this BigInteger has no multiplicative inverse mod m (that is, this BigInteger
     *                                  is not relatively prime to m).
     *
     * @pure
     */
    public function modInverse(BigNumber|int|string $modulus): self
    {
        $modulus = self::of($modulus);

        if ($modulus->isZero()) {
            throw DivisionByZeroException::zeroModulus();
        }

        if ($modulus->isNegative()) {
            throw InvalidArgumentException::negativeModulus();
        }

        if ($modulus->isOne()) {
            return self::zero();
        }

        $value = CalculatorRegistry::get()->modInverse($this->value, $modulus->value);

        if ($value === null) {
            throw NoInverseException::noModularInverse();
        }

        return new self($value);
    }

    /**
     * Returns this number raised into power with modulo.
     *
     * This operation requires a non-negative exponent and a strictly positive modulus.
     *
     * @param BigNumber|int|string $exponent The exponent. Must be convertible to a BigInteger.
     * @param BigNumber|int|string $modulus  The modulus. Must be convertible to a BigInteger.
     *
     * @throws DivisionByZeroException  If the modulus is zero.
     * @throws InvalidArgumentException If the exponent or modulus is negative.
     * @throws MathException            If the exponent or modulus is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function modPow(BigNumber|int|string $exponent, BigNumber|int|string $modulus): self
    {
        $exponent = self::of($exponent);
        $modulus = self::of($modulus);

        if ($modulus->isZero()) {
            throw DivisionByZeroException::zeroModulus();
        }

        if ($modulus->isNegative()) {
            throw InvalidArgumentException::negativeModulus();
        }

        if ($exponent->isNegative()) {
            throw InvalidArgumentException::negativeExponent();
        }

        $result = CalculatorRegistry::get()->modPow($this->value, $exponent->value, $modulus->value);

        return new self($result);
    }

    /**
     * Returns the greatest common divisor of this number and the given one.
     *
     * The GCD is always positive, unless both operands are zero, in which case it is zero.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function gcd(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            return $this->abs();
        }

        if ($this->isZero()) {
            return $that->abs();
        }

        $value = CalculatorRegistry::get()->gcd($this->value, $that->value);

        return new self($value);
    }

    /**
     * Returns the least common multiple of this number and the given one.
     *
     * The LCM is always positive, unless at least one operand is zero, in which case it is zero.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function lcm(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($this->isZero() || $that->isZero()) {
            return self::zero();
        }

        $value = CalculatorRegistry::get()->lcm($this->value, $that->value);

        return new self($value);
    }

    /**
     * Returns the integer square root of this number, rounded according to the given rounding mode.
     *
     * @param RoundingMode $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws NegativeNumberException    If this number is negative.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used, and the number is not a perfect square.
     *
     * @pure
     */
    public function sqrt(RoundingMode $roundingMode = RoundingMode::Unnecessary): self
    {
        if ($this->isNegative()) {
            throw NegativeNumberException::squareRootOfNegativeNumber();
        }

        $calculator = CalculatorRegistry::get();

        $sqrt = $calculator->sqrt($this->value);

        // For Down and Floor (equivalent for non-negative numbers), return floor sqrt
        if ($roundingMode === RoundingMode::Down || $roundingMode === RoundingMode::Floor) {
            return new self($sqrt);
        }

        // Check if the sqrt is exact
        $s2 = $calculator->mul($sqrt, $sqrt);
        $remainder = $calculator->sub($this->value, $s2);

        if ($remainder === '0') {
            // sqrt is exact
            return new self($sqrt);
        }

        // sqrt is not exact
        if ($roundingMode === RoundingMode::Unnecessary) {
            throw RoundingNecessaryException::integerSquareRootNotExact();
        }

        // For Up and Ceiling (equivalent for non-negative numbers), round up
        if ($roundingMode === RoundingMode::Up || $roundingMode === RoundingMode::Ceiling) {
            return new self($calculator->add($sqrt, '1'));
        }

        // For Half* modes, compare our number to the midpoint of the interval [s², (s+1)²[.
        // The midpoint is s² + s + 0.5. Comparing n >= s² + s + 0.5 with remainder = n − s²
        // is equivalent to comparing 2*remainder >= 2*s + 1.
        $twoRemainder = $calculator->mul($remainder, '2');
        $threshold = $calculator->add($calculator->mul($sqrt, '2'), '1');
        $cmp = $calculator->cmp($twoRemainder, $threshold);

        // We're supposed to increment (round up) when:
        //   - HalfUp, HalfCeiling => $cmp >= 0
        //   - HalfDown, HalfFloor => $cmp > 0
        //   - HalfEven => $cmp > 0 || ($cmp === 0 && $sqrt % 2 === 1)
        // But 2*remainder is always even and 2*s + 1 is always odd, so $cmp is never zero.
        // Therefore, all Half* modes simplify to:
        if ($cmp > 0) {
            $sqrt = $calculator->add($sqrt, '1');
        }

        return new self($sqrt);
    }

    #[Override()]
    public function negated(): static
    {
        return new self(CalculatorRegistry::get()->neg($this->value));
    }

    /**
     * Returns the integer bitwise-and combined with another integer.
     *
     * This method returns a negative BigInteger if and only if both operands are negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function and(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        return new self(CalculatorRegistry::get()->and($this->value, $that->value));
    }

    /**
     * Returns the integer bitwise-or combined with another integer.
     *
     * This method returns a negative BigInteger if and only if either of the operands is negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function or(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        return new self(CalculatorRegistry::get()->or($this->value, $that->value));
    }

    /**
     * Returns the integer bitwise-xor combined with another integer.
     *
     * This method returns a negative BigInteger if and only if exactly one of the operands is negative.
     *
     * @param BigNumber|int|string $that The operand. Must be convertible to a BigInteger.
     *
     * @throws MathException If the operand is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public function xor(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        return new self(CalculatorRegistry::get()->xor($this->value, $that->value));
    }

    /**
     * Returns the bitwise-not of this BigInteger.
     *
     * @pure
     */
    public function not(): self
    {
        return $this->negated()->minus(1);
    }

    /**
     * Returns the integer left shifted by a given number of bits.
     *
     * @pure
     */
    public function shiftedLeft(int $bits): self
    {
        if ($bits === 0) {
            return $this;
        }

        if ($bits < 0) {
            return $this->shiftedRight(-$bits);
        }

        return $this->multipliedBy(self::of(2)->power($bits));
    }

    /**
     * Returns the integer right shifted by a given number of bits.
     *
     * @pure
     */
    public function shiftedRight(int $bits): self
    {
        if ($bits === 0) {
            return $this;
        }

        if ($bits < 0) {
            return $this->shiftedLeft(-$bits);
        }

        $operand = self::of(2)->power($bits);

        if ($this->isPositiveOrZero()) {
            return $this->quotient($operand);
        }

        return $this->dividedBy($operand, RoundingMode::Up);
    }

    /**
     * Returns the number of bits in the minimal two's-complement representation of this BigInteger, excluding a sign bit.
     *
     * For positive BigIntegers, this is equivalent to the number of bits in the ordinary binary representation.
     * Computes (ceil(log2(this < 0 ? -this : this+1))).
     *
     * @return non-negative-int
     *
     * @pure
     */
    public function getBitLength(): int
    {
        if ($this->isZero()) {
            return 0;
        }

        if ($this->isNegative()) {
            return $this->abs()->minus(1)->getBitLength();
        }

        return mb_strlen($this->toBase(2));
    }

    /**
     * Returns the index of the rightmost (lowest-order) one bit in this BigInteger.
     *
     * Returns -1 if this BigInteger contains no one bits.
     *
     * @return -1|non-negative-int
     *
     * @pure
     */
    public function getLowestSetBit(): int
    {
        $n = $this;
        $bitLength = $this->getBitLength();

        for ($i = 0; $i <= $bitLength; ++$i) {
            if ($n->isOdd()) {
                return $i;
            }

            $n = $n->shiftedRight(1);
        }

        return -1;
    }

    /**
     * Returns true if and only if the designated bit is set.
     *
     * Computes ((this & (1<<bitIndex)) != 0).
     *
     * @param non-negative-int $bitIndex The bit to test, 0-based.
     *
     * @throws InvalidArgumentException If the bit to test is negative.
     *
     * @pure
     */
    public function isBitSet(int $bitIndex): bool
    {
        if ($bitIndex < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeBitIndex();
        }

        return $this->shiftedRight($bitIndex)->isOdd();
    }

    /**
     * Returns whether this number is even.
     *
     * @pure
     */
    public function isEven(): bool
    {
        return in_array($this->value[-1], ['0', '2', '4', '6', '8'], true);
    }

    /**
     * Returns whether this number is odd.
     *
     * @pure
     */
    public function isOdd(): bool
    {
        return in_array($this->value[-1], ['1', '3', '5', '7', '9'], true);
    }

    #[Override()]
    public function compareTo(BigNumber|int|string $that): int
    {
        $that = BigNumber::of($that);

        if ($that instanceof self) {
            return CalculatorRegistry::get()->cmp($this->value, $that->value);
        }

        return -$that->compareTo($this);
    }

    #[Override()]
    public function getSign(): int
    {
        return ($this->value === '0') ? 0 : (($this->value[0] === '-') ? -1 : 1);
    }

    #[Override()]
    public function toBigInteger(): self
    {
        return $this;
    }

    #[Override()]
    public function toBigDecimal(): BigDecimal
    {
        /** @var numeric-string $value */
        $value = $this->value;

        return self::newBigDecimal($value);
    }

    #[Override()]
    public function toBigRational(): BigRational
    {
        return self::newBigRational($this, self::one(), false, false);
    }

    #[Override()]
    public function toScale(int $scale, RoundingMode $roundingMode = RoundingMode::Unnecessary): BigDecimal
    {
        return $this->toBigDecimal()->toScale($scale, $roundingMode);
    }

    #[Override()]
    public function toInt(): int
    {
        $intValue = filter_var($this->value, FILTER_VALIDATE_INT);

        if ($intValue === false) {
            throw IntegerOverflowException::integerOutOfRange($this);
        }

        return $intValue;
    }

    #[Override()]
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Returns a string representation of this number in the given base.
     *
     * The output will always be lowercase for bases greater than 10.
     *
     * @param int<2, 36> $base
     *
     * @throws InvalidArgumentException If the base is out of range.
     *
     * @pure
     */
    public function toBase(int $base): string
    {
        if ($base === 10) {
            return $this->value;
        }

        if ($base < 2 || $base > 36) { // @phpstan-ignore smaller.alwaysFalse, greater.alwaysFalse, booleanOr.alwaysFalse
            throw InvalidArgumentException::baseOutOfRange($base);
        }

        return CalculatorRegistry::get()->toBase($this->value, $base);
    }

    /**
     * Returns a string representation of this number in an arbitrary base with a custom alphabet.
     *
     * This method is byte-oriented: the alphabet is interpreted as a sequence of single-byte characters.
     * Multibyte UTF-8 characters are not supported.
     *
     * Because this method accepts any single-byte character, including dash, it does not handle negative numbers;
     * a NegativeNumberException will be thrown when attempting to call this method on a negative number.
     *
     * @param non-empty-string $alphabet The alphabet, for example '01' for base 2, or '01234567' for base 8.
     *
     * @throws InvalidArgumentException If the alphabet does not contain at least 2 chars, or contains duplicates.
     * @throws NegativeNumberException  If this number is negative.
     *
     * @pure
     */
    public function toArbitraryBase(string $alphabet): string
    {
        $base = mb_strlen($alphabet);

        if ($base < 2) {
            throw InvalidArgumentException::alphabetTooShort();
        }

        if (mb_strlen(count_chars($alphabet, 3)) !== $base) {
            throw InvalidArgumentException::duplicateCharsInAlphabet();
        }

        if ($this->isNegative()) {
            throw NegativeNumberException::toArbitraryBaseOfNegativeNumber();
        }

        return CalculatorRegistry::get()->toArbitraryBase($this->value, $alphabet, $base);
    }

    /**
     * Returns a string of bytes containing the binary representation of this BigInteger.
     *
     * The string is in big-endian byte-order: the most significant byte is in the zeroth element.
     *
     * If `$signed` is true, the output will be in two's-complement representation, and a sign bit will be prepended to
     * the output. If `$signed` is false, no sign bit will be prepended, and this method will throw an exception if the
     * number is negative.
     *
     * The string will contain the minimum number of bytes required to represent this BigInteger, including a sign bit
     * if `$signed` is true.
     *
     * This representation is compatible with the `fromBytes()` factory method, as long as the `$signed` flags match.
     *
     * @param bool $signed Whether to output a signed number in two's-complement representation with a leading sign bit.
     *
     * @throws NegativeNumberException If $signed is false, and the number is negative.
     *
     * @pure
     */
    public function toBytes(bool $signed = true): string
    {
        if (!$signed && $this->isNegative()) {
            throw NegativeNumberException::unsignedBytesOfNegativeNumber();
        }

        $hex = $this->abs()->toBase(16);

        if (mb_strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        $baseHexLength = mb_strlen($hex);

        if ($signed) {
            if ($this->isNegative()) {
                /** @phpstan-ignore-next-line possiblyImpure.functionCall */
                $bin = hex2bin($hex);

                /** @phpstan-ignore-next-line possiblyImpure.functionCall */
                assert($bin !== false);

                /** @var non-empty-string $hex */
                $hex = bin2hex(~$bin);
                $hex = self::fromBase($hex, 16)->plus(1)->toBase(16);
                $hexLength = mb_strlen($hex);

                if ($hexLength < $baseHexLength) {
                    $hex = str_repeat('0', $baseHexLength - $hexLength).$hex;
                }

                if ($hex[0] < '8') {
                    $hex = 'FF'.$hex;
                }
            } elseif ($hex[0] >= '8') {
                $hex = '00'.$hex;
            }
        }

        /** @phpstan-ignore-next-line possiblyImpure.functionCall */
        $result = hex2bin($hex);

        /** @phpstan-ignore-next-line possiblyImpure.functionCall */
        assert($result !== false);

        return $result;
    }

    /**
     * @return numeric-string
     */
    #[Override()]
    public function toString(): string
    {
        /** @phpstan-ignore return.type */
        return $this->value;
    }

    #[Override()]
    protected static function from(BigNumber $number): static
    {
        return $number->toBigInteger();
    }

    /**
     * Returns random bytes from the provided generator or from random_bytes().
     *
     * @param int                          $byteLength           The number of requested bytes.
     * @param null|(callable(int): string) $randomBytesGenerator The random bytes generator, or null to use random_bytes().
     *
     * @throws RandomSourceException If random byte generation fails.
     */
    private static function randomBytes(int $byteLength, ?callable $randomBytesGenerator): string
    {
        if ($randomBytesGenerator === null) {
            $randomBytesGenerator = random_bytes(...);
        }

        try {
            $randomBytes = $randomBytesGenerator($byteLength);
        } catch (Throwable $throwable) {
            throw RandomSourceException::randomSourceFailure($throwable);
        }

        /** @phpstan-ignore function.alreadyNarrowedType (Defensive runtime check for user-provided callbacks) */
        if (!is_string($randomBytes)) {
            throw RandomSourceException::invalidRandomBytesType($randomBytes);
        }

        if (mb_strlen($randomBytes) !== $byteLength) {
            throw RandomSourceException::invalidRandomBytesLength($byteLength, mb_strlen($randomBytes));
        }

        return $randomBytes;
    }

    /**
     * @pure
     */
    private function isOne(): bool
    {
        return $this->value === '1';
    }

    /**
     * @pure
     */
    private function isMinusOne(): bool
    {
        return $this->value === '-1';
    }
}
