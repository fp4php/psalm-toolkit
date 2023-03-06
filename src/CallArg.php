<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit;

use PhpParser\Node\Arg;
use Psalm\CodeLocation;
use Psalm\Type\Union;

final class CallArg
{
    public function __construct(
        public readonly Arg $node,
        public readonly CodeLocation $location,
        public readonly Union $type,
    ) {}
}
