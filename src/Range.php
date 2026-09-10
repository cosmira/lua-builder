<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;

final readonly class Range
{
    public function __construct(
        public int|float|Expression $from,
        public int|float|Expression $to,
        public int|float|Expression $step = 1,
    ) {
        if ($step === 0 || $step === 0.0) {
            throw new InvalidArgumentException('A numeric for loop step cannot be zero.');
        }
    }
}
