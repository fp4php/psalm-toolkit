<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Hook;

use Fp\Collections\LinkedList;
use Fp\Collections\NonEmptyArrayList;
use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\StaticTest\PsalmCodeBlockFactory;
use Fp\PsalmToolkit\StaticTest\PsalmTest;
use Fp\PsalmToolkit\StaticTest\StaticTestCase;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Fp\PsalmToolkit\Toolkit\Assertion\AssertionsStorage;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\AssertionCollectingContext;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\HaveCodeAssertionCollector;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\SeePsalmIssuesCollector;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\SeeReturnTypeAssertionCollector;
use Fp\PsalmToolkit\Toolkit\Assertion\Reconciler\SeePsalmIssuesAssertionReconciler;
use Fp\PsalmToolkit\Toolkit\Assertion\Reconciler\SeeReturnTypeAssertionReconciler;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use Psalm\Context;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\AfterFunctionLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Plugin\EventHandler\Event\AfterFunctionLikeAnalysisEvent;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Atomic\TNamedObject;
use function Fp\Evidence\proveOf;
use function Fp\Evidence\proveTrue;

/**
 * @psalm-type AssertionName = value-of<TestCaseAnalysis::SUPPORTED_ASSERTION_METHODS>
 */
final class TestCaseAnalysis implements AfterExpressionAnalysisInterface, AfterFunctionLikeAnalysisInterface
{
    private const ASSERTION_HAVE_CODE = 'haveCode';
    private const ASSERTION_SEE_RETURN_TYPE = 'seeReturnType';
    private const ASSERTION_SEE_PSALM_ISSUE_TYPE = 'seePsalmIssue';

    private const SUPPORTED_ASSERTION_METHODS = [
        self::ASSERTION_HAVE_CODE,
        self::ASSERTION_SEE_RETURN_TYPE,
        self::ASSERTION_SEE_PSALM_ISSUE_TYPE
    ];

    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        Option::do(function() use ($event) {
            $assertion_call = yield proveOf($event->getExpr(), MethodCall::class);

            $test_class = yield self::getTestClass($event->getContext());
            $test_method = yield self::getTestMethod($event, $assertion_call);

            $assertion_name = yield self::getAssertionName($event, $assertion_call);

            $assertions = AssertionsStorage::get($test_class, $test_method);
            $assertion_context = new AssertionCollectingContext($test_class, $test_method, $assertion_name, $assertion_call, $event);

            AssertionsStorage::set(
                test_class: $test_class,
                test_method: $test_method,
                new_data: self::collectAssertions($assertions, $assertion_context),
            );
        });

        return null;
    }

    public static function afterStatementAnalysis(AfterFunctionLikeAnalysisEvent $event): ?bool
    {
        Option::do(function() use ($event) {
            yield proveTrue($event->getStmt() instanceof ClassMethod);

            $test_class = yield self::getTestClass($event->getContext());

            foreach (AssertionsStorage::take(for: $test_class) as $assertions) {
                self::reconcileAssertions($assertions);
            }
        });

        return null;
    }

    private static function reconcileAssertions(Assertions $assertions): void
    {
        $issues = NonEmptyArrayList
            ::collectNonEmpty([
                SeeReturnTypeAssertionReconciler::class,
                SeePsalmIssuesAssertionReconciler::class,
            ])
            ->filterMap(fn($handler) => $handler::reconcile($assertions));

        foreach ($issues as $issue) {
            IssueBuffer::accepts($issue);
        }
    }

    private static function collectAssertions(Assertions $assertions, AssertionCollectingContext $context): Assertions
    {
        $handlers = [
            HaveCodeAssertionCollector::class,
            SeeReturnTypeAssertionCollector::class,
            SeePsalmIssuesCollector::class,
        ];

        return LinkedList::collect($handlers)
            ->filter(fn($handler) => $handler::isSupported($context))
            ->fold($assertions)(fn($acc, $collector) => $collector::collect($acc, $context)->getOrElse($acc));
    }

    /**
     * @return Option<string>
     * @psalm-return Option<AssertionName>
     */
    private static function getAssertionName(AfterExpressionAnalysisEvent $event, MethodCall $method_call): Option
    {
        return Option::some($method_call->var)
            ->flatMap(fn($expr) => PsalmApi::$types->getType($event, $expr))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TNamedObject::class, $union))
            ->filter(fn($a) => $a->value === StaticTestCase::class || $a->value === PsalmCodeBlockFactory::class)
            ->flatMap(fn() => proveOf($method_call->name, Identifier::class))
            ->map(fn($id) => $id->name)
            ->filter(fn($name) => in_array($name, self::SUPPORTED_ASSERTION_METHODS, true));
    }

    /**
     * @return Option<class-string<PsalmTest>>
     */
    private static function getTestClass(Context $context): Option
    {
        /** @var Option<class-string<PsalmTest>> */
        return Option::fromNullable($context->self)
            ->filter(fn($self) => PsalmApi::$codebase->classExtends($self, PsalmTest::class));
    }

    /**
     * @return Option<string>
     * @psalm-return Option<lowercase-string>
     */
    private static function getTestMethod(AfterExpressionAnalysisEvent $event, MethodCall $assertion_call): Option
    {
        return Option::some($assertion_call)
            ->flatMap(fn($expr) => PsalmApi::$types->getType($event, $expr))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TGenericObject::class, $union))
            ->flatMap(fn($generic) => PsalmApi::$types->getFirstGeneric($generic, StaticTestCase::class))
            ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(TLiteralString::class, $union))
            ->map(fn($literal) => strtolower($literal->value));
    }
}
