<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\PsalmToolkit\Toolkit\Assertion\AssertionData;
use Psalm\CodeLocation;

/**
 * @psalm-immutable
 */
final class SeePsalmIssuesData implements AssertionData
{
    /**
     * @param list<SeePsalmIssue> $issues
     */
    public function __construct(public CodeLocation $code_location, public array $issues = [])
    {
    }

    public static function empty(CodeLocation $code_location): self
    {
        return new self($code_location);
    }

    /**
     * @no-named-arguments
     */
    public function concat(SeePsalmIssue ...$issues): self
    {
        $self = clone $this;
        $self->issues = [...$self->issues, ...$issues];

        return $self;
    }
}
