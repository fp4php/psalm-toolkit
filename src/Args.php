<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit;

use PhpParser\Node;
use Psalm\Type\Union;
use Psalm\CodeLocation;
use Psalm\NodeTypeProvider;
use Psalm\StatementsSource;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterFunctionCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Fp\Functional\Option\Option;
use Fp\Collections\ArrayList;

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
     * @return ArrayList<CallArg>
     */
    public function getCallArgs(
        MethodReturnTypeProviderEvent |
        FunctionReturnTypeProviderEvent |
        AfterFunctionCallAnalysisEvent |
        AfterMethodCallAnalysisEvent $from,
    ): ArrayList {
        $source = match (true) {
            $from instanceof MethodReturnTypeProviderEvent => $from->getSource(),
            default => $from->getStatementsSource(),
        };

        $args = match (true) {
            $from instanceof AfterFunctionCallAnalysisEvent => $from->getExpr()->getArgs(),
            $from instanceof AfterMethodCallAnalysisEvent => $from->getExpr()->getArgs(),
            default => $from->getCallArgs(),
        };

        return ArrayList::collect($args)
            ->traverseOption(fn($arg) => $this->getArgType($from, $arg)->map(
                fn(Union $type) => new CallArg($arg, new CodeLocation($source, $arg), $type)
            ))
            ->getOrElse(ArrayList::empty());
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
        return $this->getCallArgs($from)->head();
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
