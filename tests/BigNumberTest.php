<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Math\BigDecimal;
use Cline\Math\BigInteger;
use Cline\Math\BigNumber;
use Cline\Math\BigRational;
use Cline\Math\Exception\RoundingNecessaryException;

test('min', function (array $values, string $expectedClass, string $expectedValue): void {
    $result = BigNumber::min(...$values);

    self::assertInstanceOf($expectedClass, $result);
    self::assertSame($expectedValue, $result->toString());
})->with('providerMin');
dataset('providerMin', fn (): array => [
    [['1', '1.0', '1/1'], BigInteger::class, '1'],
    [['1.0', '1', '1/1'], BigDecimal::class, '1.0'],
    [['1/1', '1.0', '1'], BigRational::class, '1'],
    [[-3, '-4.0', '-4/1'], BigDecimal::class, '-4.0'],
    [[-3, '-4/1', '-4.0'], BigRational::class, '-4'],
    [['2/3', '0.67', '0.6666666666666666666666666667'], BigRational::class, '2/3'],
]);
test('max', function (array $values, string $expectedClass, string $expectedValue): void {
    $result = BigNumber::max(...$values);

    self::assertInstanceOf($expectedClass, $result);
    self::assertSame($expectedValue, $result->toString());
})->with('providerMax');
dataset('providerMax', fn (): array => [
    [['1', '1.0', '1/1'], BigInteger::class, '1'],
    [['1.0', '1', '1/1'], BigDecimal::class, '1.0'],
    [['1/1', '1.0', '1'], BigRational::class, '1'],
    [[-3, '-3.0', '-3/1'], BigInteger::class, '-3'],
    [['1/2', '0.5', '0.50'], BigRational::class, '1/2'],
]);
test('sum', function (string $callingClass, array $values, string $expectedClass, string $expectedSum): void {
    $sum = $callingClass::sum(...$values);

    self::assertInstanceOf($expectedClass, $sum);
    self::assertSame($expectedSum, $sum->toString());
})->with('providerSum');
dataset('providerSum', fn (): array => [
    [BigNumber::class, [-1], BigInteger::class, '-1'],
    [BigNumber::class, [-1, '99'], BigInteger::class, '98'],
    [BigInteger::class, [-1, '99'], BigInteger::class, '98'],
    [BigDecimal::class, [-1, '99'], BigDecimal::class, '98'],
    [BigRational::class, [-1, '99'], BigRational::class, '98'],
    [BigNumber::class, [-1, '99', '-0.7'], BigDecimal::class, '97.3'],
    [BigDecimal::class, [-1, '99', '-0.7'], BigDecimal::class, '97.3'],
    [BigRational::class, [-1, '99', '-0.7'], BigRational::class, '973/10'],
    [BigNumber::class, [-1, '99', '-0.7', '3/2'], BigRational::class, '494/5'],
    [BigNumber::class, [-1, '3/2'], BigRational::class, '1/2'],
    [BigNumber::class, ['-0.5'], BigDecimal::class, '-0.5'],
    [BigNumber::class, ['-0.5', 1], BigDecimal::class, '0.5'],
    [BigNumber::class, ['-0.5', 1, '0.7'], BigDecimal::class, '1.2'],
    [BigNumber::class, ['-0.5', 1, '0.7', '47/7'], BigRational::class, '277/35'],
    [BigNumber::class, ['-1/9'], BigRational::class, '-1/9'],
    [BigNumber::class, ['-1/9', 123], BigRational::class, '1106/9'],
    [BigNumber::class, ['-1/9', 123, '8349.3771'], BigRational::class, '762503939/90000'],
    [BigNumber::class, ['-1/9', '8349.3771', 123], BigRational::class, '762503939/90000'],
]);
test('sum throws rounding necessary exception', function (string $callingClass, array $values, string $expectedExceptionMessage): void {
    $this->expectException(RoundingNecessaryException::class);
    $this->expectExceptionMessageExact($expectedExceptionMessage);

    $callingClass::sum(...$values);
})->with('providerSumThrowsRoundingNecessaryException');
dataset('providerSumThrowsRoundingNecessaryException', fn (): array => [
    [BigInteger::class, [1, '1.5'], 'This decimal number cannot be represented as an integer without rounding.'],
    [BigInteger::class, [1, '1/2'], 'This rational number cannot be represented as an integer without rounding.'],
    [BigDecimal::class, ['1.5', '1/3'], 'This rational number has a non-terminating decimal expansion and cannot be represented as a decimal without rounding.'],
]);
