<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use Fp\Collections\ArrayList;
use Fp\Collections\NonEmptyArrayList;
use PhpParser\Node;
use Psalm\CodeLocation;
use Psalm\Type;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Fp\Functional\Option\Option;
use function Fp\Cast\asList;
use function Fp\Collection\at;
use function Fp\Collection\first;
use function Fp\Evidence\proveNonEmptyString;
use function Fp\Evidence\proveOf;

/**
 * @internal
 */
final class Psalm
{
    /**
     * @return lowercase-string
     */
    public function getClosureId(string $filename, Node\Expr\Closure|Node\Expr\ArrowFunction $closure): string
    {
        return strtolower($filename)
            . ':' . $closure->getLine()
            . ':' . (int) $closure->getAttribute('startFilePos')
            . ':-:closure';
    }

    /**
     * @return Option<non-empty-string>
     */
    public static function getFunctionName(Node\Expr\FuncCall $func_call): Option
    {
        return proveNonEmptyString($func_call->name->getAttribute('resolvedName'));
    }

    /**
     * @return Option<non-empty-string>
     */
    public static function getMethodName(Node\Expr\MethodCall|Node\Expr\StaticCall $method_call): Option
    {
        return proveOf($method_call->name, Node\Identifier::class)
            ->flatMap(fn($id) => proveNonEmptyString($id->name));
    }

    /**
     * @return Option<non-empty-string>
     */
    public static function getClassFromStaticCall(Node\Expr\StaticCall $static_call): Option
    {
        return proveOf($static_call->class, Node\Name::class)
            ->flatMap(fn($name) => proveNonEmptyString($name->getAttribute('resolvedName')));
    }

    /**
     * @no-named-arguments
     */
    public static function isMethodNameEq(
        Node\Expr\MethodCall|Node\Expr\StaticCall $method_call,
        string $expected_name,
        string ...$rest_names,
    ): bool
    {
        return self::getMethodName($method_call)
            ->filter(fn($actual) => in_array($actual, [$expected_name, ...$rest_names], true))
            ->isSome();
    }

    /**
     * @no-named-arguments
     */
    public static function isFunctionNameEq(Node\Expr\FuncCall $func_call, string $expected_name, string ...$rest_names): bool
    {
        return self::getFunctionName($func_call)
            ->filter(fn($actual) => in_array($actual, [$expected_name, ...$rest_names], true))
            ->isSome();
    }

    /**
     * @return Option<non-empty-string>
     */
    public static function getArgName(Node\Arg|Node\VariadicPlaceholder $arg): Option
    {
        return proveOf($arg, Node\Arg::class)
            ->flatMap(fn($arg) => proveOf($arg->name, Node\Identifier::class))
            ->flatMap(fn($id) => proveNonEmptyString($id->name));
    }

    /**
     * @return Option<Type\Union>
     */
    public static function getType(
        AfterMethodCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $from,
        Node\Expr | Node\Name | Node\Stmt\Return_ $for,
    ): Option
    {
        $source = match (true) {
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
            $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource(),
            $from instanceof AfterExpressionAnalysisEvent => $from->getStatementsSource(),
            $from instanceof AfterMethodCallAnalysisEvent => $from->getStatementsSource(),
        };
        $provider = $source->getNodeTypeProvider();

        return Option::fromNullable($provider->getType($for));
    }

    /**
     * @return Option<ArrayList<CallArg>>
     */
    public static function getCallArgs(MethodReturnTypeProviderEvent | FunctionReturnTypeProviderEvent $from): Option
    {
        return ArrayList::collect($from->getCallArgs())->everyMap(
            fn($arg) => Option::do(function() use ($from, $arg) {
                $source = match (true) {
                    $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
                    $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource(),
                };

                return new CallArg(
                    node: $arg,
                    location: new CodeLocation($source, $arg),
                    type: yield self::getType($from, $arg->value),
                );
            })
        );
    }

    /**
     * @return Option<NonEmptyArrayList<CallArg>>
     */
    public static function getNonEmptyCallArgs(MethodReturnTypeProviderEvent | FunctionReturnTypeProviderEvent $from): Option
    {
        return self::getCallArgs($from)
            ->flatMap(fn($args) => $args->toNonEmptyArrayList());
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
