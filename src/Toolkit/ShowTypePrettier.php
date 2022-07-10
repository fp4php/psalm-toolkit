<?php

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Psalm\Type;
use Psalm\Type\Union;
use Psalm\Type\Atomic;

final class ShowTypePrettier
{
    public static function pretty(Union $union): string
    {
        return "\n" . self::union($union) . "\n";
    }

    private static function union(Union $union, int $level = 1): string
    {
        return implode(' | ', array_map(
            fn($atomic) => self::atomic($atomic, $level),
            $union->getAtomicTypes(),
        ));
    }

    private static function atomic(Atomic $atomic, int $level): string
    {
        return match (true) {
            $atomic instanceof Atomic\TList => self::list($atomic, $level),
            $atomic instanceof Atomic\TArray => self::array($atomic, $level),
            $atomic instanceof Atomic\TIterable => self::iterable($atomic, $level),
            $atomic instanceof Atomic\TClosure => self::callable($atomic, $level),
            $atomic instanceof Atomic\TCallable => self::callable($atomic, $level),
            $atomic instanceof Atomic\TClassString => self::classString($atomic, $level),
            $atomic instanceof Atomic\TLiteralClassString => self::literalClassString($atomic),
            $atomic instanceof Atomic\TNamedObject => self::namedObject($atomic, $level),
            $atomic instanceof Atomic\TKeyedArray => self::keyedArray($atomic, $level),
            $atomic instanceof Atomic\TTemplateParam => self::templateParam($atomic, $level),
            default => $atomic->getId(),
        };
    }

    private static function iterable(Atomic\TIterable $atomic, int $level): string
    {
        $key = self::union($atomic->type_params[0], $level);
        $val = self::union($atomic->type_params[1], $level);

        return "iterable<{$key}, {$val}>";
    }

    private static function classString(Atomic\TClassString $atomic, int $level): string
    {
        return null !== $atomic->as_type
            ? self::namedObject($atomic->as_type, $level) . '::class'
            : 'class-string';
    }

    private static function literalClassString(Atomic\TLiteralClassString $atomic): string
    {
        return self::shortClassName($atomic->value) . '::class';
    }

    private static function templateParam(Atomic\TTemplateParam $atomic, int $level): string
    {
        $shortClassName = self::shortClassName($atomic->defining_class);
        $as = self::union($atomic->as, $level);

        return "from {$shortClassName} as {$as}";
    }

    private static function array(Atomic\TArray $atomic, int $level): string
    {
        $key = self::union($atomic->type_params[0], $level);
        $val = self::union($atomic->type_params[1], $level);

        return $atomic instanceof Atomic\TNonEmptyArray
            ? "non-empty-array<{$key}, {$val}>"
            : "array<{$key}, {$val}>";
    }

    private static function list(Atomic\TList $atomic, int $level): string
    {
        $type = self::union($atomic->type_param, $level);

        return $atomic instanceof Atomic\TNonEmptyList
            ? "non-empty-list<{$type}>"
            : "list<{$type}>";
    }

    private static function callable(Atomic\TClosure|Atomic\TCallable $atomic, int $level): string
    {
        $return = self::union($atomic->return_type ?? Type::getVoid(), $level);

        $params = implode(', ', array_map(
            function($param) use ($level) {
                $paramType = $param->type ?? Type::getMixed();
                $paramName = $param->by_ref ? "&\${$param->name}" : "\${$param->name}";
                $variadic = $param->is_variadic ? '...' : '';

                return trim($variadic . self::union($paramType, $level) . " {$paramName}");
            },
            $atomic->params ?? [],
        ));

        $pure = $atomic->is_pure ? 'pure-' : '';

        return $atomic instanceof Atomic\TClosure
            ? "{$pure}Closure({$params}): {$return}"
            : "{$pure}callable({$params}): {$return}";
    }

    private static function getGenerics(Atomic\TGenericObject $atomic, int $level): string
    {
        return implode(', ', array_map(
            fn(Union $param) => self::union($param, $level),
            $atomic->type_params,
        ));
    }

    private static function shortClassName(string $class): string
    {
        if (1 === preg_match('~(\\\\)?(?<short_class_name>\w+)$~', $class, $m)) {
            return $m['short_class_name'];
        }

        return $class;
    }

    private static function namedObject(Atomic\TNamedObject $atomic, int $level): string
    {
        $generics = $atomic instanceof Atomic\TGenericObject
            ? self::getGenerics($atomic, $level)
            : null;

        $shortClassName = self::shortClassName($atomic->value);
        $mainSide = null !== $generics ? "{$shortClassName}<{$generics}>" : $shortClassName;

        $intersectionTypes = $atomic->getIntersectionTypes();

        $intersectionSide = null !== $intersectionTypes
            ? implode(', ', array_map(fn(Atomic $a) => self::atomic($a, $level), $intersectionTypes))
            : null;

        return null !== $intersectionSide ? "{$mainSide} & {$intersectionSide}" : $mainSide;
    }

    private static function keyedArray(Atomic\TKeyedArray $atomic, int $level): string
    {
        $tab = fn(int $l): string => str_repeat("    ", $l);

        $openBracket = 'array{';
        $closeBracket = $level === 1 ? '}' : $tab($level - 1) . '}';
        $isList = self::isKeyedArrayList($atomic);

        $shape = $isList
            ? array_map(
                fn(Union $type) => self::union($type, $level + 1),
                $atomic->properties,
            )
            : array_map(
                fn(int|string $property, Union $type) => implode('', [
                    $tab($level),
                    $type->possibly_undefined ? "{$property}?: " : "{$property}: ",
                    self::union($type, $level + 1),
                ]),
                array_keys($atomic->properties),
                array_values($atomic->properties),
            );

        return $isList
            ? $openBracket . implode(", ", array_values($shape)) . $closeBracket
            : $openBracket . "\n" . implode(",\n", array_values($shape)) . ",\n" . $closeBracket;
    }

    private static function isKeyedArrayList(Atomic\TKeyedArray $atomic): bool
    {
        return array_keys($atomic->properties) === range(0, count($atomic->properties) - 1);
    }
}
