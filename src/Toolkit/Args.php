<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Fp\Collections\ArrayList;
use Fp\Collections\NonEmptyArrayList;
use PhpParser\Node;
use Fp\Functional\Option\Option;
use Psalm\CodeLocation;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\StatementsSource;
use Psalm\Type\Union;
use function Fp\Evidence\proveOf;

final class Args
{
    /**
     * @return Option<Union>
     */
    public function getArgType(
        StatementsSource |
        NodeTypeProvider |
        AfterMethodCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $from,
        Node\Arg | Node\VariadicPlaceholder $for,
    ): Option
    {
        return proveOf($for, Node\Arg::class)
            ->flatMap(fn(Node\Arg $arg) => PsalmApi::$types->getType($from, $arg->value));
    }

    /**
     * @return Option<ArrayList<CallArg>>
     */
    public static function getCallArgs(MethodReturnTypeProviderEvent | FunctionReturnTypeProviderEvent $from): Option
    {
        $source = match (true) {
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
            $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource(),
        };

        return ArrayList::collect($from->getCallArgs())->everyMap(
            fn($arg) => Option::do(fn() => new CallArg(
                node: $arg,
                location: new CodeLocation($source, $arg),
                type: yield $this->getArgType($from, $arg),
            ))
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
}
