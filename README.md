# Lua Builder

[![Coding Guidelines](https://github.com/cosmira/lua-builder/actions/workflows/code-style.yml/badge.svg)](https://github.com/cosmira/lua-builder/actions/workflows/code-style.yml)
[![Tests](https://github.com/cosmira/lua-builder/actions/workflows/phpunit.yml/badge.svg)](https://github.com/cosmira/lua-builder/actions/workflows/phpunit.yml)
[![Code Coverage](https://github.com/cosmira/lua-builder/actions/workflows/coverage.yml/badge.svg)](https://github.com/cosmira/lua-builder/actions/workflows/coverage.yml)

**Generate Lua scripts from PHP.**

Build configuration files and application-generated scripts from your PHP data.
Compose functions, conditions, and loops while the builder takes care of quoting,
expression grouping, and indentation.

```php
use Cosmira\Lua\Lua;

echo Lua::script()->return([
    'name' => 'Moonlight',
    'enabled' => true,
    'servers' => ['127.0.0.1', '192.168.1.1'],
]);
```

```lua
return {["name"] = "Moonlight", ["enabled"] = true, ["servers"] = {"127.0.0.1", "192.168.1.1"}};
```

Requires **PHP 8.2+**. No framework or runtime dependencies. Generated syntax
targets **Lua 5.1–5.4 and LuaJIT**. The package generates source; your application
decides where to save or execute it.

## Installation

The development version is available directly from GitHub. In your PHP project:

```bash
composer config repositories.cosmira-lua vcs https://github.com/cosmira/lua-builder
composer require cosmira/lua-builder:dev-main
```

Load `vendor/autoload.php` when running the examples outside a framework.
A stable Packagist release has not been published yet.

## Three concepts

| Concept | Example | Meaning |
| --- | --- | --- |
| PHP values | `'hello'`, `42`, `['enabled' => true]` | Data, quoted and encoded automatically |
| Expressions | `Lua::var('score')->greaterThan(80)` | A value Lua computes when the script runs |
| A script | `Lua::script()->call('print', 'hello')` | Commands emitted in order |

Strings always mean data. Use `Lua::var()` for a variable and `Lua::call()` when
you need the result of a function call. The script's `->call()` adds a standalone
call. Start an expression from a literal with `Lua::value()`.

```php
Lua::script()
    ->local('maximum', Lua::call('math.max', 10, 20))
    ->call('print', Lua::var('maximum'));
```

## Functions and conditions

Callbacks describe nested bodies using the same script API:

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

`->if($condition, $then, $otherwise)` generates a Lua conditional. Both supplied
PHP callbacks run while constructing their branches; Lua evaluates the condition
when the generated program executes. The `otherwise` callback is optional.

## Tables and loops

```php
echo Lua::script()
    ->local('services', ['api', 'worker'])
    ->forEach(
        ['index', 'service'],
        Lua::call('ipairs', Lua::var('services')),
        fn (Script $lua) => $lua->call('print', Lua::var('index'), Lua::var('service')),
    );
```

```lua
local services = {"api", "worker"};
for index, service in ipairs(services) do
    print(index, service);
end
```

Expressions are immutable. Scripts accumulate commands and can be cloned to
create independent variants. Literal strings preserve arbitrary bytes, including
quotes, UTF-8, and NUL. Names and operators are validated.

Lua's data model applies: lists start at 1, `nil` removes table entries, and numeric
precision depends on the Lua runtime. Raw source is an explicit escape hatch for
trusted code only. See the [API reference](docs/reference.md) for all operations,
value semantics, errors, and supported syntax.

## Development

```bash
git clone https://github.com/cosmira/lua-builder.git
cd lua-builder
composer install
LUA_BIN=lua5.1 composer test
LUA_BIN=lua5.1 composer check
```

Install PHP, Composer, and Lua first. Set `LUA_BIN` to your interpreter's name or
absolute path; it defaults to `lua`. Runtime tests require a working interpreter.
Coverage requires PCOV or an enabled Xdebug coverage driver.

`composer check` runs Pint, PHPStan, Rector, tests, **100% line coverage**, and
**100% mutation score** gates. GitHub Actions tests PHP 8.2–8.5, Lua 5.1–5.4, and
LuaJIT. Reports are uploaded as CI artifacts. See [CONTRIBUTING.md](CONTRIBUTING.md)
for individual commands, project structure, and release conventions.

## License

MIT. Maintained by [Alexandr Chernyaev](https://github.com/tabuna) and
[Cosmira](https://github.com/cosmira). See [LICENSE.md](LICENSE.md).
