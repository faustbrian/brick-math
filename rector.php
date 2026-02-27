<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\Rector\Factory;
use Rector\CodeQuality\Rector\Assign\CombinedAssignRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\DeadCode\Rector\If_\ReduceAlwaysFalseIfOrRector;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;
use RectorLaravel\Rector\MethodCall\ContainerBindConcreteWithClosureOnlyRector;

return Factory::create(
    paths: [__DIR__.'/src', __DIR__.'/tests'],
    skip: [
        RemoveUnreachableStatementRector::class => [__DIR__.'/tests'],
        CombinedAssignRector::class => [__DIR__.'/src/BigInteger.php'],
        ReduceAlwaysFalseIfOrRector::class => [__DIR__.'/src/BigInteger.php'],
        ContainerBindConcreteWithClosureOnlyRector::class,
        NewlineBetweenClassLikeStmtsRector::class,
    ],
)->withParallel(timeoutSeconds: 300);
