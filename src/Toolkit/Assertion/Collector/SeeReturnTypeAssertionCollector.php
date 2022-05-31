<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Fp\PsalmToolkit\StaticType\StaticTypeInterface;
use Psalm\Type;
use Psalm\Type\Atomic\TFalse;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TTrue;
use function Fp\Collection\first;
use function Fp\Collection\second;

final class SeeReturnTypeAssertionCollector implements AssertionCollectorInterface
{
    public static function collect(Assertions $data, AssertionCollectingContext $context): Option
    {
        return Option::do(fn() => $data->with(
            new SeeReturnTypeAssertionData(
                code_location: $context->getCodeLocation(),
                expected_return_type: yield self::getExpectedReturnType($context),
                invariant_compare: yield self::isInvariantCompare($context),
            )
        ));
    }

    /**
     * @return Option<Type\Union>
     */
    private static function getExpectedReturnType(AssertionCollectingContext $context): Option
    {
        return first($context->assertion_call->args)
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($context->event, $arg))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TGenericObject::class, $union))
            ->flatMap(fn($generic) => PsalmApi::$types->getFirstGeneric($generic, StaticTypeInterface::class));
    }

    /**
     * @return Option<bool>
     */
    private static function isInvariantCompare(AssertionCollectingContext $context): Option
    {
        return second($context->assertion_call->args)
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($context->event, $arg))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomic($union))
            ->filter(fn($atomic) => $atomic instanceof TTrue || $atomic instanceof TFalse)
            ->map(fn($atomic) => match(true) {
                $atomic instanceof TTrue => true,
                $atomic instanceof TFalse => false,
            })
            ->orElse(fn() => Option::some(true));
    }

    public static function isSupported(AssertionCollectingContext $context): bool
    {
        return 'seeReturnType' === $context->assertion_name;
    }
}
