<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use Psalm\Type\Atomic;
use Fp\Functional\Option\Option;
use Psalm\Storage\ClassLikeStorage;

final class Classlikes
{
    private function toFqClassName(string|Atomic\TNamedObject $classlike): string
    {
        return $classlike instanceof Atomic\TNamedObject
            ? $classlike->value
            : $classlike;
    }

    /**
     * @return Option<ClassLikeStorage>
     */
    public function getStorage(string|Atomic\TNamedObject $classlike): Option
    {
        return Option::fromNullable(
            PsalmToolkit::$codebase->classlikes->getStorageFor($this->toFqClassName($classlike))
        );
    }

    public function classExtends(string|Atomic\TNamedObject $classlike, string $possible_parent): bool
    {
        return PsalmToolkit::$codebase->classlikes->classExtends($this->toFqClassName($classlike), $possible_parent);
    }

    public function classImplements(string|Atomic\TNamedObject $classlike, string $interface): bool
    {
        return PsalmToolkit::$codebase->classlikes->classImplements($this->toFqClassName($classlike), $interface);
    }
}
