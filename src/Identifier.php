<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;

/** @internal */
final class Identifier
{
    private const RESERVED = ['and', 'break', 'do', 'else', 'elseif', 'end', 'false', 'for', 'function', 'goto', 'if', 'in', 'local', 'nil', 'not', 'or', 'repeat', 'return', 'then', 'true', 'until', 'while'];

    public static function validate(string $name): string
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name) !== 1 || in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException('Invalid Lua identifier: '.$name);
        }

        return $name;
    }

    /** @param array<array-key, string> $names */
    public static function parameters(array $names): string
    {
        if (! array_is_list($names) || count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('Parameters must be a list of unique identifiers.');
        }

        return implode(', ', array_map(self::validate(...), $names));
    }
}
