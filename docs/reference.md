# API reference

See the [README](../README.md) for installation and a quick start.

Import `Cosmira\Lua\Lua` for the examples below.

## Expressions that read like expressions

```php
$score = Lua::var('score');
$eligible = $score->greaterThanOrEqual(80)->and(Lua::var('active'));

$score->add(5)->multiply(2);              // ((score + 5) * 2)
Lua::value('Hello, ')->concat(Lua::var('name'));
Lua::var('items')->length();             // (# items)
Lua::var('ready')->not();                // (not ready)
```

Expressions are immutable. Every operation returns a new expression, and grouping
preserves the order you wrote, including subtraction and exponentiation.

| Operation | Methods |
| --- | --- |
| Arithmetic | `add`, `subtract`, `multiply`, `divide`, `modulo`, `power`, `negate` |
| Comparison | `equals`, `notEquals`, `greaterThan`, `greaterThanOrEqual`, `lessThan`, `lessThanOrEqual` |
| Logic | `and`, `or`, `not` |
| Strings and tables | `concat`, `length` |

## Variables, tables, and calls

```php
$config = Lua::var('config');

Lua::script()
    ->local('config', [])
    ->set($config->field('timeout'), 30)
    ->set($config->index('end'), 'arbitrary keys work too')
    ->call('print', $config->field('timeout'));
```

`->set('count', 1)` assigns an existing local or a global, following Lua's scope
rules. `->local('count', 1)` declares a local. Dotted names such as
`Lua::var('app.settings')` are supported.

For a method call with an implicit `self`:

```php
Lua::script()->method(Lua::var('client'), 'send', 'hello');
// client:send("hello");
```

For a method result, use `Lua::var('client')->method('read')`. Call an expression
with `->call(...)`, for example `Lua::var('callbacks')->index(1)->call('hello')`.
Dot calls and colon calls retain their distinct Lua semantics.

## Functions and decisions

Nested callbacks describe a body. They run once while building the script,
receive a fresh `Script`, and do not need to return anything.

```php
use Cosmira\Lua\Lua;
use Cosmira\Lua\Script;

$score = Lua::var('score');

echo Lua::script()
    ->localFunction('grade', ['score'], function (Script $lua) use ($score) {
        $lua->if(
            $score->greaterThanOrEqual(80),
            fn (Script $lua) => $lua->return('passed'),
        );

        $lua->return('try again');
    })
    ->call('print', Lua::call('grade', 90));
```

```lua
local function grade(score)
    if (score >= 80) then
        return "passed";
    end
    return "try again";
end
print(grade(90));
```

`->function()` declares a global or dotted function; `->localFunction()` declares
one in the current scope. Parent tables for dotted names must already exist.
`Lua::function(['name'], $body)` creates an anonymous function expression using
the same callback syntax. Parameter names must be unique.

`->if($condition, $then, $otherwise)` accepts an optional third callback for `else`.
Nest another `->if()` there for additional branches. Conditions are expressions;
use `Lua::value(true)` for a literal condition. Both supplied PHP callbacks run
while building their branches; Lua evaluates the condition when the generated
script executes. `->return()` accepts zero or more
values, and normal Lua multiple-return behavior is preserved.

## Loops and scopes

```php
use Cosmira\Lua\Range;

Lua::script()->for('i', new Range(1, 10), function (Script $lua) {
    $lua->call('print', Lua::var('i'));
});
```

A range accepts numbers or expressions. Its step defaults to 1; use
`new Range(10, 1, step: -1)` to count down. A literal zero step is rejected.

```php
Lua::script()->forEach(
    ['key', 'value'],
    Lua::call('pairs', Lua::var('items')),
    fn (Script $lua) => $lua->call('print', Lua::var('key'), Lua::var('value')),
);
```

Also available: `->while($condition, $body)`, `->repeat($body, $until)`,
`->scope($body)` for a `do … end` scope, and `->break()` to leave a loop.
`->comment($text)` safely prefixes every line of a comment.

`return` and `break` must end their block. Put an early return inside an `if`
or explicit scope. Rendering rejects `break` outside a loop, including inside
functions declared within a loop.

## Predictable output

Scripts accumulate statements and return themselves for chaining, so ordinary
multi-line callbacks work. Use `clone $script` to branch an existing script.
Captured bodies are snapshots: changing a retained child builder cannot change
an already assembled parent. Rendering is repeatable and does not consume the
script. Use `->toLua()` or cast to string.

Output uses four spaces, LF line endings, and a final newline for nonempty scripts.
Semicolons separate simple statements, preventing a parenthesized call on the next
line from accidentally continuing the previous expression.

| PHP value | Lua representation |
| --- | --- |
| `null` | `nil` |
| Booleans | `true` / `false` |
| Finite numbers | Numeric literals |
| Strings | Quoted, byte-preserving strings |
| List arrays | Tables indexed from 1 |
| Other arrays | Tables preserving keys and insertion order |
| Expressions | Source expressions |

Empty arrays become `{}`. Quotes, backslashes, and table keys are escaped.
Control and non-ASCII bytes use three-digit decimal escapes, preserving UTF-8,
NUL bytes, and arbitrary binary input. Strings are byte sequences: the builder does
not detect encodings, transcode, validate UTF-8, or normalize Unicode. Windows-1251,
Shift-JIS, UTF-16, and malformed UTF-8 are preserved exactly, including BOMs. Convert
text to the encoding your consumer expects before passing it to the builder.
The same rules apply to table keys: visually identical strings with different byte
sequences remain distinct. Lua string length counts bytes, not characters or emoji.

Floats use 17 significant digits independent of PHP precision settings and locale;
output may be longer than the PHP input.

Lua's data model still applies: `nil` removes table entries, holes have no reliable
`#` length, and Lua 5.1's usual doubles cannot represent every 64-bit PHP integer
exactly. Pass identifiers requiring exact decimal digits as strings. PHP converts
some numeric string array keys before the builder sees them.

Invalid identifiers, unsupported values (objects, resources, NaN, infinity), and
arrays exceeding 64 nesting levels throw `InvalidArgumentException`. Wrong PHP
argument types produce `TypeError`. Names accept ASCII identifiers and reject
Lua keywords, including `goto`. Use `index()` for arbitrary table keys.

## Trusted source and compatibility

Generated syntax targets the common **Lua 5.1–5.4** subset, including **LuaJIT**.
The package generates source; it does not parse, execute, or sandbox Lua.

For existing code or newer language features, `->raw($source)` inserts trusted
statements. `Expression::raw($source)` inserts a trusted expression. These escape
hatches do not validate syntax or control flow. Never interpolate untrusted input
into them; pass values through the normal API instead. Identifiers select code and
should come from the application, not unrestricted user input.

The structured API covers the constructs documented above. Varargs, multiple
assignment, `goto`, bitwise operators, and Lua 5.4 variable attributes require
trusted raw source. Execution, filesystem writes, and application permissions
remain with the caller.
