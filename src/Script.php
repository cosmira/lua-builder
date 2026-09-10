<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;
use Stringable;

/** A mutable script builder. Expressions and the assembled blocks are immutable. */
final class Script implements Stringable
{
    /** @var list<Statement> */
    private array $statements = [];

    public function local(string $name, mixed $value = null): self
    {
        return $this->add(Statement::local($name, $value));
    }

    public function set(string|Expression $variable, mixed $value): self
    {
        $reference = is_string($variable) ? Lua::var($variable) : $variable;

        return $this->add($reference->assign($value));
    }

    public function call(string|Expression $function, mixed ...$arguments): self
    {
        $reference = is_string($function) ? Lua::var($function) : $function;

        return $this->add($reference->call(...$arguments)->statement());
    }

    public function method(Expression $receiver, string $method, mixed ...$arguments): self
    {
        return $this->add($receiver->method($method, ...$arguments)->statement());
    }

    public function return(mixed ...$values): self
    {
        return $this->add(Statement::return(...$values));
    }

    public function comment(string $text): self
    {
        return $this->add(Statement::comment($text));
    }

    public function break(): self
    {
        return $this->add(Statement::break());
    }

    /**
     * @param callable(self): mixed        $then
     * @param (callable(self): mixed)|null $otherwise
     */
    public function if(Expression $condition, callable $then, ?callable $otherwise = null): self
    {
        return $this->add(Statement::if($condition, self::body($then), $otherwise === null ? null : self::body($otherwise)));
    }

    /** @param callable(self): mixed $body */
    public function while(Expression $condition, callable $body): self
    {
        return $this->add(Statement::while($condition, self::body($body)));
    }

    /** @param callable(self): mixed $body */
    public function repeat(callable $body, Expression $until): self
    {
        return $this->add(Statement::repeat(self::body($body), $until));
    }

    /** @param callable(self): mixed $body */
    public function for(string $variable, Range $range, callable $body): self
    {
        return $this->add(Statement::forRange($variable, $range->from, $range->to, self::body($body), $range->step));
    }

    /**
     * @param array<array-key, string> $variables
     * @param callable(self): mixed    $body
     */
    public function forEach(array $variables, Expression $iterator, callable $body): self
    {
        return $this->add(Statement::forEach($variables, $iterator, self::body($body)));
    }

    /**
     * @param list<string>          $parameters
     * @param callable(self): mixed $body
     */
    public function function(string $name, array $parameters, callable $body): self
    {
        return $this->add(Statement::function($name, $parameters, self::body($body)));
    }

    /**
     * @param list<string>          $parameters
     * @param callable(self): mixed $body
     */
    public function localFunction(string $name, array $parameters, callable $body): self
    {
        return $this->add(Statement::localFunction($name, $parameters, self::body($body)));
    }

    /** @param callable(self): mixed $body */
    public function scope(callable $body): self
    {
        return $this->add(Statement::scope(self::body($body)));
    }

    /** Insert trusted Lua statements without validation or escaping. */
    public function raw(string $source): self
    {
        return $this->add(Statement::raw($source));
    }

    public function toLua(): string
    {
        return $this->toBlock()->toLua();
    }

    public function __toString(): string
    {
        return $this->toLua();
    }

    /** @internal */
    public function toBlock(): Block
    {
        return new Block(...$this->statements);
    }

    /** @param callable(self): mixed $callback */
    private static function body(callable $callback): Block
    {
        $script = new self;
        $callback($script);

        return $script->toBlock();
    }

    private function add(Statement $statement): self
    {
        $last = $this->statements[count($this->statements) - 1] ?? null;

        if ($last?->terminatesBlock()) {
            throw new InvalidArgumentException('return and break must be the final statement in a block.');
        }

        $this->statements[] = $statement;

        return $this;
    }
}
