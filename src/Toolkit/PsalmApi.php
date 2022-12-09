<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Psalm\Codebase;

final class PsalmApi
{
    public static Args $args;
    public static Types $types;
    public static Codebase $codebase;
    public static Classlikes $classlikes;
    public static Issue $issue;
}
