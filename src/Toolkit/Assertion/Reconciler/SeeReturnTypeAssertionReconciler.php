<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Reconciler;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\HaveCodeAssertionData;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\SeeReturnTypeAssertionData;
use Fp\PsalmToolkit\Toolkit\Assertion\Issue\SeeReturnTypeAssertionFailed;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Psalm\Type;

final class SeeReturnTypeAssertionReconciler implements AssertionReconcilerInterface
{
    public static function reconcile(Assertions $data): Option
    {
        return Option::do(function() use ($data) {
            $haveCodeAssertion = yield $data(HaveCodeAssertionData::class);
            $seeReturnTypeAssertion = yield $data(SeeReturnTypeAssertionData::class);

            $isValid = self::isValid(
                expected: $seeReturnTypeAssertion->expected_return_type,
                actual: $haveCodeAssertion->actual_return_type,
                invariant: $seeReturnTypeAssertion->invariant_compare,
            );

            return yield !$isValid
                ? Option::some(new SeeReturnTypeAssertionFailed($haveCodeAssertion, $seeReturnTypeAssertion))
                : Option::none();
        });
    }

    private static function isValid(Type\Union $expected, Type\Union $actual, bool $invariant): bool
    {
        return $invariant
            ? PsalmApi::$types->isTypeEqualsToType($actual, $expected)
            : PsalmApi::$types->isTypeContainedByType($actual, $expected);
    }
}
