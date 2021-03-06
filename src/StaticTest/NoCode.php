<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\StaticTest;

use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

final class NoCode
{
    /**
     * @psalm-suppress UndefinedAttributeClass
     * @psalm-return never-return
     */
    #[NoReturn]
    public static function here(): void
    {
        throw new RuntimeException('???');
    }
}
