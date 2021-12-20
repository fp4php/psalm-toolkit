<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use Fp\Functional\Option\Option;
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

    /**
     * @param callable(Union): Option<Union> $ab
     * @return Option<CallArg>
     */
    public function flatMap(callable $ab): Option
    {
        return $ab($this->type)->map(fn($new_type) => new self($this->node, $this->location, $new_type));
    }
}
