## Psalm toolkit

Psalm api in the functional style.

### Installation

```shell
$ composer require fp4php/psalm-toolkit
```

Package `fp4php/functional` must be installed manually.

```shell
$ composer require fp4php/functional
```

### Usage

Call in the plugin entry point:

```php
<?php

declare(strict_types=1);

use SimpleXMLElement;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Fp\PsalmToolkit\PsalmApi;

final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        PsalmApi::init();

        // next register hooks
    }
}

```