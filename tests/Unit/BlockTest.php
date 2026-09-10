<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Unit;

use Cosmira\Lua\Block;
use Cosmira\Lua\Lua;
use Cosmira\Lua\Statement as S;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BlockTest extends TestCase
{
    public function testBlocksAreImmutableAndEmptyBlocksRenderEmpty(): void
    {
        $empty = new Block;
        $block = $empty->append(S::local('x'));
        self::assertSame('', $empty->toLua());
        self::assertSame("local x = nil;\n", $block->toLua());
        self::assertSame("local x = nil;\nreturn 1, false;\n", (string) $block->append(S::return(1, false)));
        self::assertSame("return;\n", (string) new Block(S::return()));
        self::assertSame("-- one\n-- two\n-- three\n-- four\n-- \n", (string) new Block(S::comment("one\r\ntwo\rthree\nfour\n")));
        self::assertSame("-- \n", (string) new Block(S::comment('')));
        self::assertSame("trusted()\n", (string) new Block(S::raw('trusted()')));
        self::assertSame("local a = 1;\nlocal b = 2;\n", (string) $empty->append(S::local('a', 1), S::local('b', 2)));
        self::assertSame("local a = 1;\nlocal b = 2;\n", (string) (new Block(first: S::local('a', 1)))->append(first: S::local('b', 2)));
        self::assertSame($block->toLua(), $block->append()->toLua());
    }

    public function testNestedControlFlowFormatting(): void
    {
        $block = new Block(
            S::local('x', 0),
            S::while(Lua::var('x')->binary('<', 3), new Block(
                S::if(Lua::var('x')->binary('==', 2), new Block(S::break()), new Block(Lua::var('x')->assign(Lua::var('x')->binary('+', 1)))),
            )),
            S::return(Lua::var('x')),
        );
        self::assertSame("local x = 0;\nwhile (x < 3) do\n    if (x == 2) then\n        break;\n    else\n        x = (x + 1);\n    end\nend\nreturn x;\n", $block->toLua());
        self::assertSame($block->toLua(), $block->toLua());
    }

    public function testLoopFunctionAndScopeForms(): void
    {
        self::assertSame("if true then\nend\n", (string) new Block(S::if(Lua::value(true), new Block)));
        self::assertSame("repeat\n    break;\nuntil true;\n", (string) new Block(S::repeat(new Block(S::break()), Lua::value(true))));
        self::assertSame("for i = 1, 3, 1 do\nend\n", (string) new Block(S::forRange('i', 1, 3, new Block)));
        self::assertSame("for i = 3, 1, -1 do\n    break;\nend\n", (string) new Block(S::forRange('i', 3, 1, new Block(S::break()), -1)));
        self::assertSame("for k, v in pairs(t) do\nend\n", (string) new Block(S::forEach(['k', 'v'], Lua::call('pairs', Lua::var('t')), new Block)));
        self::assertSame("function module.f(x)\n    return x;\nend\n", (string) new Block(S::function('module.f', ['x'], new Block(S::return(Lua::var('x'))))));
        self::assertSame("local function f()\nend\n", (string) new Block(S::localFunction('f', [], new Block)));
        self::assertSame("local function f(x, y)\n    return x, y;\nend\n", (string) new Block(S::localFunction('f', ['x', 'y'], new Block(S::return(Lua::var('x'), Lua::var('y'))))));
        self::assertSame("do\n    return;\nend\nprint();\n", (string) new Block(S::scope(new Block(S::return())), Lua::call('print')->statement()));
        self::assertSame("while true do\n    do\n        break;\n    end\nend\n", (string) new Block(S::while(Lua::value(true), new Block(S::scope(new Block(S::break()))))));
        self::assertSame("while true do\n    if false then\n    else\n        break;\n    end\nend\n", (string) new Block(S::while(Lua::value(true), new Block(S::if(Lua::value(false), new Block, new Block(S::break()))))));
    }

    #[DataProvider('invalidBlocks')]
    public function testInvalidBlocksAreRejected(string $case): void
    {
        $this->expectException(InvalidArgumentException::class);
        $block = match ($case) {
            'after-return'         => new Block(S::return(), S::local('x')),
            'after-break'          => new Block(S::break(), S::local('x')),
            'append-return'        => (new Block(S::return()))->append(S::local('x')),
            'top-break'            => new Block(S::break()),
            'if-break'             => new Block(S::if(Lua::value(true), new Block(S::break()))),
            'else-break'           => new Block(S::if(Lua::value(true), new Block, new Block(S::break()))),
            'function-break'       => new Block(S::while(Lua::value(true), new Block(S::function('f', [], new Block(S::break()))))),
            'local-function-break' => new Block(S::while(Lua::value(true), new Block(S::localFunction('f', [], new Block(S::break()))))),
            'empty-iterator'       => new Block(S::forEach([], Lua::call('pairs'), new Block)),
            'invalid-local'        => new Block(S::local('end')),
            'invalid-range'        => new Block(S::forRange('x.y', 1, 2, new Block)),
            'invalid-function'     => new Block(S::localFunction('x.y', [], new Block)),
        };
        $block->toLua();
    }

    public static function invalidBlocks(): iterable
    {
        foreach (['after-return', 'after-break', 'append-return', 'top-break', 'if-break', 'else-break', 'function-break', 'local-function-break', 'empty-iterator', 'invalid-local', 'invalid-range', 'invalid-function'] as $case) {
            yield [$case];
        }
    }
}
