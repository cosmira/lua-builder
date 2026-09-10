<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Runtime;

use Cosmira\Lua\Block;
use Cosmira\Lua\Expression;
use Cosmira\Lua\Lua;
use Cosmira\Lua\Range;
use Cosmira\Lua\Script;
use Cosmira\Lua\Statement as S;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ExecutionTest extends TestCase
{
    private function execute(Block|Script $block): string
    {
        $process = new Process([getenv('LUA_BIN') ?: 'lua', '-']);
        $process->setInput($block->toLua());
        $process->setTimeout(5);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput()."\n".$block);

        return $process->getOutput();
    }

    public function testEveryByteRoundTripsIncludingInjectionLikeText(): void
    {
        $bytes = implode('', array_map(chr(...), range(0, 255)))."\"; error('injected'); --\nПривет 🌙";
        $block = new Block(
            S::local('value', $bytes),
            S::forRange('i', 1, Lua::var('value')->unary('#'), new Block(
                Lua::call('io.write', Lua::call('string.format', '%02x', Lua::call('string.byte', Lua::var('value'), Lua::var('i'))))->statement(),
            )),
        );
        self::assertSame(bin2hex($bytes), $this->execute($block));
    }

    public function testExpressionPrecedenceAndAssociativity(): void
    {
        $expressions = [
            Lua::value(2)->binary('+', 3)->binary('*', 4),
            Lua::value(2)->binary('^', Lua::value(3)->binary('^', 2)),
            Lua::value(2)->binary('^', 3)->binary('^', 2),
            Lua::value(10)->binary('-', Lua::value(3)->binary('-', 1)),
            Lua::value(3)->unary('-')->unary('-'),
            Lua::value(false)->unary('not')->binary('and', Lua::value(3)->binary('>', 2)),
            Lua::value('a')->binary('..', 'b'),
        ];
        foreach (range(0, 4) as $index) {
            $expressions[$index] = Lua::call('string.format', '%.0f', $expressions[$index]);
        }
        self::assertSame("20\t512\t64\t8\t3\ttrue\tab\n", $this->execute(new Block(Lua::call('print', ...$expressions)->statement())));
    }

    public function testFunctionsScopesLoopsAndMultipleReturns(): void
    {
        $sum = Lua::var('sum');
        $block = new Block(
            S::local('sum', 0),
            S::localFunction('twice', ['x'], new Block(S::return(Lua::var('x')->binary('*', 2)))),
            S::forRange('i', 1, 3, new Block($sum->assign($sum->binary('+', Lua::call('twice', Lua::var('i')))))),
            S::forRange('i', 2, 1, new Block($sum->assign($sum->binary('+', Lua::var('i')))), -1),
            S::forEach(['i', 'v'], Lua::call('ipairs', [1, 2]), new Block($sum->assign($sum->binary('+', Lua::var('v'))))),
            S::while(Lua::value(true), new Block(S::if($sum->binary('>=', 20), new Block(S::break()), new Block($sum->assign($sum->binary('+', 1)))))),
            S::repeat(new Block($sum->assign($sum->binary('-', 1))), $sum->binary('==', 19)),
            S::scope(new Block(S::local('sum', 100), Lua::call('print', $sum)->statement())),
            S::local('module', []),
            S::function('module.result', [], new Block(S::return($sum, 'ok'))),
            Lua::call('print', Lua::call('module.result'))->statement(),
        );
        self::assertSame("100\n19\tok\n", $this->execute($block));
    }

    public function testTablesCallbacksAndStatementBoundaries(): void
    {
        $identity = Expression::function(['x'], new Block(S::return(Lua::var('x'))));
        $block = new Block(
            S::local('t', ['end' => 7, 'callback' => $identity]),
            Lua::var('t')->index('end')->assign(8),
            Lua::call('print', Lua::var('t')->index('end'), Lua::var('t')->field('callback')->call('yes'))->statement(),
            Lua::call('print', 'first')->statement(),
            Expression::function([], new Block(Lua::call('print', 'second')->statement()))->call()->statement(),
            Lua::call('print', Lua::value('hello')->method('upper'))->statement(),
            S::local('sparse', [0 => 'zero', 2 => 'two']),
            Lua::call('print', Lua::var('sparse')->index(0), Lua::var('sparse')->index(2))->statement(),
        );
        self::assertSame("8\tyes\nfirst\nsecond\nHELLO\nzero\ttwo\n", $this->execute($block));
    }

    public function testCommentsCannotBecomeExecutableSource(): void
    {
        self::assertSame("safe\n", $this->execute(new Block(
            S::comment("[=[\nerror('bad')\r\n]=]\rerror('bad')"),
            Lua::call('print', 'safe')->statement(),
        )));
    }

    public function testFluentProgramExecutesWithNestedCallbacks(): void
    {
        $sum = Lua::var('sum');
        $script = Lua::script()
            ->local('sum', 0)
            ->localFunction('twice', ['x'], fn (Script $lua) => $lua->return(Lua::var('x')->multiply(2)))
            ->for('i', new Range(1, 3), fn (Script $lua) => $lua->set('sum', $sum->add(Lua::call('twice', Lua::var('i')))))
            ->forEach(['i', 'v'], Lua::call('ipairs', [1, 2]), fn (Script $lua) => $lua->set('sum', $sum->add(Lua::var('v'))))
            ->while(Lua::value(true), fn (Script $lua) => $lua->if(
                $sum->greaterThanOrEqual(18),
                fn (Script $lua) => $lua->break(),
                fn (Script $lua) => $lua->set('sum', $sum->add(1)),
            ))
            ->repeat(fn (Script $lua) => $lua->set('sum', $sum->subtract(1)), $sum->equals(17))
            ->scope(fn (Script $lua) => $lua->local('sum', 100)->call('print', $sum))
            ->local('module', [])
            ->function('module.result', [], fn (Script $lua) => $lua->return($sum, 'ok'))
            ->call('print', Lua::call('module.result'))
            ->call(Lua::function([], fn (Script $lua) => $lua->call('print', 'callback')))
            ->method(Lua::value(''), 'sub', 1);

        self::assertSame("100\n17\tok\ncallback\n", $this->execute($script));
    }

    public function testPublishedExamplesExecute(): void
    {
        foreach (['configuration.php' => '', 'functions.php' => "passed\n", 'tables.php' => "1\tapi\n2\tworker\n"] as $file => $expected) {
            $process = new Process([PHP_BINARY, __DIR__.'/../../examples/'.$file]);
            $process->setTimeout(5);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame($expected, $this->execute(Lua::script()->raw(rtrim($process->getOutput(), "\n"))));
        }
    }

    public function testNegativeBaseExponentiationAndNegativeExponents(): void
    {
        $script = Lua::script()->call('print',
            Lua::call('string.format', '%.2f', Lua::value(-2)->power(2)),
            Lua::call('string.format', '%.2f', Lua::value(-2.5)->power(2)),
            Lua::call('string.format', '%.2f', Lua::value(2)->power(-2)),
        );
        self::assertSame("4.00\t6.25\t0.25\n", $this->execute($script));
    }
}
