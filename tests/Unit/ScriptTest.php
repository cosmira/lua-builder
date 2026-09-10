<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Unit;

use Cosmira\Lua\Lua;
use Cosmira\Lua\Range;
use Cosmira\Lua\Script;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScriptTest extends TestCase
{
    public function testEverydayScriptReadsInExecutionOrder(): void
    {
        $script = Lua::script();
        self::assertSame('', $script->toLua());
        self::assertSame($script, $script->local('message', 'Hello!'));
        $script->call('print', Lua::var('message'));
        self::assertSame("local message = \"Hello!\";\nprint(message);\n", (string) $script);
        self::assertSame($script->toLua(), $script->toLua());
    }

    public function testAssignmentsCallsAndData(): void
    {
        $script = Lua::script()
            ->local('x')
            ->set('x', 2)
            ->set(Lua::var('t')->index('end'), 3)
            ->call(Lua::var('callbacks')->index(1), 'hello')
            ->method(Lua::var('client'), 'send', 'payload')
            ->return(Lua::var('x'), true);
        self::assertSame("local x = nil;\nx = 2;\nt[\"end\"] = 3;\ncallbacks[1](\"hello\");\nclient:send(\"payload\");\nreturn x, true;\n", (string) $script);
    }

    public function testIfBuildsBothBranchesWithoutRequiringCallbackReturn(): void
    {
        $script = Lua::script()
            ->if(Lua::var('ready'), function (Script $lua): void {
                $lua->call('start');
                $lua->call('finish');
            }, fn (Script $lua) => $lua->return(false));

        self::assertSame("if ready then\n    start();\n    finish();\nelse\n    return false;\nend\n", (string) $script);
        self::assertSame("if ready then\n    start();\nend\n", (string) Lua::script()->if(Lua::var('ready'), fn (Script $lua) => $lua->call('start')));
    }

    public function testLoopAndFunctionCallbacks(): void
    {
        $script = Lua::script()
            ->while(Lua::var('ready'), fn (Script $lua) => $lua->break())
            ->repeat(fn (Script $lua) => $lua->call('tick'), Lua::var('done'))
            ->for('i', new Range(3, 1, -1), fn (Script $lua) => $lua->call('print', Lua::var('i')))
            ->forEach(['k', 'v'], Lua::call('pairs', Lua::var('items')), fn (Script $lua) => $lua->call('print', Lua::var('k'), Lua::var('v')))
            ->function('module.run', ['x'], fn (Script $lua) => $lua->return(Lua::var('x')))
            ->localFunction('run', [], fn (Script $lua) => $lua->return())
            ->scope(fn (Script $lua) => $lua->local('x', 1))
            ->comment("hello\nworld")
            ->raw('trusted()');

        self::assertSame("while ready do\n    break;\nend\nrepeat\n    tick();\nuntil done;\nfor i = 3, 1, -1 do\n    print(i);\nend\nfor k, v in pairs(items) do\n    print(k, v);\nend\nfunction module.run(x)\n    return x;\nend\nlocal function run()\n    return;\nend\ndo\n    local x = 1;\nend\n-- hello\n-- world\ntrusted()\n", (string) $script);
        self::assertSame("for i = 1, 2, 1 do\nend\n", (string) Lua::script()->for('i', new Range(1, 2), fn () => null));
    }

    public function testAnonymousFunctionsUseTheSameBodySyntax(): void
    {
        $function = Lua::function(['name'], fn (Script $lua) => $lua->return(Lua::value('Hello, ')->concat(Lua::var('name'))));
        self::assertSame("function (name)\n    return (\"Hello, \" .. name);\nend", (string) $function);
    }

    public function testClonesAndCapturedBodiesAreIndependent(): void
    {
        $original = Lua::script()->local('x', 1);
        $copy = clone $original;
        $copy->call('print', Lua::var('x'));
        self::assertSame("local x = 1;\n", (string) $original);

        $child = null;
        $parent = Lua::script()->scope(function (Script $lua) use (&$child): void {
            $child = $lua;
            $lua->local('y', 2);
        });
        $child->local('z', 3);
        self::assertSame("do\n    local y = 2;\nend\n", (string) $parent);
    }

    public function testCallbackExceptionsLeaveParentUnchanged(): void
    {
        $script = Lua::script()->local('x', 1);

        try {
            $script->scope(function (Script $lua): void {
                $lua->local('y', 2);

                throw new RuntimeException('Failed to build body.');
            });
            self::fail('The callback exception should propagate.');
        } catch (RuntimeException $error) {
            self::assertSame('Failed to build body.', $error->getMessage());
        }
        self::assertSame("local x = 1;\n", (string) $script);
    }

    public function testAppendingAfterReturnFailsWithoutChangingTheScript(): void
    {
        $script = Lua::script()->return('done');

        try {
            $script->call('unreachable');
            self::fail('A return must end its block.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('return and break must be the final statement in a block.', $error->getMessage());
        }
        self::assertSame("return \"done\";\n", (string) $script);
    }

    #[DataProvider('zeroSteps')]
    public function testZeroStepsAreRejected(int|float $step): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Range(1, 2, $step);
    }

    public static function zeroSteps(): iterable
    {
        yield [0];
        yield [0.0];
        yield [-0.0];
    }

    public function testDynamicRanges(): void
    {
        self::assertSame("for i = first, last, step do\nend\n", (string) Lua::script()->for('i', new Range(Lua::var('first'), Lua::var('last'), Lua::var('step')), fn () => null));
    }

    #[DataProvider('expressionMethods')]
    public function testNamedOperators(string $method, string $operator): void
    {
        self::assertSame('(x '.$operator.' 2)', (string) Lua::var('x')->{$method}(2));
    }

    public static function expressionMethods(): iterable
    {
        foreach (['add' => '+', 'subtract' => '-', 'multiply' => '*', 'divide' => '/', 'modulo' => '%', 'power' => '^', 'concat' => '..', 'lessThan' => '<', 'greaterThan' => '>', 'lessThanOrEqual' => '<=', 'greaterThanOrEqual' => '>=', 'equals' => '==', 'notEquals' => '~=', 'and' => 'and', 'or' => 'or'] as $method => $operator) {
            yield [$method, $operator];
        }
    }

    public function testUnaryMethods(): void
    {
        self::assertSame('(- x)', (string) Lua::var('x')->negate());
        self::assertSame('(not x)', (string) Lua::var('x')->not());
        self::assertSame('(# x)', (string) Lua::var('x')->length());
    }

    public function testNegativeNumbersRemainGroupedWhenRaisedToAPower(): void
    {
        self::assertSame('((-2) ^ 2)', (string) Lua::value(-2)->power(2));
        self::assertSame('((-2.5) ^ 2)', (string) Lua::value(-2.5)->power(2));
        self::assertSame('(2 ^ -2)', (string) Lua::value(2)->power(-2));
    }
}
