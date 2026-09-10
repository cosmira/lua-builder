<?php

declare(strict_types=1);

namespace Cosmira\Lua;

/** @internal */
enum StatementKind
{
    case Simple;
    case Return;
    case Break;
    case Loop;
    case Function;
}
