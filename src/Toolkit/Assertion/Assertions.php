<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion;

use Fp\Functional\Option\Option;
use function Fp\Collection\at;

final class Assertions
{
    /**
     * @param array<class-string<AssertionData>, AssertionData> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @template T of AssertionData
     *
     * @param class-string<T> $name
     * @return Option<T>
     */
    public function __invoke(string $name): Option
    {
        return at($this->data, $name)->filterOf($name);
    }

    /**
     * @template T of AssertionData
     *
     * @param class-string<T> $name
     * @param T $value
     */
    public function with(AssertionData $value): self
    {
        return new self(array_merge($this->data, [$value::class => $value]));
    }
}
