<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Fp\Functional\Option\Option;
use Psalm\Storage\ClassLikeStorage;
use Psalm\Storage\PropertyStorage;
use Psalm\Type\Atomic\TNamedObject;

use function Fp\Collection\at;

final class Properties
{
    /**
     * @return Option<PropertyStorage>
     */
    public function getStorage(string|TNamedObject|ClassLikeStorage $object, string $property): Option
    {
        $storage = !($object instanceof ClassLikeStorage)
            ? PsalmApi::$classlikes->getStorage($object)
            : Option::some($object);

        return $storage->flatMap(
            fn(ClassLikeStorage $s) => at($s->properties, $property)
                ->orElse(fn() => self::getAppearingStorage($s, $property))
                ->orElse(fn() => self::getFromParent($s, $property)),
        );
    }

    /**
     * @return Option<PropertyStorage>
     */
    private function getFromParent(ClassLikeStorage $storage, string $property): Option
    {
        return Option::fromNullable($storage->parent_class)
            ->flatMap(fn(string $parent) => $this->getStorage($parent, $property));
    }

    /**
     * @return Option<PropertyStorage>
     */
    private function getAppearingStorage(ClassLikeStorage $storage, string $property): Option
    {
        return at($storage->appearing_property_ids, $property)->flatMap(
            fn(string $propertyId) => Option::try(fn() => PsalmApi::$codebase->properties->getStorage($propertyId)),
        );
    }
}
