<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Math\BigInteger;
use Cline\Math\BigNumber;
use Cline\Math\BigRational;
use Cline\Math\Exception\DivisionByZeroException;
use Cline\Math\Exception\IntegerOverflowException;
use Cline\Math\Exception\InvalidArgumentException;
use Cline\Math\Exception\NumberFormatException;
use Cline\Math\Exception\RoundingNecessaryException;
use Cline\Math\RoundingMode;

test('of fraction', function (int|string $numerator, int|string $denominator, string $expected): void {
    $rational = BigRational::ofFraction($numerator, $denominator);
    self::assertBigRationalEquals($expected, $rational);
})->with('providerOfFraction');
dataset('providerOfFraction', fn (): array => [
    ['7', 1, '7'],
    ['7', -1, '-7'],
    [7, 36, '7/36'],
    [7, -36, '-7/36'],
    ['-9', -15, '3/5'],
    [1_134_550, '34482098458475894798273810032245500', '22691/689641969169517895965476200644910'],
    [-899_340_012, '38742976492480578498793720873435125', '-99926668/4304775165831175388754857874826125'],
    ['5858937927498353480379287328794735400', 892_320_015, '390595861833223565358619155252982360/59488001'],
    ['283783947983740928034872902384095350044304', -1_122_233_344, '-17736496748983808002179556399005959377769/70139584'],
    ['-98765432109876543210', '12345678901234567890', '-109739369/13717421'],
    ['9095422003440195222055833233441122284005', '-9954384195559284034403958782783723000200', '-1819084400688039044411166646688224456801/1990876839111856806880791756556744600040'],
]);
test('of fraction with zero denominator', function (): void {
    $this->expectException(DivisionByZeroException::class);
    $this->expectExceptionMessageExact('The denominator of a rational number must not be zero.');

    BigRational::ofFraction(1, 0);
});
test('of', function (string $string, string $expected): void {
    $rational = BigRational::of($string);
    self::assertBigRationalEquals($expected, $rational);
})->with('providerOf');
test('of nullable with valid input behaves like of', function (string $string, string $expected): void {
    $rational = BigRational::ofNullable($string);
    self::assertBigRationalEquals($expected, $rational);
})->with('providerOf');
test('of nullable with null input', function (): void {
    self::assertNull(BigRational::ofNullable(null));
});
dataset('providerOf', fn (): array => [
    ['0', '0'],
    ['1', '1'],
    ['-1', '-1'],
    ['0/123456', '0'],
    ['-0/123456', '0'],
    ['-1/123456', '-1/123456'],
    ['4/6', '2/3'],
    ['-4/6', '-2/3'],
    ['123/456', '41/152'],
    ['-234/567', '-26/63'],
    ['1.125', '9/8'],
    ['123/456', '41/152'],
    ['+123/456', '41/152'],
    ['-2345/6789', '-2345/6789'],
    ['123456', '123456'],
    ['-1234567', '-1234567'],
    ['-0/123', '0'],
    ['-1234567890987654321012345678909876543210/9999', '-137174210109739369001371742101097393690/1111'],
    ['-1234567890987654321012345678909876543210/12345', '-82304526065843621400823045260658436214/823'],
    ['489798742123504998877665/387590928349859112233445', '32653249474900333258511/25839395223323940815563'],
    ['-395651984391591565172038784/445108482440540510818543632', '-8/9'],
    ['123e4', '1230000'],
    ['1.125', '9/8'],
]);
test('of with zero denominator', function (): void {
    $this->expectException(DivisionByZeroException::class);
    $this->expectExceptionMessageExact('The denominator of a rational number must not be zero.');

    BigRational::of('2/0');
});
test('of invalid string', function (string $string): void {
    $this->expectException(NumberFormatException::class);
    $this->expectExceptionMessageExact(sprintf('Value "%s" does not represent a valid number.', $string));

    BigRational::of($string);
})->with('providerOfInvalidString');
dataset('providerOfInvalidString', fn (): array => [
    ['123/-456'],
    ['1e4/2'],
    ['1.2/3'],
    ['1e2/3'],
    [' 1/2'],
    ['1/2 '],
    ['+'],
    ['-'],
    ['/'],
]);
test('zero', function (): void {
    self::assertBigRationalEquals('0', BigRational::zero());
    self::assertSame(BigRational::zero(), BigRational::zero());
});
test('one', function (): void {
    self::assertBigRationalEquals('1', BigRational::one());
    self::assertSame(BigRational::one(), BigRational::one());
});
test('ten', function (): void {
    self::assertBigRationalEquals('10', BigRational::ten());
    self::assertSame(BigRational::ten(), BigRational::ten());
});
test('min', function (array $values, string $min): void {
    self::assertBigRationalEquals($min, BigRational::min(...$values));
})->with('providerMin');
dataset('providerMin', fn (): array => [
    [['1/2', '1/4', '1/3'], '1/4'],
    [['1/2', '0.1', '1/3'], '1/10'],
    [['-0.25', '-0.3', '-1/8', '123456789123456789123456789', '2e25'], '-3/10'],
    [['1e30', '123456789123456789123456789/3', '2e26'], '41152263041152263041152263'],
]);
test('max', function (array $values, string $max): void {
    self::assertBigRationalEquals($max, BigRational::max(...$values));
})->with('providerMax');
dataset('providerMax', fn (): array => [
    [['-5532146515641651651321321064580/32453', '-1/2', '-1/99'], '-1/99'],
    [['1e-30', '123456789123456789123456789/2', '2e25'], '123456789123456789123456789/2'],
    [['999/1000', '1'], '1'],
    [[0, '0.9', '-1.00'], '9/10'],
    [[0, '0.01', -1, '-1.2'], '1/100'],
    [['1e-30', '15185185062185185062185185048/123', '2e25'], '15185185062185185062185185048/123'],
    [['1e-30', '15185185062185185062185185048/123', '2e26'], '200000000000000000000000000'],
]);
test('sum', function (array $values, string $sum): void {
    self::assertBigRationalEquals($sum, BigRational::sum(...$values));
})->with('providerSum');
dataset('providerSum', fn (): array => [
    [['-5532146515641651651321321064580/32453', '-1/2', '-1/99'], '-1095365010097047026961621574064593/6425694'],
    [['1e-30', '123456789123456789123456789/2', '2e25'], '81728394561728394561728394500000000000000000000000000001/1000000000000000000000000000000'],
    [['999/1000', '1'], '1999/1000'],
    [[0, '0.9', '-1.00'], '-1/10'],
    [[0, '0.01', -1, '-1.2'], '-219/100'],
    [['1e-30', '15185185062185185062185185048/123', '2e25'], '17645185062185185062185185048000000000000000000000000000123/123000000000000000000000000000000'],
    [['1e-30', '15185185062185185062185185048/123', '2e26'], '39785185062185185062185185048000000000000000000000000000123/123000000000000000000000000000000'],
]);
test('get integral and fractional part', function (string $rational, string $integralPart, string $fractionalPart): void {
    $r = BigRational::of($rational);

    self::assertBigIntegerEquals($integralPart, $r->getIntegralPart());
    self::assertBigRationalEquals($fractionalPart, $r->getFractionalPart());

    self::assertTrue($r->isEqualTo($r->getFractionalPart()->plus($r->getIntegralPart())));
})->with('providerGetIntegralAndFractionalPart');
dataset('providerGetIntegralAndFractionalPart', fn (): array => [
    ['7/3', '2', '1/3'],
    ['-7/3', '-2', '-1/3'],
    ['3/4', '0', '3/4'],
    ['-3/4', '0', '-3/4'],
    ['22/7', '3', '1/7'],
    ['-22/7', '-3', '-1/7'],
    ['1000/3', '333', '1/3'],
    ['-1000/3', '-333', '-1/3'],
    ['895/400', '2', '19/80'],
    ['-2.5', '-2', '-1/2'],
    ['-5/2', '-2', '-1/2'],
    ['0', '0', '0'],
    ['1', '1', '0'],
    ['-1', '-1', '0'],
    ['123456789012345678901234567889/7', '17636684144620811271604938269', '6/7'],
    ['123456789012345678901234567890/7', '17636684144620811271604938270', '0'],
    ['123456789012345678901234567891/7', '17636684144620811271604938270', '1/7'],
    ['1000000000000000000000/3', '333333333333333333333', '1/3'],
    ['-999999999999999999999/7', '-142857142857142857142', '-5/7'],
]);
test('plus', function (string $rational, BigNumber|int|string $plus, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->plus($plus));
})->with('providerPlus');
dataset('providerPlus', fn (): array => [
    ['123/456', 1, '193/152'],
    ['123/456', BigInteger::of(2), '345/152'],
    ['123/456', BigRational::ofFraction(2, 3), '427/456'],
    ['234/567', '123/28', '173/36'],
    ['-1234567890123456789/497', '79394345/109859892', '-135629495075630790047217323/54600366324'],
    ['-1234567890123456789/999', '-98765/43210', '-1185459522938548144865/959262'],
    ['123/456789123456789123456789', '-987/654321987654321', '-7156362932878877148736020/4744240749192401332533400050303375163'],
]);
test('minus', function (string $rational, string $minus, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->minus($minus));
})->with('providerMinus');
dataset('providerMinus', fn (): array => [
    ['123/456', '1', '-111/152'],
    ['234/567', '123/28', '-1003/252'],
    ['-1234567890123456789/497', '79394345/109859892', '-135629495075630868965196253/54600366324'],
    ['-1234567890123456789/999', '-98765/43210', '-1185459522938543759699/959262'],
    ['123/456789123456789123456789', '-987/654321987654321', '7156362935433848719576702/4744240749192401332533400050303375163'],
]);
test('multiplied by', function (string $rational, string $minus, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->multipliedBy($minus));
})->with('providerMultipliedBy');
dataset('providerMultipliedBy', fn (): array => [
    ['123/456', '1', '41/152'],
    ['123/456', '2', '41/76'],
    ['123/456', '1/2', '41/304'],
    ['123/456', '2/3', '41/228'],
    ['-123/456', '2/3', '-41/228'],
    ['123/456', '-2/3', '-41/228'],
    ['489798742123504/387590928349859', '324893948394/23609901123', '53044215748973274183484192/3050327831503982846997219'],
]);
test('divided by', function (string $rational, string $minus, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->dividedBy($minus));
})->with('providerDividedBy');
dataset('providerDividedBy', fn (): array => [
    ['123/456', '1', '41/152'],
    ['123/456', '2', '41/304'],
    ['123/456', '1/2', '41/76'],
    ['123/456', '2/3', '123/304'],
    ['-123/456', '2/3', '-123/304'],
    ['123/456', '-2/3', '-123/304'],
    ['489798742123504/387590928349859', '324893948394/23609901123', '1927349978617617415715832/20987657845546940253862741'],
]);
test('divided by zero', function (): void {
    $number = BigRational::ofFraction(1, 2);
    $this->expectException(DivisionByZeroException::class);
    $this->expectExceptionMessageExact('Division by zero.');

    $number->dividedBy(0);
});
test('power', function (string $number, int $exponent, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($number)->power($exponent));
})->with('providerPower');
dataset('providerPower', fn (): array => [
    ['-3',   0, '1'],
    ['-2/3', 0, '1'],
    ['-1/2', 0, '1'],
    ['0',    0, '1'],
    ['1/3',  0, '1'],
    ['2/3',  0, '1'],
    ['3/2',  0, '1'],

    ['-3/2', 1, '-3/2'],
    ['-2/3', 1, '-2/3'],
    ['-1/3', 1, '-1/3'],
    ['0',    1, '0'],
    ['1/3',  1, '1/3'],
    ['2/3',  1, '2/3'],
    ['3/2',  1, '3/2'],

    ['-3/4', 2, '9/16'],
    ['-2/3', 2, '4/9'],
    ['-1/2', 2, '1/4'],
    ['0',    2, '0'],
    ['1/2',  2, '1/4'],
    ['2/3',  2, '4/9'],
    ['3/4',  2, '9/16'],

    ['-3/4', 3, '-27/64'],
    ['-2/3', 3, '-8/27'],
    ['-1/2', 3, '-1/8'],
    ['0',    3, '0'],
    ['1/2',  3, '1/8'],
    ['2/3',  3, '8/27'],
    ['3/4',  3, '27/64'],

    ['0', 1_000_000, '0'],
    ['1', 1_000_000, '1'],
    ['1', -1_000_000, '1'],

    ['-2/3', 99, '-633825300114114700748351602688/171792506910670443678820376588540424234035840667'],
    ['-2/3', 100, '1267650600228229401496703205376/515377520732011331036461129765621272702107522001'],

    ['-123/33', 25, '-20873554875923477449109855954682643681001/108347059433883722041830251'],
    ['123/33', 26, '855815749912862575413504094141988390921041/1191817653772720942460132761'],

    ['-123456789/2', 8, '53965948844821664748141453212125737955899777414752273389058576481/256'],
    ['9876543210/3', 7, '4191659474105327353382483648587366147848521700884465442218430000000'],

    // Negative exponents
    ['1/2',  -1, '2'],
    ['2/3',  -1, '3/2'],
    ['-3/4', -1, '-4/3'],
    ['1/3',  -1, '3'],
    ['5',    -1, '1/5'],

    ['2/3',  -2, '9/4'],
    ['-3/4', -2, '16/9'],
    ['1/2',  -2, '4'],

    ['2/3',  -3, '27/8'],
    ['-2/3', -3, '-27/8'],
    ['-1/2', -3, '-8'],

    ['-2/3', -99, '-171792506910670443678820376588540424234035840667/633825300114114700748351602688'],
    ['-2/3', -100, '515377520732011331036461129765621272702107522001/1267650600228229401496703205376'],
]);
test('power with zero base and negative exponent', function (int $exponent): void {
    $zero = BigRational::zero();

    $this->expectException(DivisionByZeroException::class);
    $this->expectExceptionMessageExact('The reciprocal of zero is undefined.');

    $zero->power($exponent);
})->with('providerPowerWithZeroBaseAndNegativeExponent');
dataset('providerPowerWithZeroBaseAndNegativeExponent', fn (): array => [
    [-1],
    [-2],
    [-100],
]);
test('clamp', function (string $number, BigNumber|int|string $min, BigNumber|int|string $max, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($number)->clamp($min, $max));
})->with('providerClamp');
dataset('providerClamp', fn (): array => [
    ['1/2', '1/4', '3/4', '1/2'],   // within range
    ['1/8', '1/4', '3/4', '1/4'],   // below min
    ['7/8', '1/4', '3/4', '3/4'],   // above max
    ['1/4', '1/4', '3/4', '1/4'],   // equals min
    ['3/4', '1/4', '3/4', '3/4'],   // equals max
    ['-1/2', '-3/4', '-1/4', '-1/2'],  // negative range, within
    ['-1', '-3/4', '-1/4', '-3/4'],    // negative range, below min
    ['-1/8', '-3/4', '-1/4', '-1/4'],  // negative range, above max
    ['-3/4', '-3/4', '-1/4', '-3/4'],  // negative range, equals min
    ['-1/4', '-3/4', '-1/4', '-1/4'],  // negative range, equals max
    ['0', '-1/2', '1/2', '0'],         // zero within range
    ['2/3', 0, 1, '2/3'],              // int min/max
    ['3/2', '0.5', '1.0', '1'],
    ['5/4', BigRational::of('1/2'), BigRational::of('1'), '1'],  // BigRational min/max
]);
test('clamp with inverted bounds throws exception', function (): void {
    $number = BigRational::of('1/2');
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageExact('The minimum value must be less than or equal to the maximum value.');

    $number->clamp('3/4', '1/4');
});
test('reciprocal', function (string $rational, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->reciprocal());
})->with('providerReciprocal');
dataset('providerReciprocal', fn (): array => [
    ['1', '1'],
    ['2', '1/2'],
    ['1/2', '2'],
    ['123/456', '152/41'],
    ['-234/567', '-63/26'],
    ['489798742123504998877665/387590928349859112233445', '25839395223323940815563/32653249474900333258511'],
]);
test('reciprocal of zero throws exception', function (): void {
    $number = BigRational::ofFraction(0, 2);

    $this->expectException(DivisionByZeroException::class);
    $this->expectExceptionMessageExact('The reciprocal of zero is undefined.');

    $number->reciprocal();
});
test('abs', function (string $rational, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->abs());
})->with('providerAbs');
dataset('providerAbs', fn (): array => [
    ['0', '0'],
    ['1', '1'],
    ['-1', '1'],
    ['123/456', '41/152'],
    ['-234/567', '26/63'],
    ['-489798742123504998877665/387590928349859112233445', '32653249474900333258511/25839395223323940815563'],
]);
test('negated', function (string $rational, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->negated());
})->with('providerNegated');
dataset('providerNegated', fn (): array => [
    ['0', '0'],
    ['1', '-1'],
    ['-1', '1'],
    ['123/456', '-41/152'],
    ['-234/567', '26/63'],
    ['-489798742123504998877665/387590928349859112233445', '32653249474900333258511/25839395223323940815563'],
    ['489798742123504998877665/387590928349859112233445', '-32653249474900333258511/25839395223323940815563'],
]);
test('simplified', function (string $rational, string $expected): void {
    self::assertBigRationalEquals($expected, BigRational::of($rational)->simplified());
})->with('providerSimplified');
dataset('providerSimplified', fn (): array => [
    ['0', '0'],
    ['1', '1'],
    ['-1', '-1'],
    ['0/123456', '0'],
    ['-0/123456', '0'],
    ['-1/123456', '-1/123456'],
    ['4/6', '2/3'],
    ['-4/6', '-2/3'],
    ['123/456', '41/152'],
    ['-234/567', '-26/63'],
    ['489798742123504998877665/387590928349859112233445', '32653249474900333258511/25839395223323940815563'],
    ['-395651984391591565172038784/445108482440540510818543632', '-8/9'],
    ['1.125', '9/8'],
]);
test('compare to', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp, BigRational::of($a)->compareTo($b));
})->with('providerCompareTo');
test('is equal to', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp === 0, BigRational::of($a)->isEqualTo($b));
})->with('providerCompareTo');
test('is less than', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp < 0, BigRational::of($a)->isLessThan($b));
})->with('providerCompareTo');
test('is less than or equal to', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp <= 0, BigRational::of($a)->isLessThanOrEqualTo($b));
})->with('providerCompareTo');
test('is greater than', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp > 0, BigRational::of($a)->isGreaterThan($b));
})->with('providerCompareTo');
test('is greater than or equal to', function (string $a, int|string $b, int $cmp): void {
    self::assertSame($cmp >= 0, BigRational::of($a)->isGreaterThanOrEqualTo($b));
})->with('providerCompareTo');
dataset('providerCompareTo', fn (): array => [
    ['-1', '1/2', -1],
    ['1', '1/2', 1],
    ['1', '-1/2', 1],
    ['-1', '-1/2', -1],
    ['1/2', '1/2', 0],
    ['-1/2', '-1/2', 0],
    ['1/2', '2/4', 0],
    ['1/3', '122/369', 1],
    ['1/3', '123/369', 0],
    ['1/3', '124/369', -1],
    ['1/3', '123/368', -1],
    ['1/3', '123/370', 1],
    ['-1/3', '-122/369', -1],
    ['-1/3', '-123/369', 0],
    ['-1/3', '-124/369', 1],
    ['-1/3', '-123/368', 1],
    ['-1/3', '-123/370', -1],
    ['999999999999999999999999999999/1000000000000000000000000000000', '1', -1],
    ['1', '999999999999999999999999999999/1000000000000000000000000000000', 1],
    ['999999999999999999999999999999/1000000000000000000000000000000', '999/1000', 1],
    ['-999999999999999999999999999999/1000000000000000000000000000000', '-999/1000', -1],
    ['-999999999999999999999999999999/1000000000000000000000000000000', -1, 1],
    ['-999999999999999999999999999999/1000000000000000000000000000000', '-10e-1', 1],
    ['-999999999999999999999999999999/1000000000000000000000000000000', '-0.999999999999999999999999999999', 0],
    ['-999999999999999999999999999999/1000000000000000000000000000000', '-0.999999999999999999999999999998', -1],
]);
test('get sign', function (string $number, int $sign): void {
    self::assertSame($sign, BigRational::of($number)->getSign());
})->with('providerSign');
test('is zero', function (string $number, int $sign): void {
    self::assertSame($sign === 0, BigRational::of($number)->isZero());
})->with('providerSign');
test('is negative', function (string $number, int $sign): void {
    self::assertSame($sign < 0, BigRational::of($number)->isNegative());
})->with('providerSign');
test('is negative or zero', function (string $number, int $sign): void {
    self::assertSame($sign <= 0, BigRational::of($number)->isNegativeOrZero());
})->with('providerSign');
test('is positive', function (string $number, int $sign): void {
    self::assertSame($sign > 0, BigRational::of($number)->isPositive());
})->with('providerSign');
test('is positive or zero', function (string $number, int $sign): void {
    self::assertSame($sign >= 0, BigRational::of($number)->isPositiveOrZero());
})->with('providerSign');
dataset('providerSign', fn (): array => [
    ['0', 0],
    ['-0', 0],
    ['-2', -1],
    ['2', 1],
    ['0/123456', 0],
    ['-0/123456', 0],
    ['-1/23784738479837498273817307948739875387498374983749837984739874983749834384938493284934', -1],
    ['1/3478378924784729749873298479832792487498789012890843098490820480938092849032809480932840', 1],
]);
test('to big integer', function (string $number, ?string $expected): void {
    $number = BigRational::of($number);

    if ($expected === null) {
        $this->expectException(RoundingNecessaryException::class);
        $this->expectExceptionMessageExact('This rational number cannot be represented as an integer without rounding.');
    }

    $actual = $number->toBigInteger();

    if ($expected === null) {
        return;
    }

    self::assertBigIntegerEquals($expected, $actual);
})->with('providerToBigInteger');
dataset('providerToBigInteger', fn (): array => [
    ['0', '0'],
    ['1', '1'],
    ['-1', '-1'],
    ['1/2', null],
    ['-1/2', null],
    ['2/2', '1'],
    ['-2/2', '-1'],
    ['9999999999999999999999999999999999999998', '9999999999999999999999999999999999999998'],
    ['-9999999999999999999999999999999999999998', '-9999999999999999999999999999999999999998'],
    ['9999999999999999999999999999999999999998/2', '4999999999999999999999999999999999999999'],
    ['-9999999999999999999999999999999999999998/2', '-4999999999999999999999999999999999999999'],
    ['9999999999999999999999999999999999999998/3', null],
    ['-9999999999999999999999999999999999999998/3', null],
]);
test('to big decimal', function (string $number, ?string $expected): void {
    if ($expected === null) {
        $this->expectException(RoundingNecessaryException::class);
        $this->expectExceptionMessageExact('This rational number has a non-terminating decimal expansion and cannot be represented as a decimal without rounding.');
    }

    $actual = BigRational::of($number)->toBigDecimal();

    if ($expected === null) {
        return;
    }

    self::assertBigDecimalEquals($expected, $actual);
})->with('providerToBigDecimal');
dataset('providerToBigDecimal', function () {
    $tests = [
        ['1', '1'],
        ['1/2', '0.5'],
        ['2/2', '1'],
        ['3/2', '1.5'],
        ['1/3', null],
        ['2/3', null],
        ['3/3', '1'],
        ['4/3', null],
        ['1/4', '0.25'],
        ['2/4', '0.5'],
        ['3/4', '0.75'],
        ['4/4', '1'],
        ['5/4', '1.25'],
        ['1/5', '0.2'],
        ['2/5', '0.4'],
        ['1/6', null],
        ['2/6', null],
        ['3/6', '0.5'],
        ['4/6', null],
        ['5/6', null],
        ['6/6', '1'],
        ['7/6', null],
        ['1/7', null],
        ['2/7', null],
        ['6/7', null],
        ['7/7', '1'],
        ['14/7', '2'],
        ['15/7', null],
        ['1/8', '0.125'],
        ['2/8', '0.25'],
        ['3/8', '0.375'],
        ['4/8', '0.5'],
        ['5/8', '0.625'],
        ['6/8', '0.75'],
        ['7/8', '0.875'],
        ['8/8', '1'],
        ['17/8', '2.125'],
        ['1/9', null],
        ['2/9', null],
        ['9/9', '1'],
        ['10/9', null],
        ['17/9', null],
        ['18/9', '2'],
        ['19/9', null],
        ['1/10', '0.1'],
        ['10/2', '5'],
        ['10/20', '0.5'],
        ['100/20', '5'],
        ['100/2', '50'],
        ['8/360', null],
        ['9/360', '0.025'],
        ['10/360', null],
        ['17/360', null],
        ['18/360', '0.05'],
        ['19/360', null],
        ['1/500', '0.002'],
        ['1/600', null],
        ['1/400', '0.0025'],
        ['1/800', '0.00125'],
        ['1/1600', '0.000625'],
        ['2/1600', '0.00125'],
        ['3/1600', '0.001875'],
        ['4/1600', '0.0025'],
        ['5/1600', '0.003125'],
        ['669433117850846623944075755499/3723692145740642445161938667297363281250', '0.0000000001797767086134066979625344023536861184'],
        ['669433117850846623944075755498/3723692145740642445161938667297363281250', null],
        ['669433117850846623944075755499/3723692145740642445161938667297363281251', null],

        ['438002367448868006942618029488152554057431119072727/9', '48666929716540889660290892165350283784159013230303'],
        ['438002367448868006942618029488152554057431119072728/9', null],

        ['1278347892548908779/181664161764972047166111224214546382427215576171875', '0.0000000000000000000000000000000070368744177664'],
        ['1278347892548908779/363328323529944094332222448429092764854431152343750', '0.0000000000000000000000000000000035184372088832'],
        ['1278347892548908778/363328323529944094332222448429092764854431152343750', null],
        ['1278347892548908779/363328323529944094332222448429092764854431152343751', null],

        ['1274512848871262052662/181119169279677131024612890541902743279933929443359375', null],
        ['1274512848871262052663/181119169279677131024612890541902743279933929443359375', '0.0000000000000000000000000000000070368744177664'],
        ['1274512848871262052664/181119169279677131024612890541902743279933929443359375', null],
    ];

    foreach ($tests as [$number, $expected]) {
        yield [$number, $expected];

        yield ['-'.$number, $expected === null ? null : '-'.$expected];
    }
});
test('to scale', function (string $number, int $scale, RoundingMode $roundingMode, string $expected): void {
    $number = BigRational::of($number);

    $expectedExceptionMessage = match ($expected) {
        'NON_EXACT' => 'This rational number has a non-terminating decimal expansion and cannot be represented as a decimal without rounding.',
        'SCALE_TOO_SMALL' => 'This rational number cannot be represented at the requested scale without rounding.',
        default => null,
    };

    if ($expectedExceptionMessage !== null) {
        $this->expectException(RoundingNecessaryException::class);
        $this->expectExceptionMessageExact($expectedExceptionMessage);
    }

    $actual = $number->toScale($scale, $roundingMode);

    if ($expectedExceptionMessage !== null) {
        return;
    }

    self::assertBigDecimalEquals($expected, $actual);
})->with('providerToScale');
test('to scale with negative scale', function (): void {
    $number = BigRational::of('1/2');

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageExact('The scale must not be negative.');

    $number->toScale(-1);
});
dataset('providerToScale', fn (): array => [
    ['1/8', 3, RoundingMode::Unnecessary, '0.125'],
    ['1/16', 3, RoundingMode::Unnecessary, 'SCALE_TOO_SMALL'],
    ['1/16', 3, RoundingMode::HalfDown, '0.062'],
    ['1/16', 3, RoundingMode::HalfUp, '0.063'],
    ['1/9', 30, RoundingMode::Down, '0.111111111111111111111111111111'],
    ['1/9', 30, RoundingMode::Up, '0.111111111111111111111111111112'],
    ['1/9', 100, RoundingMode::Unnecessary, 'NON_EXACT'],
]);
test('to int', function (int|string $rational, int $integer): void {
    self::assertSame($integer, BigRational::of($rational)->toInt());
})->with('providerToInt');
dataset('providerToInt', fn (): array => [
    [\PHP_INT_MAX, \PHP_INT_MAX],
    [\PHP_INT_MIN, \PHP_INT_MIN],
    [\PHP_INT_MAX.'0/10', \PHP_INT_MAX],
    [\PHP_INT_MIN.'0/10', \PHP_INT_MIN],
    ['246913578/2', 123_456_789],
    ['-246913578/2', -123_456_789],
    ['625/25', 25],
    ['-625/25', -25],
    ['0/3', 0],
    ['-0/3', 0],
]);
test('to int throws integer overflow exception', function (string $number): void {
    $rational = BigRational::of($number);

    $this->expectException(IntegerOverflowException::class);
    $this->expectExceptionMessageExact(sprintf('%s is out of range [%d, %d] and cannot be represented as an integer.', $number, \PHP_INT_MIN, \PHP_INT_MAX));

    $rational->toInt();
})->with('providerToIntThrowsIntegerOverflowException');
dataset('providerToIntThrowsIntegerOverflowException', fn (): array => [
    ['-999999999999999999999999999999'],
    ['9999999999999999999999999999999'],
]);
test('to int throws rounding necessary exception', function (string $number): void {
    $number = BigRational::of($number);

    $this->expectException(RoundingNecessaryException::class);
    $this->expectExceptionMessageExact('This rational number cannot be represented as an integer without rounding.');

    $number->toInt();
})->with('providerToIntThrowsRoundingNecessaryException');
dataset('providerToIntThrowsRoundingNecessaryException', fn (): array => [
    ['-9999999999999999999999999999999/2'],
    ['9999999999999999999999999999999/2'],
    ['1/2'],
    ['2/3'],
]);
test('to float', function (BigRational|string $value, float $expected): void {
    $actual = BigRational::of($value)->toFloat();

    self::assertFalse(is_nan($actual));

    if (is_infinite($expected) || $expected === 0.0) {
        self::assertSame($expected, $actual);
    } else {
        $ratio = $actual / $expected;

        $min = 1.0 - \PHP_FLOAT_EPSILON;
        $max = 1.0 + \PHP_FLOAT_EPSILON;

        self::assertTrue($ratio >= $min && $ratio <= $max, sprintf('%.20f != %.20f', $actual, $expected));
    }
})->with('providerToFloat');
dataset('providerToFloat', fn (): array => [
    ['0', 0.0],
    ['-0', 0.0],
    ['1.6', 1.6],
    ['-1.6', -1.6],
    ['1.23456789', 1.234_567_89],
    ['-1.23456789', -1.234_567_89],
    ['1000000000000000000000000000000000000000/3', (float) '3.333333333333333e+38'],
    ['-2/300000000000000000000000000000000000000', -6.666_666_666_666_666e-39],

    ['1e-100', 1e-100],
    ['-1e-100', -1e-100],
    ['1e-324', 1e-324],
    ['-1e-324', -1e-324],
    ['1e-325', 0.0],
    ['-1e-325', 0.0],
    ['1e-1000', 0.0],
    ['-1e-1000', 0.0],
    ['1.2345e-100', 1.234_5e-100],
    ['-1.2345e-100', -1.234_5e-100],
    ['1.2345e-1000', 0.0],
    ['-1.2345e-1000', 0.0],
    ['1e100', 1e100],
    ['-1e100', -1e100],
    ['1e308', 1e308],
    ['-1e308', -1e308],
    ['1e309', \INF],
    ['-1e309', -\INF],
    ['1e1000', \INF],
    ['-1e1000', -\INF],
    ['1.2345e100', 1.234_5e100],
    ['-1.2345e100', -1.234_5e100],
    ['1.2345e1000', \INF],
    ['-1.2345e1000', -\INF],

    [BigRational::ofFraction('1e15', BigInteger::of('1e15')->plus(1)), 0.999_999_999_999_999],
    [BigRational::ofFraction('1e15', BigInteger::of('1e15')->minus(1)), 1.000_000_000_000_001],
    [BigRational::ofFraction('-1e15', BigInteger::of('1e15')->plus(1)), -0.999_999_999_999_999],
    [BigRational::ofFraction('-1e15', BigInteger::of('1e15')->minus(1)), -1.000_000_000_000_001],
    [BigRational::ofFraction('1e1000', BigInteger::of('1e1000')->plus(1)), 1.0],
    [BigRational::ofFraction('1e1000', BigInteger::of('1e1000')->minus(1)), 1.0],
    [BigRational::ofFraction('-1e1000', BigInteger::of('1e1000')->plus(1)), -1.0],
    [BigRational::ofFraction('-1e1000', BigInteger::of('1e1000')->minus(1)), -1.0],
    [BigRational::ofFraction('1e1000', BigInteger::of('2.5e1001')->plus(1)), 0.04],
    [BigRational::ofFraction('1e1000', BigInteger::of('2.5e1001')->minus(1)), 0.04],
    [BigRational::ofFraction('-1e1000', BigInteger::of('2.5e1001')->plus(1)), -0.04],
    [BigRational::ofFraction('-1e1000', BigInteger::of('2.5e1001')->minus(1)), -0.04],
    [BigRational::ofFraction(BigInteger::of('1e1000')->plus(1), BigInteger::of('1e2000')->plus(2)), 0.0],
    [BigRational::ofFraction(BigInteger::of('-1e1000')->minus(1), BigInteger::of('1e2000')->plus(2)), 0.0],
    [BigRational::ofFraction(BigInteger::of('1.2345e9999')->plus(1), BigInteger::of('2.34e10123')->plus(2)), 5.275_641_025_641_025e-125],
    [BigRational::ofFraction(BigInteger::of('-1.2345e9999')->minus(1), BigInteger::of('2.34e10123')->plus(2)), -5.275_641_025_641_025e-125],
    [BigRational::ofFraction(BigInteger::of('1.2345e10123')->plus(3), BigInteger::of('2.34e9999')->plus(123_000)), 5.275_641_025_641_025e123],
    [BigRational::ofFraction(BigInteger::of('-1.2345e10123')->minus(3), BigInteger::of('2.34e9999')->plus(123_000)), -5.275_641_025_641_025e123],
    [BigRational::ofFraction(BigInteger::of('1e2000')->plus(1), BigInteger::of('1e1000')->plus(2)), \INF],
    [BigRational::ofFraction(BigInteger::of('-1e2000')->minus(1), BigInteger::of('1e1000')->plus(2)), -\INF],
    [BigRational::ofFraction(BigInteger::of('1e309'), 7), 1.428_571_428_571_428_6e308],
    [BigRational::ofFraction(BigInteger::of('-1e309'), 7), -1.428_571_428_571_428_6e308],
]);
test('to repeating decimal string', function (string $number, string $expected): void {
    self::assertSame($expected, BigRational::of($number)->toRepeatingDecimalString());
})->with('providerToRepeatingDecimalString');
dataset('providerToRepeatingDecimalString', fn (): array => [
    ['0/7', '0'],
    ['10/5', '2'],
    ['1/2', '0.5'],
    ['1/3', '0.(3)'],
    ['4/3', '1.(3)'],
    ['10/3', '3.(3)'],
    ['7/6', '1.1(6)'],
    ['22/7', '3.(142857)'],
    ['171/70', '2.4(428571)'],
    ['122200/99', '1234.(34)'],
    ['123/98', '1.2(551020408163265306122448979591836734693877)'],
    ['1234500000/99999', '12345.(12345)'],
    ['12345000000/99999', '123451.(23451)'],
    ['1/250', '0.004'],
    ['50/8', '6.25'],
    ['1/28', '0.03(571428)'],
    ['1/40', '0.025'],
    ['-1/28', '-0.03(571428)'],
    ['-1/3', '-0.(3)'],
    ['-1/30', '-0.0(3)'],
    ['-5/2', '-2.5'],
    ['-22/7', '-3.(142857)'],
    ['1/90', '0.0(1)'],
    ['1/12', '0.08(3)'],
]);
test('to string', function (string $numerator, string $denominator, string $expected): void {
    $bigRational = BigRational::ofFraction($numerator, $denominator);
    self::assertSame($expected, $bigRational->toString());
    self::assertSame($expected, (string) $bigRational);
})->with('providerToString');
dataset('providerToString', fn (): array => [
    ['-1', '1', '-1'],
    ['2', '1', '2'],
    ['1', '2', '1/2'],
    ['-1', '-2', '1/2'],
    ['1', '-2', '-1/2'],
    ['34327948737247817984738927598572389', '32565046546', '34327948737247817984738927598572389/32565046546'],
    ['34327948737247817984738927598572389', '-32565046546', '-34327948737247817984738927598572389/32565046546'],
    ['34327948737247817984738927598572389', '1', '34327948737247817984738927598572389'],
    ['34327948737247817984738927598572389', '-1', '-34327948737247817984738927598572389'],
]);
test('serialize', function (): void {
    $numerator = '-1234567890987654321012345678909876543210123456789';
    $denominator = '347827348278374374263874681238374983729873401984091287439827467286';

    $rational = BigRational::ofFraction($numerator, $denominator);

    self::assertBigRationalEquals(sprintf('%s/%s', $numerator, $denominator), unserialize(serialize($rational)));
});
test('direct call to unserialize', function (): void {
    $number = BigRational::ofFraction(1, 2);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageExact('__unserialize() is an internal function, it must not be called directly.');

    $number->__unserialize([]);
});
