<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use Psalm\Codebase;

final class PsalmToolkit
{
    public static Args $args;
    public static Types $types;
    public static Codebase $codebase;
    public static Classlikes $classlikes;
}
