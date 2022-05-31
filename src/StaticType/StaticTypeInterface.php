<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\StaticType;

/**
 * @template-covariant T
 */
interface StaticTypeInterface
{
    /**
     * @return StaticTypeInterface<T>
     */
    public function optional(): StaticTypeInterface;
}
