<!-- aikit v0.14.0 - managed file, do not edit -->

# Pest

Testing rules for repos using Pest. Apply when writing or editing tests.

- Write tests as Pest `it()` blocks: `it('rejects expired tokens', function (): void { ... });`. No PHPUnit test classes, no `test()`.
- Convert class-based stubs (e.g. from `php artisan make:test`) to `it()` before filling them in.
- Every bug fix ships with a regression test that fails without the fix.
- While working, run only the affected tests (`--filter=...`); run the full suite once before push, or when asked.
- Test names and code in English.
