<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Closure;
use Fp\Functional\Option\Option;
use Psalm\Issue\CodeIssue;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterFunctionCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterMethodCallAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\StatementsSource;

final class Issue
{
    /**
     * @return Closure(CodeIssue): Option<empty>
     */
    public function accepts(
        StatementsSource |
        AfterMethodCallAnalysisEvent |
        AfterFunctionCallAnalysisEvent |
        MethodReturnTypeProviderEvent |
        AfterStatementAnalysisEvent |
        FunctionReturnTypeProviderEvent |
        AfterExpressionAnalysisEvent $source,
    ): Closure {
        $s = match (true) {
            $source instanceof StatementsSource => $source,
            $source instanceof AfterMethodCallAnalysisEvent => $source->getStatementsSource(),
            $source instanceof AfterFunctionCallAnalysisEvent => $source->getStatementsSource(),
            $source instanceof MethodReturnTypeProviderEvent => $source->getSource(),
            $source instanceof AfterStatementAnalysisEvent => $source->getStatementsSource(),
            $source instanceof FunctionReturnTypeProviderEvent => $source->getStatementsSource(),
            $source instanceof AfterExpressionAnalysisEvent => $source->getStatementsSource(),
        };

        return function(CodeIssue $issue) use ($s) {
            IssueBuffer::accepts($issue, $s->getSuppressedIssues());
            return Option::none();
        };
    }
}
