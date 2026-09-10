<?php

declare(strict_types=1);

namespace Cosmira\Lua;

final class Lua
{
    public static function value(mixed $value): Expression
    {
        return Expression::value($value);
    }

    public static function var(string $name): Expression
    {
        return Expression::name($name);
    }

    public static function call(string $name, mixed ...$arguments): Expression
    {
        return self::var($name)->call(...$arguments);
    }

    public static function script(): Script
    {
        return new Script;
    }

    /**
     * @param list<string>            $parameters
     * @param callable(Script): mixed $body
     */
    public static function function(array $parameters, callable $body): Expression
    {
        $script = new Script;
        $body($script);

        return Expression::function($parameters, $script->toBlock());
    }
}
