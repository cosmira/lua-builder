# Contributing

Keep the public API small, framework-independent, and useful outside the original
application. Prefer an executable example over a new abstraction. Generated code
should be readable, correctly grouped, and safe when values contain arbitrary bytes.

Maintained by [Alexandr Chernyaev](https://github.com/tabuna) in the
[Cosmira organization](https://github.com/cosmira).

## Working locally

1. Install PHP 8.2+, Composer, and a Lua interpreter.
2. Run `composer install`.
3. Add a regression test for the expected behavior or error.
4. Make the smallest coherent change and run `composer format`.
5. Run `LUA_BIN=lua5.1 composer check` and `LUA_BIN=lua5.4 composer test:runtime`.

Use PHPUnit classes. Unit tests assert public API behavior and diagnostic failures.
Runtime tests execute generated programs in a child process with a timeout and
check their result. Keep tests deterministic and independent of run order.
Add runtime checks for changes to Lua semantics, quoting, grouping, and control flow.

100% coverage describes executed source lines, not a proof of correctness. Mutation
tests supplement it. Strengthen tests or simplify redundant code when mutants
survive; do not exclude source or disable mutators to improve the score. If a
mutant is truly equivalent, explain the equivalence before changing a gate.

`composer analyse` runs PHPStan; `composer refactor:check` checks Rector without
editing files. `composer refactor` applies its suggestions. Update README examples
and CHANGELOG for public behavior changes. Keep all tools in `require-dev`.

## Repository map

- `src/`: the public PHP API and internal Lua rendering.
- `tests/Unit/`: output, validation, and composition contracts.
- `tests/Runtime/`: generated programs executed by real Lua interpreters.
- `examples/`: runnable examples exercised by the runtime suite.
- `docs/reference.md`: API details, value semantics, and compatibility limits.
- `tools/coverage.php`: the coverage gate used locally and in CI.

## Compatibility

The structured API emits the shared Lua 5.1–5.4 syntax. Runtime tests also cover
LuaJIT. Do not introduce newer syntax into default output. Do not silently change
list indexing, nil handling, number precision, method receivers, or multiple-return
semantics. Raw source is caller-owned and outside structured validation.

The public surface is `Lua`, `Script`, `Expression`, and `Range`, except methods
marked `@internal`. Other classes are implementation details. Before 1.0, breaking
changes require a minor release and migration notes; after 1.0, use a major release.

## Preparing a release

- Review changes, documentation examples, API compatibility, and dependency audit.
- Require all GitHub Actions jobs to pass on the commit being released.
- Review the coverage and Infection artifacts, not only their job status.
- Run the examples and install the package into a clean consumer with `--no-dev`.
- Update CHANGELOG with the version, date, and any migration instructions.
- Create an annotated SemVer tag and GitHub release after maintainer approval.
- Submit the repository to Packagist for the first release and configure updates.

Publishing commits does not automatically create a release or Packagist entry.
Maintainers own version tags and release publication.
