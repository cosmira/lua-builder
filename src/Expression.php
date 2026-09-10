<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;
use Stringable;

final readonly class Expression implements Stringable
{
    private function __construct(private string $code, private ExpressionKind $kind = ExpressionKind::Value) {}

    public static function value(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new self(Literal::encode($value));
    }

    public static function name(string $name): self
    {
        return new self(implode('.', array_map(Identifier::validate(...), explode('.', $name))), ExpressionKind::Reference);
    }

    /** Insert trusted Lua source without validation or escaping. */
    public static function raw(string $code): self
    {
        return new self($code);
    }

    /** @param list<string> $parameters */
    public static function function(array $parameters, Block $body): self
    {
        return new self('function ('.Identifier::parameters($parameters).")\n".$body->renderIndented(false).'end');
    }

    public function field(string $name): self
    {
        return new self($this->prefix().'.'.Identifier::validate($name), ExpressionKind::Reference);
    }

    public function index(mixed $key): self
    {
        if ($key === null) {
            throw new InvalidArgumentException('A table key cannot be nil.');
        }

        return new self($this->prefix().'['.self::value($key).']', ExpressionKind::Reference);
    }

    public function call(mixed ...$arguments): self
    {
        return new self($this->prefix().'('.self::arguments($arguments).')', ExpressionKind::Call);
    }

    public function method(string $name, mixed ...$arguments): self
    {
        return new self($this->prefix().':'.Identifier::validate($name).'('.self::arguments($arguments).')', ExpressionKind::Call);
    }

    /** @internal */
    public function binary(string $operator, mixed $right): self
    {
        if (! in_array($operator, ['+', '-', '*', '/', '%', '^', '..', '<', '>', '<=', '>=', '==', '~=', 'and', 'or'], true)) {
            throw new InvalidArgumentException('Unsupported Lua binary operator: '.$operator);
        }

        $left = str_starts_with($this->code, '-') ? '('.$this->code.')' : $this->code;

        return new self('('.$left.' '.$operator.' '.self::value($right).')');
    }

    /** @internal */
    public function unary(string $operator): self
    {
        if (! in_array($operator, ['-', 'not', '#'], true)) {
            throw new InvalidArgumentException('Unsupported Lua unary operator: '.$operator);
        }

        return new self('('.$operator.' '.$this.')');
    }

    public function add(mixed $value): self
    {
        return $this->binary('+', $value);
    }

    public function subtract(mixed $value): self
    {
        return $this->binary('-', $value);
    }

    public function multiply(mixed $value): self
    {
        return $this->binary('*', $value);
    }

    public function divide(mixed $value): self
    {
        return $this->binary('/', $value);
    }

    public function modulo(mixed $value): self
    {
        return $this->binary('%', $value);
    }

    public function power(mixed $value): self
    {
        return $this->binary('^', $value);
    }

    public function concat(mixed $value): self
    {
        return $this->binary('..', $value);
    }

    public function lessThan(mixed $value): self
    {
        return $this->binary('<', $value);
    }

    public function greaterThan(mixed $value): self
    {
        return $this->binary('>', $value);
    }

    public function lessThanOrEqual(mixed $value): self
    {
        return $this->binary('<=', $value);
    }

    public function greaterThanOrEqual(mixed $value): self
    {
        return $this->binary('>=', $value);
    }

    public function equals(mixed $value): self
    {
        return $this->binary('==', $value);
    }

    public function notEquals(mixed $value): self
    {
        return $this->binary('~=', $value);
    }

    public function and(mixed $value): self
    {
        return $this->binary('and', $value);
    }

    public function or(mixed $value): self
    {
        return $this->binary('or', $value);
    }

    public function negate(): self
    {
        return $this->unary('-');
    }

    public function not(): self
    {
        return $this->unary('not');
    }

    public function length(): self
    {
        return $this->unary('#');
    }

    /** @internal */
    public function assign(mixed $value): Statement
    {
        if ($this->kind !== ExpressionKind::Reference) {
            throw new InvalidArgumentException('Only a variable or table field can be assigned.');
        }

        return Statement::raw($this.' = '.self::value($value).';');
    }

    /** @internal */
    public function statement(): Statement
    {
        if ($this->kind !== ExpressionKind::Call) {
            throw new InvalidArgumentException('Only a function call can be used as a statement.');
        }

        return Statement::raw($this.';');
    }

    public function toLua(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    private function prefix(): string
    {
        return $this->kind === ExpressionKind::Value ? '('.$this->code.')' : $this->code;
    }

    /** @param array<array-key, mixed> $arguments */
    private static function arguments(array $arguments): string
    {
        return implode(', ', array_map(static fn (mixed $value): string => (string) self::value($value), $arguments));
    }
}
