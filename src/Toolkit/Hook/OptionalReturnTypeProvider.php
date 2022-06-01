<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\StaticType\StaticTypeInterface;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use function Fp\Collection\first;
use function Fp\Evidence\proveTrue;

final class OptionalReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [StaticTypeInterface::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Type\Union
    {
        $return_type = Option::do(function() use ($event) {
            yield proveTrue('optional' === $event->getMethodNameLowercase());

            $possibly_undefined = yield Option::fromNullable($event->getTemplateTypeParameters())
                ->flatMap(fn($template_params) => first($template_params))
                ->map(fn(Type\Union $type) => PsalmApi::$types->asPossiblyUndefined($type));

            return new Type\Union([
                new Type\Atomic\TGenericObject(StaticTypeInterface::class, [$possibly_undefined]),
            ]);
        });

        return $return_type->get();
    }
}
