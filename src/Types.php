<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit;

use PhpParser\Node;
use Fp\Functional\Option\Option;
use Psalm\Context;
use Psalm\Internal\Analyzer\Statements\ExpressionAnalyzer;
use Psalm\Internal\Analyzer\StatementsAnalyzer;
use Psalm\Internal\Type\TypeExpander;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterFunctionCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use Psalm\StatementsSource;
use Psalm\Type;
use Psalm\Type\Atomic;
use Psalm\Type\Atomic\TBool;
use Psalm\Type\Atomic\TTrue;
use Psalm\Type\Atomic\TFalse;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TFloat;
use Psalm\Type\Atomic\TInt;
use Psalm\Type\Atomic\TIntRange;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TClassString;
use Psalm\Type\Atomic\TLiteralClassString;
use Psalm\Type\Atomic\TLiteralFloat;
use Psalm\Type\Atomic\TLiteralInt;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Atomic\TNonEmptyArray;
use Psalm\Type\Atomic\TNonEmptyString;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Atomic\TTemplateParam;
use Psalm\Type\Atomic\TIterable;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Atomic\TNull;
use Psalm\Type\Atomic\TObjectWithProperties;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Union;

use function Fp\Cast\asList;
use function Fp\Collection\at;
use function Fp\Collection\first;
use function Fp\Collection\map;
use function Fp\Evidence\proveOf;

final class Types
{
    public function asUnion(Atomic $atomic): Union
    {
        return new Union([$atomic]);
    }

    public function isTypeContainedByType(Union $input_type, Union $container_type): bool
    {
        return PsalmApi::$codebase->isTypeContainedByType($input_type, $container_type);
    }

    public function isTypeEqualsToType(Union $a_type, Union $b_type): bool
    {
        return $a_type->getId() === $b_type->getId();
    }

    public function asPossiblyUndefined(Union $union): Union
    {
        return $union->setPossiblyUndefined(true);
    }

    public function asAlwaysDefined(Union $union): Union
    {
        return $union->setPossiblyUndefined(false);
    }

    public function asNullable(Union $union): Union
    {
        $builder = $union->getBuilder();
        $builder->addType(new TNull());

        return $builder->freeze();
    }

    public function asNonNullable(Union $union): Union
    {
        $builder = $union->getBuilder();
        $builder->removeType((new TNull())->getKey());

        return $builder->freeze();
    }

    /**
     * @template T of TTemplateParam|TIterable|TNamedObject|TObjectWithProperties
     * @param T $to
     * @param TNamedObject|TTemplateParam|TIterable|TObjectWithProperties $type
     * @return T
     */
    public function addIntersection(
        TTemplateParam|TIterable|TNamedObject|TObjectWithProperties $to,
        TNamedObject|TTemplateParam|TIterable|TObjectWithProperties $type,
    ): TTemplateParam|TIterable|TNamedObject|TObjectWithProperties {
        return $to->addIntersectionType($type);
    }

    public function expandUnion(string $self_class, Union $type): Union
    {
        return TypeExpander::expandUnion(
            codebase: PsalmApi::$codebase,
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
        AfterFunctionCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        AfterStatementAnalysisEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $from,
        Node\Expr | Node\Name | Node\Stmt\Return_ $for,
    ): Option
    {
        $provider = match (true) {
            $from instanceof NodeTypeProvider => $from,
            $from instanceof StatementsSource => $from->getNodeTypeProvider(),
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource()->getNodeTypeProvider(),
            default => $from->getStatementsSource()->getNodeTypeProvider(),
        };

        return Option::fromNullable($provider->getType($for));
    }

    public function setType(
        StatementsSource |
        NodeTypeProvider |
        AfterMethodCallAnalysisEvent |
        AfterFunctionCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        AfterStatementAnalysisEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $to,
        Node\Expr | Node\Name | Node\Stmt\Return_ $for,
        Union $type,
    ): void
    {
        $provider = match (true) {
            $to instanceof NodeTypeProvider => $to,
            $to instanceof StatementsSource => $to->getNodeTypeProvider(),
            $to instanceof MethodReturnTypeProviderEvent => $to->getSource()->getNodeTypeProvider(),
            default => $to->getStatementsSource()->getNodeTypeProvider(),
        };

        $provider->setType($for, $type);
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
        return $this->asSingleAtomic($union)
            ->flatMap(fn(Atomic $atomic) => proveOf($atomic, $class));
    }

    public function asNonLiteralType(Union $type): Union
    {
        return new Union(
            map(asList($type->getAtomicTypes()), fn($a) => match (true) {
                $a instanceof TTrue, $a instanceof TFalse => new TBool(),
                $a instanceof TLiteralClassString => new TClassString(),
                $a instanceof TLiteralString => empty($a->value)
                    ? new TString()
                    : new TNonEmptyString(),
                $a instanceof TLiteralInt, $a instanceof TIntRange => new TInt(),
                $a instanceof TLiteralFloat => new TFloat(),
                $a instanceof TKeyedArray => $a->is_list
                    ? Type::getListAtomic(
                        $this->asNonLiteralType($a->getGenericValueType()),
                    )
                    : new TNonEmptyArray([
                        $this->asNonLiteralType($a->getGenericKeyType()),
                        $this->asNonLiteralType($a->getGenericValueType()),
                    ]),
                $a instanceof TNonEmptyArray => new TNonEmptyArray([
                    $this->asNonLiteralType($a->type_params[0]),
                    $this->asNonLiteralType($a->type_params[1]),
                ]),
                $a instanceof TArray => new TArray([
                    $this->asNonLiteralType($a->type_params[0]),
                    $this->asNonLiteralType($a->type_params[1]),
                ]),
                $a instanceof TGenericObject => new TGenericObject(
                    $a->value,
                    map($a->type_params, fn(Union $t) => $this->asNonLiteralType($t)),
                ),
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

    /**
     * @param class-string $of
     * @return Option<Union>
     */
    public function getFirstGeneric(Atomic\TGenericObject $from, string $of): Option
    {
        return $this->getGeneric($from, $of, position: 0);
    }

    /**
     * @param class-string $of
     * @return Option<Union>
     */
    public function getSecondGeneric(Atomic\TGenericObject $from, string $of): Option
    {
        return $this->getGeneric($from, $of, position: 1);
    }

    /**
     * @param class-string $of
     * @return Option<Union>
     */
    public function getThirdGeneric(Atomic\TGenericObject $from, string $of): Option
    {
        return $this->getGeneric($from, $of, position: 2);
    }
}
