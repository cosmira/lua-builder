<?php

declare(strict_types=1);

namespace Cosmira\Lua;

/** @internal */
enum ExpressionKind
{
    case Value;
    case Reference;
    case Call;
}
