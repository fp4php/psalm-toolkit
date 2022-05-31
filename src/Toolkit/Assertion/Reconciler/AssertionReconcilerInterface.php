<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Reconciler;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Psalm\Issue\CodeIssue;

interface AssertionReconcilerInterface
{
    /**
     * @return Option<CodeIssue>
     */
    public static function reconcile(Assertions $data): Option;
}
