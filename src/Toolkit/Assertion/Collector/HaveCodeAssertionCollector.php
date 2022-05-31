<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use PhpParser\Node;
use Psalm\CodeLocation;
use Psalm\Type;
use Psalm\Type\Atomic\TClosure;
use function Fp\Collection\first;

final class HaveCodeAssertionCollector implements AssertionCollectorInterface
{
    public static function collect(Assertions $data, AssertionCollectingContext $context): Option
    {
        return Option::do(fn() => $data->with(
            new HaveCodeAssertionData(
                code_location: yield self::getClosureCodeLocation($context),
                actual_return_type: yield self::getClosureReturnType($context),
            ))
        );
    }

    /**
     * @return Option<Type\Union>
     */
    private static function getClosureReturnType(AssertionCollectingContext $context): Option
    {
        return first($context->assertion_call->args)
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($context->event, $arg))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TClosure::class, $union))
            ->map(fn($atomic) => $atomic->return_type ?? Type::getVoid());
    }

    /**
     * @return Option<CodeLocation>
     */
    private static function getClosureCodeLocation(AssertionCollectingContext $context): Option
    {
        return first($context->assertion_call->args)
            ->filter(fn($arg) => $arg instanceof Node\Arg && (
                    $arg->value instanceof Node\Expr\Closure ||
                    $arg->value instanceof Node\Expr\ArrowFunction))
            ->map(fn($arg) => new CodeLocation($context->event->getStatementsSource(), $arg));
    }

    public static function isSupported(AssertionCollectingContext $context): bool
    {
        return 'haveCode' === $context->assertion_name;
    }
}
