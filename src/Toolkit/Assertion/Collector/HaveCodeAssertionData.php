<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\PsalmToolkit\Toolkit\Assertion\AssertionData;
use Psalm\CodeLocation;
use Psalm\Type;

/**
 * @psalm-immutable
 */
final class HaveCodeAssertionData implements AssertionData
{
    public function __construct(
        public CodeLocation $code_location,
        public Type\Union $actual_return_type,
    ) {}
}
