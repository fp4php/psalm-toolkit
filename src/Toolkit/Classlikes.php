<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

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
            PsalmApi::$codebase->classlikes->getStorageFor($this->toFqClassName($classlike))
        );
    }

    public function classExtends(string|Atomic\TNamedObject $classlike, string $possible_parent): bool
    {
        return PsalmApi::$codebase->classlikes->classExtends($this->toFqClassName($classlike), $possible_parent);
    }

    public function classImplements(string|Atomic\TNamedObject $classlike, string $interface): bool
    {
        return PsalmApi::$codebase->classlikes->classImplements($this->toFqClassName($classlike), $interface);
    }

    public function toShortName(ClassLikeStorage|Atomic\TNamedObject|string $fqn_classlike_name): string
    {
        $name = match (true) {
            $fqn_classlike_name instanceof ClassLikeStorage => $fqn_classlike_name->name,
            $fqn_classlike_name instanceof Atomic\TNamedObject => $fqn_classlike_name->value,
            default => $fqn_classlike_name,
        };

        return str_contains($name, '\\') ? substr(strrchr($name, '\\'), 1) : $name;
    }
}
