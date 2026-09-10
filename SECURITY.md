# Security

Report security issues privately to **bliz48rus@gmail.com**. Include package,
PHP, and Lua versions, a minimal reproduction, and expected versus actual output.
Please avoid public reports until a fix or mitigation is available.

The structured API quotes literal values and validates identifiers and operators.
This prevents those literal values from being interpreted as Lua source. It does
not limit which functions your generated program can invoke or what those functions
can do. Validate application permissions and allowed operations separately.

`Expression::raw()` and `Script::raw()` accept trusted code only. Using them with
untrusted source, or executing untrusted Lua without an appropriate host isolation
model, is outside this package's security boundary. The package itself never
executes Lua or accesses application data.

The first release is in preparation; there are no supported tagged releases yet.
