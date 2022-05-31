<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use Fp\Collections\NonEmptyLinkedList;
use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Fp\PsalmToolkit\StaticType\StaticTypeInterface;
use Fp\PsalmToolkit\StaticType\StaticTypes;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type\Union;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TGenericObject;
use function Fp\Collection\first;
use function Fp\Evidence\proveTrue;

final class IntersectionReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [StaticTypes::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        $return_type = Option::do(function() use ($event) {
            yield proveTrue('intersection' === $event->getMethodNameLowercase());

            $types = yield self::getTypes($event);
            $first_type = clone $types->head();

            foreach ($types->tail() as $addToIntersection) {
                $first_type->addIntersectionType($addToIntersection);
            }

            return new Union([
                new TGenericObject(StaticTypeInterface::class, [
                    new Union([$first_type]),
                ]),
            ]);
        });

        return $return_type->get();
    }

    /**
     * @return Option<NonEmptyLinkedList<TNamedObject>>
     */
    private static function getTypes(MethodReturnTypeProviderEvent $event): Option
    {
        return Option::do(function() use ($event) {
            $keyed_array = yield first($event->getCallArgs())
                ->flatMap(fn($arg) => PsalmApi::$args->getArgType($event, $arg))
                ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TKeyedArray::class, $union))
                ->filter(fn($keyed_array) => $keyed_array->is_list);

            $types = [];

            foreach ($keyed_array->properties as $property) {
                $types[] = yield Option::some($property)
                    ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TGenericObject::class, $union))
                    ->flatMap(fn($generic) => PsalmApi::$types->getFirstGeneric($generic, StaticTypeInterface::class))
                    ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TNamedObject::class, $union));
            }

            return NonEmptyLinkedList::collectNonEmpty($types);
        });
    }
}
