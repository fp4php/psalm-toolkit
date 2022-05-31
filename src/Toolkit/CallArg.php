<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use PhpParser\Node\Arg;
use Psalm\CodeLocation;
use Psalm\Type\Union;

final class CallArg
{
    public function __construct(
        public Arg $node,
        public CodeLocation $location,
        public Union $type,
    ) { }
}
