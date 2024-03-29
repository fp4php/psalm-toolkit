<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Fp\Collections\ArrayList;
use PhpParser\Node;
use Fp\Functional\Option\Option;
use Psalm\CodeLocation;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterFunctionCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
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
        AfterStatementAnalysisEvent |
        AfterMethodCallAnalysisEvent |
        AfterFunctionCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $from,
        Node\Arg | Node\VariadicPlaceholder $for,
    ): Option {
        return proveOf($for, Node\Arg::class)
            ->flatMap(fn(Node\Arg $arg) => PsalmApi::$types->getType($from, $arg->value));
    }

    /**
     * @return Option<ArrayList<CallArg>>
     */
    public function getCallArgs(
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterFunctionCallAnalysisEvent |
        AfterMethodCallAnalysisEvent $from,
    ): Option {
        $source = match (true) {
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
            $from instanceof FunctionReturnTypeProviderEvent => $from->getStatementsSource(),
            $from instanceof AfterFunctionCallAnalysisEvent => $from->getStatementsSource(),
            $from instanceof AfterMethodCallAnalysisEvent => $from->getStatementsSource(),
        };

        $args = match (true) {
            $from instanceof AfterFunctionCallAnalysisEvent => $from->getExpr()->getArgs(),
            $from instanceof AfterMethodCallAnalysisEvent => $from->getExpr()->getArgs(),
            default => $from->getCallArgs(),
        };

        return ArrayList::collect($args)
            ->traverseOption(fn($arg) => $this->getArgType($from, $arg)->map(
                fn(Union $type) => new CallArg($arg, new CodeLocation($source, $arg), $type)
            ));
    }

    /**
     * @return Option<CallArg>
     */
    public function getFirstCallArg(
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterFunctionCallAnalysisEvent |
        AfterMethodCallAnalysisEvent $from,
    ): Option {
        return $this->getCallArgs($from)->flatMap(fn(ArrayList $args) => $args->head());
    }

    /**
     * @return Option<Union>
     */
    public function getFirstCallArgType(
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterFunctionCallAnalysisEvent |
        AfterMethodCallAnalysisEvent $from,
    ): Option {
        return $this->getFirstCallArg($from)->map(fn(CallArg $arg) => $arg->type);
    }
}
