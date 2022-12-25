<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Fp\Functional\Option\Option;
use Psalm\Internal\MethodIdentifier;
use Psalm\Storage\ClassLikeStorage;
use Psalm\Storage\MethodStorage;
use Psalm\Type\Atomic\TNamedObject;

use function Fp\Collection\at;

final class Methods
{
    /**
     * @return Option<MethodStorage>
     */
    public function getStorage(string|TNamedObject|ClassLikeStorage $object, string $method): Option
    {
        $storage = !($object instanceof ClassLikeStorage)
            ? PsalmApi::$classlikes->getStorage($object)
            : Option::some($object);

        return $storage->flatMap(fn(ClassLikeStorage $s) => at($s->methods, strtolower($method))->orElse(
            fn() => self::getDeclaringStorage($s, strtolower($method)),
        ));
    }

    /**
     * @return Option<MethodStorage>
     */
    private function getDeclaringStorage(ClassLikeStorage $storage, string $method): Option
    {
        return at($storage->declaring_method_ids, strtolower($method))->flatMap(
            fn(MethodIdentifier $id) => Option::try(fn() => PsalmApi::$codebase->methods->getStorage($id)),
        );
    }
}
