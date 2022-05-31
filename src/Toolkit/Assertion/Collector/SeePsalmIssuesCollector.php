<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit\Assertion\Collector;

use Fp\Functional\Option\Option;
use Fp\PsalmToolkit\Toolkit\Assertion\Assertions;
use Fp\PsalmToolkit\Toolkit\PsalmApi;
use Psalm\Type;
use Psalm\Type\Atomic\TLiteralString;
use function Fp\Collection\at;

final class SeePsalmIssuesCollector implements AssertionCollectorInterface
{
    public static function collect(Assertions $data, AssertionCollectingContext $context): Option
    {
        return Option::do(fn() => $data->with(
            $data(SeePsalmIssuesData::class)
                ->getOrCall(fn() => SeePsalmIssuesData::empty($context->getCodeLocation()))
                ->concat(new SeePsalmIssue(
                    type: yield self::getSeePsalmIssueArg($context, position: 0)
                        ->flatMap(fn($type) => self::getLiteralStringValue($type)),
                    message: yield self::getSeePsalmIssueArg($context, position: 1)
                        ->flatMap(fn($type) => self::getLiteralStringValue($type))
                        ->map(fn($value) => self::formatMessage($value, $context)),
                ))
        ));
    }

    private static function formatMessage(string $message, AssertionCollectingContext $context): string
    {
        $formatting_args = Option::do(function() use ($context) {
            $issue_args = yield self::getSeePsalmIssueArg($context, position: 2)
                ->flatMap(fn($union) => PsalmApi::$types->asSingleAtomicOf(Type\Atomic\TKeyedArray::class, $union));

            $replacements = [];

            foreach ($issue_args->properties as $name => $property) {
                $replacements["#[{$name}]"] = yield self::getLiteralStringValue($property);
            }

            return $replacements;
        });

        return $formatting_args
            ->map(fn($args) => strtr($message, $args))
            ->getOrElse($message);
    }

    /**
     * @return Option<string>
     */
    private static function getLiteralStringValue(Type\Union $union): Option
    {
        return PsalmApi::$types
            ->asSingleAtomicOf(TLiteralString::class, $union)
            ->map(fn($atomic) => $atomic->value);
    }

    /**
     * @return Option<Type\Union>
     */
    private static function getSeePsalmIssueArg(AssertionCollectingContext $context, int $position): Option
    {
        return at($context->assertion_call->args, $position)
            ->flatMap(fn($arg) => PsalmApi::$args->getArgType($context->event, $arg));
    }

    public static function isSupported(AssertionCollectingContext $context): bool
    {
        return 'seePsalmIssue' === $context->assertion_name;
    }
}
