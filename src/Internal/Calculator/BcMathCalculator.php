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

use function bcadd;
use function bcdiv;
use function bcmod;
use function bcmul;
use function bcpow;
use function bcpowmod;
use function bcsqrt;
use function bcsub;

/**
 * Calculator implementation built around the bcmath library.
 *
 * @internal
 * @psalm-immutable
 */
final readonly class BcMathCalculator extends Calculator
{
    #[Override()]
    public function add(string $a, string $b): string
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bcadd($a, $b, 0);
    }

    #[Override()]
    public function sub(string $a, string $b): string
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bcsub($a, $b, 0);
    }

    #[Override()]
    public function mul(string $a, string $b): string
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bcmul($a, $b, 0);
    }

    #[Override()]
    public function divQ(string $a, string $b): string
    {
        return bcdiv($a, $b, 0);
    }

    #[Override()]
    public function divR(string $a, string $b): string
    {
        return bcmod($a, $b, 0);
    }

    #[Override()]
    public function divQR(string $a, string $b): array
    {
        $q = bcdiv($a, $b, 0);
        $r = bcmod($a, $b, 0);

        return [$q, $r];
    }

    #[Override()]
    public function pow(string $a, int $e): string
    {
        /** @var numeric-string $a */
        return bcpow($a, (string) $e, 0);
    }

    #[Override()]
    public function modPow(string $base, string $exp, string $mod): string
    {
        // normalize to Euclidean representative so modPow() stays consistent with mod()
        $base = $this->mod($base, $mod);

        return bcpowmod($base, $exp, $mod, 0);
    }

    #[Override()]
    public function sqrt(string $n): string
    {
        /** @var numeric-string $n */
        return bcsqrt($n, 0);
    }
}
