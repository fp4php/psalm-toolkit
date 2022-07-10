<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Fp\PsalmToolkit\StaticType\StaticTypeInterface;
use Fp\PsalmToolkit\StaticType\StaticTypes;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Atomic\TGenericObject;
use function Fp\Cast\asList;
use function Fp\Collection\first;
use function Fp\Collection\second;
use function Fp\Collection\sequenceOption;
use function Fp\Collection\traverseOption;
use function Fp\Evidence\proveTrue;

final class GenericObjectReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [StaticTypes::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Type\Union
    {
        return proveTrue('generic' === $event->getMethodNameLowercase())
            ->flatMap(fn() => sequenceOption([
                'class' => self::getTypeConstructor($event),
                'params' => self::getTypeParams($event),
            ]))
            ->map(fn($generic) => new Type\Union([
                new Type\Atomic\TGenericObject($generic['class'], $generic['params']),
            ]))
            ->map(fn($inferred_type) => new Type\Union([
                new Type\Atomic\TGenericObject(StaticTypeInterface::class, [$inferred_type]),
            ]))
            ->get();
    }

    /**
     * @return Option<string>
     */
    private static function getTypeConstructor(MethodReturnTypeProviderEvent $event): Option
    {
        return first($event->getCallArgs())
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($event, $arg))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(Type\Atomic\TLiteralClassString::class, $union))
            ->map(fn($atomic) => $atomic->value);
    }

    /**
     * @return Option<non-empty-list<Type\Union>>
     */
    private static function getTypeParams(MethodReturnTypeProviderEvent $event): Option
    {
        return second($event->getCallArgs())
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($event, $arg))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(Type\Atomic\TKeyedArray::class, $union))
            ->filter(fn($keyed_array) => $keyed_array->is_list)
            ->flatMap(fn($keyed_array) => traverseOption(
                asList($keyed_array->properties),
                fn($property) => Option::some($property)
                    ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TGenericObject::class, $union))
                    ->flatMap(fn($generic) => PsalmApi::$types->getFirstGeneric($generic, StaticTypeInterface::class))
            ));
    }
}
