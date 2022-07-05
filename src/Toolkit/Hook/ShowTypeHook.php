<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use PhpParser\Node;
use Psalm\Type\Union;
use Psalm\CodeLocation;
use Psalm\Issue\Trace;
use Psalm\IssueBuffer;
use Fp\PsalmToolkit\Toolkit\ShowTypePrettier;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\AfterStatementAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;

final class ShowTypeHook implements AfterExpressionAnalysisInterface, AfterStatementAnalysisInterface
{
    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        $node = $event->getExpr();

        if (self::hasShowComment($node) && $node instanceof Node\Expr\Assign) {
            $source = $event->getStatementsSource();
            $provider = $source->getNodeTypeProvider();

            self::show($provider->getType($node->expr), new CodeLocation($source, $node));
        }

        return null;
    }

    public static function afterStatementAnalysis(AfterStatementAnalysisEvent $event): ?bool
    {
        $node = $event->getStmt();

        if (self::hasShowComment($node) && $node instanceof Node\Stmt\Return_) {
            $source = $event->getStatementsSource();
            $provider = $source->getNodeTypeProvider();

            self::show($provider->getType($node), new CodeLocation($source, $node));
        }

        return null;
    }

    private static function hasShowComment(Node $node): bool
    {
        $doc = $node->getDocComment();

        return null !== $doc && str_contains($doc->getText(), '@show-type');
    }

    private static function show(?Union $type, CodeLocation $location): void
    {
        IssueBuffer::accepts(
            null === $type
                ? new Trace('Unable to determine type', $location)
                : new Trace(ShowTypePrettier::pretty($type), $location)
        );
    }
}
