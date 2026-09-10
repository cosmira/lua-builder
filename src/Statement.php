<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;

/** @internal */
final readonly class Statement
{
    private function __construct(
        private string $head,
        private ?Block $body = null,
        private string $tail = 'end',
        private ?Block $otherwise = null,
        private StatementKind $kind = StatementKind::Simple,
    ) {}

    /** Insert trusted Lua statements. The caller owns their syntax and control flow. */
    public static function raw(string $code): self
    {
        return new self($code);
    }

    public static function local(string $name, mixed $value = null): self
    {
        return new self('local '.Identifier::validate($name).' = '.Expression::value($value).';');
    }

    public static function return(mixed ...$values): self
    {
        $code = implode(', ', array_map(static fn (mixed $value): string => (string) Expression::value($value), $values));

        return new self('return'.($values === [] ? '' : ' '.$code).';', kind: StatementKind::Return);
    }

    public static function break(): self
    {
        return new self('break;', kind: StatementKind::Break);
    }

    public static function comment(string $text): self
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));

        return new self(implode("\n", array_map(static fn (string $line): string => '-- '.$line, $lines)));
    }

    public static function if(Expression $condition, Block $then, ?Block $otherwise = null): self
    {
        return new self('if '.$condition.' then', $then, otherwise: $otherwise);
    }

    public static function while(Expression $condition, Block $body): self
    {
        return new self('while '.$condition.' do', $body, kind: StatementKind::Loop);
    }

    public static function repeat(Block $body, Expression $until): self
    {
        return new self('repeat', $body, 'until '.$until.';', kind: StatementKind::Loop);
    }

    public static function forRange(string $name, mixed $from, mixed $to, Block $body, mixed $step = 1): self
    {
        return new self('for '.Identifier::validate($name).' = '.Expression::value($from).', '.Expression::value($to).', '.Expression::value($step).' do', $body, kind: StatementKind::Loop);
    }

    /** @param array<array-key, string> $names */
    public static function forEach(array $names, Expression $iterator, Block $body): self
    {
        if ($names === []) {
            throw new InvalidArgumentException('A generic for loop needs at least one variable.');
        }

        return new self('for '.Identifier::parameters($names).' in '.$iterator.' do', $body, kind: StatementKind::Loop);
    }

    /** @param list<string> $parameters */
    public static function function(string $name, array $parameters, Block $body): self
    {
        return new self('function '.Expression::name($name).'('.Identifier::parameters($parameters).')', $body, kind: StatementKind::Function);
    }

    /** @param list<string> $parameters */
    public static function localFunction(string $name, array $parameters, Block $body): self
    {
        return new self('local function '.Identifier::validate($name).'('.Identifier::parameters($parameters).')', $body, kind: StatementKind::Function);
    }

    public static function scope(Block $body): self
    {
        return new self('do', $body);
    }

    /** @internal */
    public function terminatesBlock(): bool
    {
        return in_array($this->kind, [StatementKind::Return, StatementKind::Break], true);
    }

    /** @internal */
    public function render(bool $insideLoop): string
    {
        if ($this->kind === StatementKind::Break && ! $insideLoop) {
            throw new InvalidArgumentException('break must be inside a loop in the same function.');
        }

        if ($this->body === null) {
            return $this->head."\n";
        }

        $bodyInsideLoop = match ($this->kind) {
            StatementKind::Loop     => true,
            StatementKind::Function => false,
            default                 => $insideLoop,
        };

        $code = $this->head."\n".$this->body->renderIndented($bodyInsideLoop);

        if ($this->otherwise !== null) {
            $code .= "else\n".$this->otherwise->renderIndented($insideLoop);
        }

        return $code.$this->tail."\n";
    }
}
