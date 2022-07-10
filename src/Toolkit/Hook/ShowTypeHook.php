<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use Psalm\Type\Union;
use Psalm\CodeLocation;
use Psalm\Issue\Trace;
use Psalm\IssueBuffer;
use Fp\PsalmToolkit\Toolkit\ShowTypePrettier;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\AfterStatementAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use function Fp\Evidence\proveOf;

final class ShowTypeHook implements AfterExpressionAnalysisInterface, AfterStatementAnalysisInterface
{
    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        self::handle($event);

        return null;
    }

    public static function afterStatementAnalysis(AfterStatementAnalysisEvent $event): ?bool
    {
        self::handle($event);

        return null;
    }

    private static function handle(AfterStatementAnalysisEvent|AfterExpressionAnalysisEvent $event): void
    {
        $node = $event instanceof AfterStatementAnalysisEvent
            ? $event->getStmt()
            : $event->getExpr();

        proveOf($node, Expr::class)
            ->orElse(fn() => proveOf($node, Name::class))
            ->orElse(fn() => proveOf($node, Return_::class))
            ->filter(fn(Node $node) => Option::fromNullable($node->getDocComment())
                ->map(fn(Doc $doc) => str_contains($doc->getText(), '@show-type'))
                ->getOrElse(false))
            ->tap(function($node) use ($event) {
                $location = new CodeLocation($event->getStatementsSource(), $node);

                IssueBuffer::accepts(
                    PsalmApi::$types->getType($event, $node instanceof Assign ? $node->expr : $node)
                        ->map(fn(Union $type) => ShowTypePrettier::pretty($type))
                        ->map(fn(string $type) => new Trace($type, $location))
                        ->getOrCall(fn() => new Trace('Unable to determine type', $location)),
                );
            });
    }
}
