<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration;

use PhpParser\Node;
use Fp\Functional\Option\Option;
use Psalm\Context;
use Psalm\Internal\Analyzer\Statements\ExpressionAnalyzer;
use Psalm\Internal\Analyzer\StatementsAnalyzer;
use Psalm\Internal\Type\TypeExpander;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\StatementsSource;
use Psalm\Type\Atomic;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TFloat;
use Psalm\Type\Atomic\TInt;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TList;
use Psalm\Type\Atomic\TClassString;
use Psalm\Type\Atomic\TLiteralClassString;
use Psalm\Type\Atomic\TLiteralFloat;
use Psalm\Type\Atomic\TLiteralInt;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Atomic\TNonEmptyArray;
use Psalm\Type\Atomic\TNonEmptyList;
use Psalm\Type\Atomic\TNonEmptyString;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Union;
use function Fp\Cast\asList;
use function Fp\Collection\at;
use function Fp\Collection\first;
use function Fp\Collection\map;
use function Fp\Evidence\proveOf;

final class Types
{
    public function isTypeContainedByType(Union $input_type, Union $container_type): bool
    {
        return PsalmToolkit::$codebase->isTypeContainedByType($input_type, $container_type);
    }

    public function isTypeEqualsToType(Union $a_type, Union $b_type): bool
    {
        return $a_type->getId() === $b_type->getId();
    }

    public function expandUnion(string $self_class, Union $type): Union
    {
        return TypeExpander::expandUnion(
            codebase: PsalmToolkit::$codebase,
            return_type: $type,
            self_class: $self_class,
            static_class_type: null,
            parent_class: null,
        );
    }

    /**
     * @return Option<Union>
     */
    public function analyzeType(StatementsSource $analyzer, Node\Expr $expr, Context $context): Option
    {
        return $this->getType($analyzer, $expr)
            ->orElse(
                fn() => proveOf($analyzer, StatementsAnalyzer::class)
                    ->flatMap(fn($analyzer) => Option::try(
                        fn() => ExpressionAnalyzer::analyze($analyzer, $expr, $context))
                    )
                    ->flatMap(fn() => $this->getType($analyzer, $expr))
            );
    }

    /**
     * @return Option<Union>
     */
    public function getType(
        StatementsSource |
        NodeTypeProvider |
        AfterMethodCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $from,
        Node\Expr | Node\Name | Node\Stmt\Return_ $for,
    ): Option
    {
        $provider = match (true) {
            $from instanceof NodeTypeProvider => $from,
            $from instanceof StatementsSource => $from->getNodeTypeProvider(),
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource()->getNodeTypeProvider(),
            $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource()->getNodeTypeProvider(),
            $from instanceof AfterExpressionAnalysisEvent => $from->getStatementsSource()->getNodeTypeProvider(),
            $from instanceof AfterMethodCallAnalysisEvent => $from->getStatementsSource()->getNodeTypeProvider(),
        };

        return Option::fromNullable($provider->getType($for));
    }

    /**
     * @return Option<Atomic>
     */
    public function asSingleAtomic(Union $union): Option
    {
        return Option::some($union->getAtomicTypes())
            ->map(fn($atomics) => asList($atomics))
            ->filter(fn($atomics) => 1 === count($atomics))
            ->flatMap(fn($atomics) => first($atomics));
    }

    /**
     * @template TAtomic of Atomic
     *
     * @param class-string<TAtomic> $class
     * @return Option<TAtomic>
     */
    public function asSingleAtomicOf(string $class, Union $union): Option
    {
        return self::asSingleAtomic($union)
            ->flatMap(fn(Atomic $atomic) => proveOf($atomic, $class));
    }

    public function asNonLiteralType(Union $type): Union
    {
        return new Union(
            map(asList($type->getAtomicTypes()), fn($a) => match (true) {
                $a instanceof TLiteralClassString => new TClassString(),
                $a instanceof TLiteralString => empty($a->value)
                    ? new TString()
                    : new TNonEmptyString(),
                $a instanceof TLiteralInt => new TInt(),
                $a instanceof TLiteralFloat => new TFloat(),
                $a instanceof TKeyedArray => new TNonEmptyArray([
                    self::asNonLiteralType($a->getGenericKeyType()),
                    self::asNonLiteralType($a->getGenericValueType()),
                ]),
                $a instanceof TNonEmptyList => new TNonEmptyList(
                    self::asNonLiteralType($a->type_param),
                ),
                $a instanceof TList => new TList(
                    self::asNonLiteralType($a->type_param),
                ),
                $a instanceof TNonEmptyArray => new TNonEmptyArray([
                    self::asNonLiteralType($a->type_params[0]),
                    self::asNonLiteralType($a->type_params[1]),
                ]),
                $a instanceof TArray => new TArray([
                    self::asNonLiteralType($a->type_params[0]),
                    self::asNonLiteralType($a->type_params[1]),
                ]),
                default => $a,
            }),
        );
    }

    /**
     * @param class-string $of
     * @param int<0, max> $position
     * @return Option<Union>
     */
    public function getGeneric(Atomic\TGenericObject $from, string $of, int $position): Option
    {
        return Option::some($from)
            ->filter(fn($a) => $a->value === $of)
            ->flatMap(fn($a) => at($a->type_params, $position));
    }
}
