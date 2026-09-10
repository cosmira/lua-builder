<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Unit;

use Cosmira\Lua\Block;
use Cosmira\Lua\Expression;
use Cosmira\Lua\Lua;
use Cosmira\Lua\Statement;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpressionTest extends TestCase
{
    #[DataProvider('literals')]
    public function testLiteralEncoding(mixed $value, string $expected): void
    {
        self::assertSame($expected, Lua::value($value)->toLua());
    }

    public static function literals(): iterable
    {
        yield [null, 'nil'];
        yield [true, 'true'];
        yield [false, 'false'];
        yield [0, '0'];
        yield [-42, '-42'];
        yield [PHP_INT_MAX, (string) PHP_INT_MAX];
        yield [1.25, '1.25'];
        yield [1.0, '1.0'];
        yield [1e30, '1.0e+30'];
        yield [-0.0, '-0.0'];
        yield ['', '""'];
        yield ["a\"b\\c'd[]", '"a\\"b\\\\c\'d[]"'];
        yield ["\0".'12'."\n\r\t", '"\\00012\\010\\013\\009"'];
        yield ["\x7f\x80\xff", '"\\127\\128\\255"'];
        yield ['hello', '"hello"'];
        yield 'emoji' => ['🚀', '"\240\159\154\128"'];
        yield 'Russian UTF-8' => ['Яё', '"\208\175\209\145"'];
        yield 'Japanese UTF-8' => ['日', '"\230\151\165"'];
        yield 'Windows-1251' => ["\xCF\xF0\xE8\xE2\xE5\xF2", '"\207\240\232\226\229\242"'];
        yield 'UTF-16LE' => ["\xFF\xFEA\x00", '"\255\254A\000"'];
        yield 'invalid UTF-8' => ["\xC0\xAF", '"\192\175"'];
        yield 'combining mark' => ["e\u{0301}", '"e\204\129"'];
        yield [[], '{}'];
        yield [[1, false, null], '{1, false, nil}'];
        yield [['x' => [2]], '{["x"] = {2}}'];
        yield [[2 => 'two', 0 => 'zero'], '{[2] = "two", [0] = "zero"}'];
        yield [['end' => 1, 'a"b' => 2], '{["end"] = 1, ["a\\"b"] = 2}'];
        yield [[Lua::var('x')], '{x}'];
    }

    public function testExpressionIdentityAndImmutability(): void
    {
        $name = Lua::var('module.item');
        self::assertSame($name, Lua::value($name));
        self::assertSame('module.item', (string) $name);
        self::assertSame('module.item.field', (string) $name->field('field'));
        self::assertSame('module.item["end"]', (string) $name->index('end'));
        self::assertSame('module.item(1, "x")', (string) $name->call(1, 'x'));
        self::assertSame('module.item:send(false)', (string) $name->method('send', false));
        self::assertSame('module.item', (string) $name);
        self::assertSame('factory().field', (string) Lua::call('factory')->field('field'));
        self::assertSame('factory()(2)', (string) Lua::call('factory')->call(2));
        self::assertSame('factory()[1]', (string) Lua::call('factory')->index(1));
        self::assertSame('factory():send()', (string) Lua::call('factory')->method('send'));
        self::assertSame('({}).field', (string) Lua::value([])->field('field'));
        self::assertSame('({})[1]', (string) Lua::value([])->index(1));
        self::assertSame('("hello"):upper()', (string) Lua::value('hello')->method('upper'));
        self::assertSame('(trusted)(1)', (string) Expression::raw('trusted')->call(1));
        self::assertSame("module.item = 3;\n", (string) new Block($name->assign(3)));
        self::assertSame("print(1);\n", (string) new Block(Lua::call('print', 1)->statement()));
    }

    #[DataProvider('binaryOperators')]
    public function testBinaryOperators(string $operator): void
    {
        self::assertSame('(x '.$operator.' 2)', (string) Lua::var('x')->binary($operator, 2));
    }

    public static function binaryOperators(): iterable
    {
        foreach (['+', '-', '*', '/', '%', '^', '..', '<', '>', '<=', '>=', '==', '~=', 'and', 'or'] as $operator) {
            yield [$operator];
        }
    }

    #[DataProvider('unaryOperators')]
    public function testUnaryOperators(string $operator): void
    {
        self::assertSame('('.$operator.' x)', (string) Lua::var('x')->unary($operator));
    }

    public static function unaryOperators(): iterable
    {
        yield ['-'];
        yield ['not'];
        yield ['#'];
    }

    public function testGroupingAndFunctionExpression(): void
    {
        self::assertSame('((2 + 3) * 4)', (string) Lua::value(2)->binary('+', 3)->binary('*', 4));
        self::assertSame('(2 ^ (3 ^ 2))', (string) Lua::value(2)->binary('^', Lua::value(3)->binary('^', 2)));
        self::assertSame("function (x)\n    return x;\nend", (string) Expression::function(['x'], new Block(Statement::return(Lua::var('x')))));
        self::assertSame("function ()\nend", (string) Expression::function([], new Block));
    }

    #[DataProvider('invalidExpressions')]
    public function testInvalidExpressionsAreRejected(string $case): void
    {
        $this->expectException(InvalidArgumentException::class);
        if ($case === 'operator') {
            $this->expectExceptionMessage('Unsupported Lua binary operator: ; print(1)');
        }
        if ($case === 'unary') {
            $this->expectExceptionMessage('Unsupported Lua unary operator: ~');
        }
        if ($case === 'object') {
            $this->expectExceptionMessage('Unsupported Lua value: stdClass');
        }
        match ($case) {
            'operator'             => Lua::value(1)->binary('; print(1)', 2),
            'unary'                => Lua::value(1)->unary('~'),
            'assignment'           => Lua::value(1)->assign(2),
            'call-assignment'      => Lua::call('f')->assign(2),
            'statement'            => Lua::var('x')->statement(),
            'value-statement'      => Lua::value(1)->statement(),
            'nil-key'              => Lua::var('x')->index(null),
            'object'               => Lua::value(new \stdClass),
            'infinity'             => Lua::value(INF),
            'negative-infinity'    => Lua::value(-INF),
            'nan'                  => Lua::value(NAN),
            'duplicate-parameters' => Expression::function(['x', 'x'], new Block),
            'keyed-parameters'     => Expression::function([1 => 'x'], new Block),
            'function-break'       => Expression::function([], new Block(Statement::break())),
            'invalid-parameter'    => Expression::function(['end'], new Block),
        };
    }

    public static function invalidExpressions(): iterable
    {
        foreach (['operator', 'unary', 'assignment', 'call-assignment', 'statement', 'value-statement', 'nil-key', 'object', 'infinity', 'negative-infinity', 'nan', 'duplicate-parameters', 'keyed-parameters', 'function-break', 'invalid-parameter'] as $case) {
            yield [$case];
        }
    }

    #[DataProvider('invalidNames')]
    public function testInvalidIdentifiers(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        Lua::var($name);
    }

    public static function invalidNames(): iterable
    {
        foreach (['', '1x', 'a b', 'a..b', 'a.', '.a', "a\n", 'a-b', 'é', 'x;os.exit()', 'and', 'break', 'do', 'else', 'elseif', 'end', 'false', 'for', 'function', 'goto', 'if', 'in', 'local', 'nil', 'not', 'or', 'repeat', 'return', 'then', 'true', 'until', 'while'] as $name) {
            yield [$name];
        }
    }

    public function testValidIdentifiersAndCaseSensitivity(): void
    {
        foreach (['_', '_VERSION', 'a0', 'A', 'And', 'module.value'] as $name) {
            self::assertSame($name, (string) Lua::var($name));
        }
    }

    public function testIdentifierErrorNamesTheInvalidIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Lua identifier: invalid-name');
        Lua::var('invalid-name');
    }

    public function testDepthLimitAndRecursiveArrays(): void
    {
        $value = 'leaf';
        for ($i = 0; $i < 64; $i++) {
            $value = [$value];
        }
        self::assertSame(str_repeat('{', 64).'"leaf"'.str_repeat('}', 64), (string) Lua::value($value));
        $this->expectException(InvalidArgumentException::class);
        Lua::value([$value]);
    }

    public function testRecursiveArrayTerminates(): void
    {
        $value = [];
        $value[] = &$value;
        $this->expectException(InvalidArgumentException::class);
        Lua::value($value);
    }

    public function testFloatsRoundTripRegardlessOfPhpPrecision(): void
    {
        $previous = ini_set('serialize_precision', '3');

        try {
            foreach ([0.1, 1.2345678901234567, PHP_FLOAT_MIN, PHP_FLOAT_MAX, 5e-324] as $value) {
                self::assertSame($value, (float) Lua::value($value)->toLua());
            }
        } finally {
            ini_set('serialize_precision', $previous);
        }
    }
}
