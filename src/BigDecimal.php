<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Math;

use Cline\Math\Exception\DivisionByZeroException;
use Cline\Math\Exception\InvalidArgumentException;
use Cline\Math\Exception\MathException;
use Cline\Math\Exception\NegativeNumberException;
use Cline\Math\Exception\RoundingNecessaryException;
use Cline\Math\Internal\CalculatorRegistry;
use Cline\Math\Internal\DecimalHelper;
use LogicException;
use Override;

use function in_array;
use function intdiv;
use function max;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function str_repeat;
use function throw_if;

/**
 * An arbitrarily large decimal number.
 *
 * This class is immutable.
 *
 * The scale of the number is the number of digits after the decimal point. It is always positive or zero.
 * @psalm-immutable
 */
final readonly class BigDecimal extends BigNumber
{
    /**
     * Protected constructor. Use a factory method to obtain an instance.
     *
     * @param string           $value The unscaled value, validated.
     * @param non-negative-int $scale The scale, validated.
     *
     * @pure
     */
    protected function __construct(
        /**
         * The unscaled value of this decimal number.
         *
         * This is a string of digits with an optional leading minus sign.
         * No leading zero must be present.
         * No leading minus sign must be present if the value is 0.
         */
        private string $value,
        /**
         * The scale (number of digits after the decimal point) of this decimal number.
         *
         * This must be zero or more.
         *
         * @var non-negative-int
         */
        private int $scale = 0,
    ) {}

    /**
     * This method is required for serializing the object and SHOULD NOT be accessed directly.
     *
     * @internal
     *
     * @return array{value: string, scale: int}
     */
    public function __serialize(): array
    {
        return ['value' => $this->value, 'scale' => $this->scale];
    }

    /**
     * This method is only here to allow unserializing the object and cannot be accessed directly.
     *
     * @internal
     *
     * @param array{value: string, scale: non-negative-int} $data
     *
     * @throws LogicException
     */
    public function __unserialize(array $data): void
    {
        /** @phpstan-ignore isset.initializedProperty */
        throw_if(isset($this->value), LogicException::class, '__unserialize() is an internal function, it must not be called directly.');

        $this->value = $data['value'];
        $this->scale = $data['scale'];
    }

    /**
     * Creates a BigDecimal from an unscaled value and a scale.
     *
     * Example: `(12345, 3)` will result in the BigDecimal `12.345`.
     *
     * A negative scale is normalized to zero by appending zeros to the unscaled value.
     *
     * Example: `(12345, -3)` will result in the BigDecimal `12345000`.
     *
     * @param BigNumber|int|string $value The unscaled value. Must be convertible to a BigInteger.
     * @param int                  $scale The scale of the number. If negative, the scale will be set to zero
     *                                    and the unscaled value will be adjusted accordingly.
     *
     * @throws MathException If the value is not valid, or is not convertible to a BigInteger.
     *
     * @pure
     */
    public static function ofUnscaledValue(BigNumber|int|string $value, int $scale = 0): self
    {
        $value = BigInteger::of($value)->toString();

        if ($scale < 0) {
            if ($value !== '0') {
                $value .= str_repeat('0', -$scale);
            }

            $scale = 0;
        }

        return new self($value, $scale);
    }

    /**
     * Returns a BigDecimal representing zero, with a scale of zero.
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
     * Returns a BigDecimal representing one, with a scale of zero.
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
     * Returns a BigDecimal representing ten, with a scale of zero.
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
     * Returns the sum of this number and the given one.
     *
     * The result has a scale of `max($this->scale, $that->scale)`.
     *
     * @param BigNumber|int|string $that The number to add. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function plus(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero() && $that->scale <= $this->scale) {
            return $this;
        }

        if ($this->isZero() && $this->scale <= $that->scale) {
            return $that;
        }

        [$a, $b] = $this->scaleValues($this, $that);

        $value = CalculatorRegistry::get()->add($a, $b);
        $scale = max($this->scale, $that->scale);

        return new self($value, $scale);
    }

    /**
     * Returns the difference of this number and the given one.
     *
     * The result has a scale of `max($this->scale, $that->scale)`.
     *
     * @param BigNumber|int|string $that The number to subtract. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the number is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function minus(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero() && $that->scale <= $this->scale) {
            return $this;
        }

        if ($this->isZero() && $this->scale <= $that->scale) {
            return $that->negated();
        }

        [$a, $b] = $this->scaleValues($this, $that);

        $value = CalculatorRegistry::get()->sub($a, $b);
        $scale = max($this->scale, $that->scale);

        return new self($value, $scale);
    }

    /**
     * Returns the product of this number and the given one.
     *
     * The result has a scale of `$this->scale + $that->scale`.
     *
     * @param BigNumber|int|string $that The multiplier. Must be convertible to a BigDecimal.
     *
     * @throws MathException If the multiplier is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function multipliedBy(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isOneScaleZero()) {
            return $this;
        }

        if ($this->isOneScaleZero()) {
            return $that;
        }

        if ($this->isZero() || $that->isZero()) {
            return new self('0', $this->scale + $that->scale);
        }

        $value = CalculatorRegistry::get()->mul($this->value, $that->value);
        $scale = $this->scale + $that->scale;

        return new self($value, $scale);
    }

    /**
     * Returns the result of the division of this number by the given one, at the given scale.
     *
     * @param BigNumber|int|string $that         The divisor. Must be convertible to a BigDecimal.
     * @param non-negative-int     $scale        The desired scale. Must be non-negative.
     * @param RoundingMode         $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws InvalidArgumentException   If the scale is negative.
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the result cannot be represented
     *                                    exactly at the given scale.
     *
     * @pure
     */
    public function dividedBy(BigNumber|int|string $that, int $scale, RoundingMode $roundingMode = RoundingMode::Unnecessary): self
    {
        if ($scale < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeScale();
        }

        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        if ($that->isOneScaleZero() && $scale === $this->scale) {
            return $this;
        }

        $p = $this->valueWithMinScale($that->scale + $scale);
        $q = $that->valueWithMinScale($this->scale - $scale);

        $calculator = CalculatorRegistry::get();
        $result = $calculator->divRound($p, $q, $roundingMode);

        if ($result === null) {
            [$a, $b] = $this->scaleValues($this->abs(), $that->abs());

            $denominator = $calculator->divQ($b, $calculator->gcd($a, $b));
            $requiredScale = DecimalHelper::computeScaleFromReducedFractionDenominator($denominator);

            if ($requiredScale === null) {
                throw RoundingNecessaryException::decimalDivisionNotExact();
            }

            throw RoundingNecessaryException::decimalDivisionScaleTooSmall();
        }

        return new self($result, $scale);
    }

    /**
     * Returns the exact result of the division of this number by the given one.
     *
     * The scale of the result is automatically calculated to fit all the fraction digits.
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws DivisionByZeroException    If the divisor is zero.
     * @throws MathException              If the divisor is not valid, or is not convertible to a BigDecimal.
     * @throws RoundingNecessaryException If the result yields an infinite number of digits.
     *
     * @pure
     */
    public function dividedByExact(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        [$a, $b] = $this->scaleValues($this->abs(), $that->abs());

        $calculator = CalculatorRegistry::get();

        $denominator = $calculator->divQ($b, $calculator->gcd($a, $b));
        $scale = DecimalHelper::computeScaleFromReducedFractionDenominator($denominator);

        if ($scale === null) {
            throw RoundingNecessaryException::decimalDivisionNotExact();
        }

        return $this->dividedBy($that, $scale)->strippedOfTrailingZeros();
    }

    /**
     * Returns this number exponentiated to the given value.
     *
     * The result has a scale of `$this->scale * $exponent`.
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

        return new self(CalculatorRegistry::get()->pow($this->value, $exponent), $this->scale * $exponent);
    }

    /**
     * Returns the quotient of the division of this number by the given one.
     *
     * The quotient has a scale of `0`.
     *
     * Examples:
     *
     * - `7.5` quotient `3` returns `2`
     * - `7.5` quotient `-3` returns `-2`
     * - `-7.5` quotient `3` returns `-2`
     * - `-7.5` quotient `-3` returns `2`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function quotient(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        $p = $this->valueWithMinScale($that->scale);
        $q = $that->valueWithMinScale($this->scale);

        $quotient = CalculatorRegistry::get()->divQ($p, $q);

        return new self($quotient, 0);
    }

    /**
     * Returns the remainder of the division of this number by the given one.
     *
     * The remainder has a scale of `max($this->scale, $that->scale)`.
     * The remainder, when non-zero, has the same sign as the dividend.
     *
     * Examples:
     *
     * - `7.5` remainder `3` returns `1.5`
     * - `7.5` remainder `-3` returns `1.5`
     * - `-7.5` remainder `3` returns `-1.5`
     * - `-7.5` remainder `-3` returns `-1.5`
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
     *
     * @pure
     */
    public function remainder(BigNumber|int|string $that): self
    {
        $that = self::of($that);

        if ($that->isZero()) {
            throw DivisionByZeroException::divisionByZero();
        }

        $p = $this->valueWithMinScale($that->scale);
        $q = $that->valueWithMinScale($this->scale);

        $remainder = CalculatorRegistry::get()->divR($p, $q);

        $scale = max($this->scale, $that->scale);

        return new self($remainder, $scale);
    }

    /**
     * Returns the quotient and remainder of the division of this number by the given one.
     *
     * The quotient has a scale of `0`, and the remainder has a scale of `max($this->scale, $that->scale)`.
     *
     * Examples:
     *
     * - `7.5` quotientAndRemainder `3` returns [`2`, `1.5`]
     * - `7.5` quotientAndRemainder `-3` returns [`-2`, `1.5`]
     * - `-7.5` quotientAndRemainder `3` returns [`-2`, `-1.5`]
     * - `-7.5` quotientAndRemainder `-3` returns [`2`, `-1.5`]
     *
     * @param BigNumber|int|string $that The divisor. Must be convertible to a BigDecimal.
     *
     * @throws DivisionByZeroException If the divisor is zero.
     * @throws MathException           If the divisor is not valid, or is not convertible to a BigDecimal.
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

        $p = $this->valueWithMinScale($that->scale);
        $q = $that->valueWithMinScale($this->scale);

        [$quotient, $remainder] = CalculatorRegistry::get()->divQR($p, $q);

        $scale = max($this->scale, $that->scale);

        $quotient = new self($quotient, 0);
        $remainder = new self($remainder, $scale);

        return [$quotient, $remainder];
    }

    /**
     * Returns the square root of this number, rounded to the given scale according to the given rounding mode.
     *
     * @param non-negative-int $scale        The target scale. Must be non-negative.
     * @param RoundingMode     $roundingMode An optional rounding mode, defaults to Unnecessary.
     *
     * @throws InvalidArgumentException   If the scale is negative.
     * @throws NegativeNumberException    If this number is negative.
     * @throws RoundingNecessaryException If RoundingMode::Unnecessary is used and the result cannot be represented
     *                                    exactly at the given scale.
     *
     * @pure
     */
    public function sqrt(int $scale, RoundingMode $roundingMode = RoundingMode::Unnecessary): self
    {
        if ($scale < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeScale();
        }

        if ($this->isZero()) {
            return new self('0', $scale);
        }

        if ($this->isNegative()) {
            throw NegativeNumberException::squareRootOfNegativeNumber();
        }

        $value = $this->value;
        $inputScale = $this->scale;

        if ($inputScale % 2 !== 0) {
            $value .= '0';
            ++$inputScale;
        }

        $calculator = CalculatorRegistry::get();

        // Keep one extra digit for rounding.
        $intermediateScale = max($scale, intdiv($inputScale, 2)) + 1;
        $value .= str_repeat('0', 2 * $intermediateScale - $inputScale);

        $sqrt = $calculator->sqrt($value);
        $isExact = $calculator->mul($sqrt, $sqrt) === $value;

        if (!$isExact) {
            if ($roundingMode === RoundingMode::Unnecessary) {
                throw RoundingNecessaryException::decimalSquareRootNotExact();
            }

            // Non-perfect-square sqrt is irrational, so the true value is strictly above this sqrt floor.
            // Add one at the intermediate scale to guarantee Up/Ceiling round up at the target scale.
            if (in_array($roundingMode, [RoundingMode::Up, RoundingMode::Ceiling], true)) {
                $sqrt = $calculator->add($sqrt, '1');
            }

            // Irrational sqrt cannot land exactly on a midpoint; treat tie-to-down modes as HalfUp.
            elseif (in_array($roundingMode, [RoundingMode::HalfDown, RoundingMode::HalfEven, RoundingMode::HalfFloor], true)) {
                $roundingMode = RoundingMode::HalfUp;
            }
        }

        $scaled = DecimalHelper::scale($sqrt, $intermediateScale, $scale, $roundingMode);

        if ($scaled === null) {
            throw RoundingNecessaryException::decimalSquareRootScaleTooSmall();
        }

        return new self($scaled, $scale);
    }

    /**
     * Returns a copy of this BigDecimal with the decimal point moved to the left by the given number of places.
     *
     * @pure
     */
    public function withPointMovedLeft(int $places): self
    {
        if ($places === 0) {
            return $this;
        }

        if ($places < 0) {
            return $this->withPointMovedRight(-$places);
        }

        return new self($this->value, $this->scale + $places);
    }

    /**
     * Returns a copy of this BigDecimal with the decimal point moved to the right by the given number of places.
     *
     * @pure
     */
    public function withPointMovedRight(int $places): self
    {
        if ($places === 0) {
            return $this;
        }

        if ($places < 0) {
            return $this->withPointMovedLeft(-$places);
        }

        $value = $this->value;
        $scale = $this->scale - $places;

        if ($scale < 0) {
            if ($value !== '0') {
                $value .= str_repeat('0', -$scale);
            }

            $scale = 0;
        }

        return new self($value, $scale);
    }

    /**
     * Returns a copy of this BigDecimal with any trailing zeros removed from the fractional part.
     *
     * @pure
     */
    public function strippedOfTrailingZeros(): self
    {
        if ($this->scale === 0) {
            return $this;
        }

        $trimmedValue = mb_rtrim($this->value, '0');

        if ($trimmedValue === '') {
            return self::zero();
        }

        $trimmableZeros = mb_strlen($this->value) - mb_strlen($trimmedValue);

        if ($trimmableZeros === 0) {
            return $this;
        }

        if ($trimmableZeros > $this->scale) {
            $trimmableZeros = $this->scale;
        }

        $value = mb_substr($this->value, 0, -$trimmableZeros);

        /** @var non-negative-int $scale */
        $scale = $this->scale - $trimmableZeros;

        return new self($value, $scale);
    }

    #[Override()]
    public function negated(): static
    {
        return new self(CalculatorRegistry::get()->neg($this->value), $this->scale);
    }

    #[Override()]
    public function compareTo(BigNumber|int|string $that): int
    {
        $that = BigNumber::of($that);

        if ($that instanceof BigInteger) {
            $that = $that->toBigDecimal();
        }

        if ($that instanceof self) {
            [$a, $b] = $this->scaleValues($this, $that);

            return CalculatorRegistry::get()->cmp($a, $b);
        }

        return -$that->compareTo($this);
    }

    #[Override()]
    public function getSign(): int
    {
        return ($this->value === '0') ? 0 : (($this->value[0] === '-') ? -1 : 1);
    }

    /**
     * @pure
     */
    public function getUnscaledValue(): BigInteger
    {
        /** @var numeric-string $value */
        $value = $this->value;

        return self::newBigInteger($value);
    }

    /**
     * @return non-negative-int
     *
     * @pure
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * Returns the number of significant digits in the number.
     *
     * This is the number of digits in the unscaled value of the number.
     * The sign has no impact on the result.
     *
     * Examples:
     *   0 => 1
     *   0.0 => 1
     *   123 => 3
     *   123.456 => 6
     *   0.00123 => 3
     *   0.0012300 => 5
     *
     * @return positive-int
     *
     * @pure
     */
    public function getPrecision(): int
    {
        $length = mb_strlen($this->value);

        if ($length === 0) {
            return 1;
        }

        if ($this->value[0] !== '-') {
            return $length;
        }

        return ($length > 1) ? $length - 1 : 1;
    }

    /**
     * Returns whether this decimal number has a non-zero fractional part.
     *
     * @pure
     */
    public function hasNonZeroFractionalPart(): bool
    {
        if ($this->scale === 0) {
            return false;
        }

        $value = DecimalHelper::padUnscaledValue($this->value, $this->scale);

        return mb_substr($value, -$this->scale) !== str_repeat('0', $this->scale);
    }

    #[Override()]
    public function toBigInteger(): BigInteger
    {
        $value = DecimalHelper::tryScaleExactly($this->value, $this->scale, 0);

        if ($value !== null) {
            /** @var numeric-string $value */
            return self::newBigInteger($value);
        }

        throw RoundingNecessaryException::decimalNotConvertibleToInteger();
    }

    #[Override()]
    public function toBigDecimal(): self
    {
        return $this;
    }

    #[Override()]
    public function toBigRational(): BigRational
    {
        /** @var numeric-string $numeratorValue */
        $numeratorValue = $this->value;
        $numerator = self::newBigInteger($numeratorValue);

        $denominatorValue = '1'.str_repeat('0', $this->scale);

        /** @phpstan-ignore-next-line argument.type */
        $denominator = self::newBigInteger($denominatorValue);

        return self::newBigRational($numerator, $denominator, false, true);
    }

    #[Override()]
    public function toScale(int $scale, RoundingMode $roundingMode = RoundingMode::Unnecessary): self
    {
        if ($scale < 0) { // @phpstan-ignore smaller.alwaysFalse
            throw InvalidArgumentException::negativeScale();
        }

        if ($scale === $this->scale) {
            return $this;
        }

        $value = DecimalHelper::scale($this->value, $this->scale, $scale, $roundingMode);

        if ($value === null) {
            throw RoundingNecessaryException::decimalScaleTooSmall();
        }

        return new self($value, $scale);
    }

    #[Override()]
    public function toInt(): int
    {
        return $this->toBigInteger()->toInt();
    }

    #[Override()]
    public function toFloat(): float
    {
        return (float) $this->toString();
    }

    /**
     * @return numeric-string
     */
    #[Override()]
    public function toString(): string
    {
        if ($this->scale === 0) {
            /** @phpstan-ignore return.type */
            return $this->value;
        }

        $value = DecimalHelper::padUnscaledValue($this->value, $this->scale);

        /** @phpstan-ignore return.type */
        return mb_substr($value, 0, -$this->scale).'.'.mb_substr($value, -$this->scale);
    }

    #[Override()]
    protected static function from(BigNumber $number): static
    {
        return $number->toBigDecimal();
    }

    /**
     * Puts the internal values of the given decimal numbers on the same scale.
     *
     * @return array{string, string} The scaled integer values of $x and $y.
     *
     * @pure
     */
    private function scaleValues(self $x, self $y): array
    {
        $a = $x->value;
        $b = $y->value;

        if ($b !== '0' && $x->scale > $y->scale) {
            $b .= str_repeat('0', $x->scale - $y->scale);
        } elseif ($a !== '0' && $x->scale < $y->scale) {
            $a .= str_repeat('0', $y->scale - $x->scale);
        }

        return [$a, $b];
    }

    /**
     * @pure
     */
    private function valueWithMinScale(int $scale): string
    {
        $value = $this->value;

        if ($this->value !== '0' && $scale > $this->scale) {
            $value .= str_repeat('0', $scale - $this->scale);
        }

        return $value;
    }

    /**
     * @pure
     */
    private function isOneScaleZero(): bool
    {
        return $this->value === '1' && $this->scale === 0;
    }
}
