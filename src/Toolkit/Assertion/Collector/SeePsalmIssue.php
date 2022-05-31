<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

/**
 * @psalm-immutable
 */
final class SeePsalmIssue
{
    public function __construct(
        public string $type,
        public string $message,
    ) {}
}
