<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use Closure;
use Fp\Collections\ArrayList;
use PhpParser\Node;
use Psalm\Type;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Fp\Functional\Option\Option;
use function Fp\Cast\asList;
use function Fp\Collection\at;
use function Fp\Collection\first;
use function Fp\Evidence\proveOf;

/**
 * @internal
 */
final class Psalm
{
    /**
     * @return Option<Type\Union>
     */
    public static function getType(
        MethodReturnTypeProviderEvent | FunctionReturnTypeProviderEvent | AfterExpressionAnalysisEvent $from,
        Node\Expr | Node\Name | Node\Stmt\Return_ $for,
    ): Option
    {
        $source = match (true) {
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
            $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource(),
            $from instanceof AfterExpressionAnalysisEvent => $from->getStatementsSource(),
        };
        $provider = $source->getNodeTypeProvider();

        return Option::fromNullable($provider->getType($for));
    }

    /**
     * @return Option<ArrayList<Type\Union>>
     */
    public static function getArgTypes(MethodReturnTypeProviderEvent | FunctionReturnTypeProviderEvent $from): Option
    {
        return ArrayList::collect($from->getCallArgs())
            ->map(fn(Node\Arg $arg) => $arg->value)
            ->everyMap(fn($expr) => self::getType($from, $expr));
    }

    /**
     * @return ArrayList<Type\Union>
     */
    public static function getTemplates(MethodReturnTypeProviderEvent $from): ArrayList
    {
        return ArrayList::collect($from->getTemplateTypeParameters() ?? []);
    }

    /**
     * @return Option<Type\Union>
     */
    public static function getArgType(
        MethodReturnTypeProviderEvent | AfterExpressionAnalysisEvent $from,
        Node\Arg | Node\VariadicPlaceholder $for,
    ): Option
    {
        return $for instanceof Node\Arg
            ? self::getType($from, $for->value)
            : Option::none();
    }

    /**
     * @return Option<Type\Atomic>
     */
    public static function asSingleAtomic(Type\Union $union): Option
    {
        return Option::some($union->getAtomicTypes())
            ->map(fn($atomics) => asList($atomics))
            ->filter(fn($atomics) => 1 === count($atomics))
            ->flatMap(fn($atomics) => first($atomics));
    }

    /**
     * @param class-string $of
     * @param 0|positive-int $position
     * @return Option<Type\Union>
     */
    public static function getTypeParam(Type\Atomic\TGenericObject $from, string $of, int $position): Option
    {
        return Option::some($from)
            ->filter(fn($a) => $a->value === $of)
            ->flatMap(fn($a) => at($a->type_params, $position));
    }

    /**
     * @template TAtomic of Type\Atomic
     *
     * @param class-string<TAtomic> $class
     * @return Option<TAtomic>
     */
    public static function asSingleAtomicOf(string $class, Type\Union $union): Option
    {
        return self::asSingleAtomic($union)->flatMap(fn(Type\Atomic $atomic) => proveOf($atomic, $class));
    }
}
