<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit;

use Psalm\Type\Atomic;
use Fp\Functional\Option\Option;
use Psalm\Storage\ClassLikeStorage;

use function Fp\Collection\exists;

final class Classlikes
{
    private function toFqClassName(string|Atomic\TNamedObject|ClassLikeStorage $classlike): string
    {
        return match (true) {
            $classlike instanceof ClassLikeStorage => $classlike->name,
            $classlike instanceof Atomic\TNamedObject => $classlike->value,
            default => $classlike,
        };
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

    /**
     * @param string|non-empty-list<string> $possible_parent
     */
    public function classExtends(string|Atomic\TNamedObject|ClassLikeStorage $classlike, string|array $possible_parent): bool
    {
        return exists(
            is_string($possible_parent) ? [$possible_parent] : $possible_parent,
            fn($c) => PsalmApi::$codebase->classlikes->classExtends($this->toFqClassName($classlike), $c),
        );
    }

    /**
     * @param string|non-empty-list<string> $interface
     */
    public function classImplements(string|Atomic\TNamedObject|ClassLikeStorage $classlike, string|array $interface): bool
    {
        return exists(
            is_string($interface) ? [$interface] : $interface,
            fn($i) => PsalmApi::$codebase->classlikes->classImplements($this->toFqClassName($classlike), $i),
        );
    }

    /**
     * @param string|non-empty-list<string> $trait
     */
    public function isTraitUsed(string|Atomic\TNamedObject|ClassLikeStorage $classlike, string|array $trait): bool
    {
        $storage = $classlike instanceof ClassLikeStorage
            ? Option::some($classlike)
            : $this->getStorage($classlike);

        return $storage
            ->map(fn(ClassLikeStorage $s) => exists(
                is_string($trait) ? [$trait] : $trait,
                fn($t) => array_key_exists(strtolower($t), $s->used_traits),
            ))
            ->getOrElse(false);
    }
}
