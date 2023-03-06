<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit;

use Psalm\Codebase;
use Psalm\Internal\Analyzer\ProjectAnalyzer;

final class PsalmApi
{
    private static bool $initialized = false;

    public static Args $args;
    public static Types $types;
    public static Codebase $codebase;
    public static Classlikes $classlikes;
    public static Issue $issue;
    public static Methods $methods;
    public static Properties $properties;

    public static function init(): void
    {
        if (PsalmApi::$initialized) {
            return;
        }

        PsalmApi::$types = new Types();
        PsalmApi::$args = new Args();
        PsalmApi::$classlikes = new Classlikes();
        PsalmApi::$codebase = ProjectAnalyzer::getInstance()->getCodebase();
        PsalmApi::$issue = new Issue();
        PsalmApi::$methods = new Methods();
        PsalmApi::$properties = new Properties();
        PsalmApi::$initialized = true;
    }
}
