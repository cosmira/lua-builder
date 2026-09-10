<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;

/** @internal */
final class Literal
{
    public static function encode(mixed $value, int $depth = 0): string
    {
        if ($depth > 64) {
            throw new InvalidArgumentException('Lua values cannot exceed 64 levels of nesting.');
        }

        if ($value instanceof Expression) {
            return $value->toLua();
        }

        if ($value === null) {
            return 'nil';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Lua numeric literals must be finite.');
            }

            $number = sprintf('%.17h', $value);

            return strpbrk($number, '.e') === false ? $number.'.0' : $number;
        }

        if (is_string($value)) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            $escaped = preg_replace_callback('/[\x00-\x1f\x7f-\xff]/', static fn (array $match): string => sprintf('\\%03d', ord($match[0])), $escaped);

            return '"'.$escaped.'"';
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Unsupported Lua value: '.get_debug_type($value));
        }

        $list = array_is_list($value);
        $entries = [];

        foreach ($value as $key => $item) {
            $encoded = self::encode($item, $depth + 1);
            $entries[] = $list ? $encoded : '['.self::encode($key).'] = '.$encoded;
        }

        return '{'.implode(', ', $entries).'}';
    }
}
