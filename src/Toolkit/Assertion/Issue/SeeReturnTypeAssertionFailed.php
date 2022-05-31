<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Issue;

use Fp\PsalmToolkit\Toolkit\Assertion\Collector\HaveCodeAssertionData;
use Fp\PsalmToolkit\Toolkit\Assertion\Collector\SeeReturnTypeAssertionData;
use Psalm\Issue\CodeIssue;

final class SeeReturnTypeAssertionFailed extends CodeIssue
{
    public function __construct(HaveCodeAssertionData $haveCodeAssertion, SeeReturnTypeAssertionData $seeReturnTypeAssertion)
    {
        parent::__construct(
            message: implode(' ', [
                "Actual return type: {$haveCodeAssertion->actual_return_type->getId()},",
                "Expected return type: {$seeReturnTypeAssertion->expected_return_type->getId()}",
            ]),
            code_location: $seeReturnTypeAssertion->code_location,
        );
    }
}
