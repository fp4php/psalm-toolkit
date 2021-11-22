## Psalm test

Static testing tool for psalm plugins.

### Installation

```shell
$ composer require --dev klimick/psalm-test
$ vendor/bin/psalm-plugin enable klimick/psalm-test
```

### Usage

At the moment you can use two methods for static asserts:
- `seePsalmIssue`: Checks that a code block from the `haveCode` have specific issue.
- `seeReturnType`: Verifies a return type from the `haveCode` block.

Usage example below:

```php
<?php

namespace Klimick\Decode\Test\Static;

use Klimick\PsalmTest\PsalmTest;
use Klimick\PsalmTest\StaticTestCase;
use Klimick\PsalmTest\StaticType\StaticTypes as t;

final class ExampleTest extends PsalmTest
{
    public function __invoke(): void
    {
        StaticTestCase::describe('See InvalidScalarArgument issue')
            ->haveCode(function() {
                $plus = fn(int $a, int $b): int => $a + $b;

                $plus(10, 10.00);
            })
            ->seePsalmIssue(
                type: 'InvalidScalarArgument',
                message: 'Argument 2 expects int, float(10) provided',
            );

        StaticTestCase::describe('See return type (invariant type compare)')
            ->haveCode(function() {
                return [
                    'twenty' => 10 + 10,
                    'message' => 'Hello world!'
                ];
            })
            ->seeReturnType(
                is: t::shape([
                    'twenty' => t::literal(20),
                    'message' => t::literal('Hello world!'),
                ]),
            );

        StaticTestCase::describe('See return type (covariant type compare)')
            ->haveCode(function() {
                return [
                    'twenty' => 10 + 10,
                    'message' => 'Hello world!'
                ];
            })
            ->seeReturnType(
                is: t::shape([
                    'twenty' => t::int(),
                    'message' => t::string(),
                ]),
                invariant: false,
            );
    }
}
```
