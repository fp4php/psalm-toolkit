<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;

interface AssertionCollectorInterface
{
    /**
     * @return Option<Assertions>
     */
    public static function collect(Assertions $data, AssertionCollectingContext $context): Option;

    public static function isSupported(AssertionCollectingContext $context): bool;
}
