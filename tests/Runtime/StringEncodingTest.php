<?php

declare(strict_types=1);

namespace Cosmira\Lua\Tests\Runtime;

use Cosmira\Lua\Lua;
use Cosmira\Lua\Script;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class StringEncodingTest extends TestCase
{
    #[DataProvider('encodedStrings')]
    public function testValuesAndNestedTableKeysPreserveTheirBytes(string $bytes): void
    {
        $script = Lua::script()
            ->local('payload', $bytes)
            ->call('io.write', Lua::call('string.format', '%d:', Lua::var('payload')->length()), Lua::var('payload'))
            ->local('entries', [$bytes => ['value' => $bytes]])
            ->forEach(['key', 'entry'], Lua::call('pairs', Lua::var('entries')), fn (Script $lua) => $lua
                ->call('io.write', Lua::var('key'), Lua::var('entry')->field('value')));

        self::assertDoesNotMatchRegularExpression('/[^\x20-\x7e\n]/', $script->toLua());
        self::assertSame(strlen($bytes).':'.$bytes.$bytes.$bytes, $this->execute($script));
    }

    public static function encodedStrings(): iterable
    {
        yield 'empty string' => [''];
        yield 'Russian UTF-8 including yo' => ['Привет, мир! Ёжик и ёлка'];
        yield 'Japanese kanji hiragana katakana' => ['日本語・こんにちは・カタカナ'];
        yield 'Chinese and Korean' => ['中文 / 한국어'];
        yield 'Arabic and Hebrew' => ['مرحبا / שלום'];
        yield 'emoji with skin tone and joiner' => ['👩🏽‍💻'];
        yield 'family emoji' => ['👨‍👩‍👧‍👦'];
        yield 'flag and keycap emoji' => ['🇯🇵 1️⃣'];
        yield 'variation selectors' => ["\u{2764}\u{FE0F} / \u{2764}\u{FE0E}"];
        yield 'combining marks' => ["e\u{0301} / \u{00E9} / カ\u{3099} / ガ"];
        yield 'Unicode separators and directional controls' => ["a\u{2028}b\u{2029}c\u{202E}d\u{2066}e\u{2069}"];
        yield 'UTF-8 BOM stays data' => ["\xEF\xBB\xBFПривет"];
        yield 'Windows-1251 Russian Привет Ёё' => ["\xCF\xF0\xE8\xE2\xE5\xF2 \xA8\xB8"];
        yield 'KOI8-R Russian Привет' => ["\xF0\xD2\xC9\xD7\xC5\xD4"];
        yield 'Shift-JIS Japanese 日本語' => ["\x93\xFA\x96\x7B\x8C\xEA"];
        yield 'EUC-JP Japanese 日本語' => ["\xC6\xFC\xCB\xDC\xB8\xEC"];
        yield 'ISO-8859-1 café' => ["caf\xE9"];
        yield 'Windows-1252 quotes and euro' => ["\x93caf\xE9\x94 \x80"];
        yield 'UTF-16LE BOM A and grinning face' => ["\xFF\xFE\x41\x00\x3D\xD8\x00\xDE"];
        yield 'UTF-16BE BOM A and grinning face' => ["\xFE\xFF\x00\x41\xD8\x3D\xDE\x00"];
        yield 'UTF-32LE BOM A and grinning face' => ["\xFF\xFE\x00\x00\x41\x00\x00\x00\x00\xF6\x01\x00"];
        yield 'UTF-32BE BOM A and grinning face' => ["\x00\x00\xFE\xFF\x00\x00\x00\x41\x00\x01\xF6\x00"];
        yield 'isolated UTF-8 continuation bytes' => ["\x80\xBF"];
        yield 'truncated UTF-8 emoji' => ["\xF0\x9F\x98"];
        yield 'overlong UTF-8 encoding' => ["\xC0\xAF"];
        yield 'UTF-8 encoded surrogate' => ["\xED\xA0\x80"];
        yield 'code point beyond Unicode range' => ["\xF4\x90\x80\x80"];
        yield 'literal escapes are not interpreted' => ['\u{1F680}\xF0\240\n&#x20;'];
        yield 'mixed encodings quotes and source-like text' => ["Привет \xCF\xF0 日本語 👩🏽‍💻\0\"; error('injected'); --\r\n[=[end]=]\\123"];
    }

    public function testDecimalEscapesCannotConsumeFollowingDigits(): void
    {
        $bytes = '';
        foreach (range(0, 255) as $byte) {
            $bytes .= chr($byte).'0123456789';
        }

        self::assertSame($bytes, $this->execute(Lua::script()->call('io.write', $bytes)));
    }

    #[DataProvider('distinctStrings')]
    public function testVisuallySimilarStringsRemainDistinctTableKeys(string $first, string $second): void
    {
        $script = Lua::script()
            ->local('entries', [$first => 'first', $second => 'second'])
            ->set(Lua::var('entries')->index($first), 'updated')
            ->call('print', Lua::value($first)->equals($second),
                Lua::var('entries')->index($first), Lua::var('entries')->index($second));

        self::assertSame("false\tupdated\tsecond\n", $this->execute($script));
    }

    public static function distinctStrings(): iterable
    {
        yield 'Unicode normalization' => ["\u{00E9}", "e\u{0301}"];
        yield 'emoji presentation' => ["\u{2764}", "\u{2764}\u{FE0F}"];
        yield 'UTF-8 versus Windows-1251' => ['Привет', "\xCF\xF0\xE8\xE2\xE5\xF2"];
    }

    public function testMultilingualCommentsDoNotChangeProgramExecution(): void
    {
        $comment = "Привет 👩🏽‍💻\r\n日本語\rerror('injected')\n\u{2028}\u{2029}]] -- العربية";

        self::assertSame('safe', $this->execute(Lua::script()->comment($comment)->call('io.write', 'safe')));
    }

    private function execute(Script $script): string
    {
        $process = new Process([getenv('LUA_BIN') ?: 'lua', '-']);
        $process->setInput($script->toLua());
        $process->setTimeout(5);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput()."\n".$script);

        return $process->getOutput();
    }
}
