<?php

declare(strict_types=1);

namespace Cosmira\Lua;

use InvalidArgumentException;
use Stringable;

/** @internal */
final readonly class Block implements Stringable
{
    /** @var list<Statement> */
    private array $statements;

    public function __construct(Statement ...$statements)
    {
        $terminated = false;

        foreach ($statements as $statement) {
            if ($terminated) {
                throw new InvalidArgumentException('return and break must be the final statement in a block.');
            }

            $terminated = $statement->terminatesBlock();
        }

        $this->statements = array_values($statements);
    }

    public function append(Statement ...$statements): self
    {
        return new self(...[...$this->statements, ...$statements]);
    }

    public function toLua(): string
    {
        return $this->render(false);
    }

    public function __toString(): string
    {
        return $this->toLua();
    }

    /** @internal */
    public function renderIndented(bool $insideLoop): string
    {
        return (string) preg_replace('/^(.+)/m', '    $1', $this->render($insideLoop));
    }

    private function render(bool $insideLoop): string
    {
        return implode('', array_map(static fn (Statement $statement): string => $statement->render($insideLoop), $this->statements));
    }
}
