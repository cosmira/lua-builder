<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Runtime;

use Cosmira\Lua\Lua;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class RulesTest extends TestCase
{
    #[DataProvider('events')]
    public function testGeneratedRulesExecuteInOrderAndShortCircuit(string $date, string $department, string $extension, int $checks, bool $matches): void
    {
        $generator = new Process([PHP_BINARY, __DIR__.'/../../examples/rules.php']);
        $generator->setTimeout(5);
        $generator->mustRun();

        $setup = Lua::script()
            ->local('context', ['date' => $date])
            ->local('attributes', ['department' => $department, 'extension' => $extension]);

        $harness = <<<'LUA'
            local checks = 0
            local engine = {
                matches = function(filter)
                    checks = checks + 1
                    for _, expected in ipairs(filter.values) do
                        if attributes[filter.attribute] == expected then
                            return true
                        end
                    end
                    return false
                end,
                tag = function(value) print("tag", value) end,
                notify = function(address, message) print("notify", address, message) end,
            }
            local config = get_config()
            assert(config.enabled == true)
            assert(config.recipients[1] == "ops@example.test")
            process_rules(context, engine)
            print("checks", checks)
            LUA;

        $runtime = new Process([getenv('LUA_BIN') ?: 'lua', '-']);
        $runtime->setInput($generator->getOutput().$setup->toLua().$harness);
        $runtime->setTimeout(5);
        $runtime->run();

        self::assertSame(0, $runtime->getExitCode(), $runtime->getErrorOutput());
        $expected = $matches ? "tag\tОтчёты 📄\nnotify\tops@example.test\t確認してください\n" : '';
        self::assertSame($expected."tag\tchecked\nchecks\t".$checks."\n", $runtime->getOutput());
    }

    public static function events(): iterable
    {
        yield 'Russian department and matching extension' => ['2026-06-01', 'Разработка', 'pdf', 2, true];
        yield 'Japanese department and matching extension' => ['2026-06-01', '開発', 'csv', 2, true];
        yield 'inclusive start date' => ['2026-01-01', '開発', 'pdf', 2, true];
        yield 'inclusive end date' => ['2026-12-31', '開発', 'pdf', 2, true];
        yield 'before start date skips filters' => ['2025-12-31', 'Разработка', 'pdf', 0, false];
        yield 'after end date skips filters' => ['2027-01-01', '開発', 'pdf', 0, false];
        yield 'first mismatch skips second filter' => ['2026-06-01', 'Sales', 'pdf', 1, false];
        yield 'second mismatch skips actions' => ['2026-06-01', '開発', 'exe', 2, false];
    }
}
