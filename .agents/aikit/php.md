<!-- aikit v0.14.0 - managed file, do not edit -->

# PHP

Code-writing rules for PHP files. Apply when writing or editing PHP code. In Laravel apps these rules layer on top of whatever `laravel/boost` provides.

## Types

- Declare `strict_types=1` at the top of every PHP file.
- Add explicit return type declarations to every method.
- Add type hints to every parameter.
- Use union and intersection types when they fit. Avoid `mixed` unless genuinely unbounded.
- Use `readonly` properties when the value doesn't change after construction.

## Syntax

- Use PHP 8 constructor property promotion. Don't leave empty `__construct()` methods.
- Use curly braces for every control structure, even single-line bodies.
- Use `match` instead of `switch` when assigning a value or returning early.
- Use first-class callable syntax (`Foo::bar(...)`) over closures wrapping a single call.
- Use named arguments when a call has multiple booleans or optional params and clarity beats brevity.

## Enums

- Use enum classes for fixed sets of values; don't use string constants on a class.
- TitleCase for enum case keys: `FavoritePerson`, `Monthly`.

## Comments and PHPDoc

- Prefer PHPDoc blocks over inline comments. Inline comments only for non-obvious WHY.
- Use array shape definitions in PHPDoc for structured arrays:

```php
/** @return array{name: string, age: int} */
```

## PHP 8.4+ array helpers

Prefer the built-in helpers over manual loops:

- `array_find` over `foreach` + early return
- `array_find_key` over manual key lookup
- `array_any` / `array_all` over `foreach` + boolean accumulator

## Composer scripts

Prefer the repo's `composer.json` scripts (`composer format`, `composer analyse`, `composer test`) over invoking the underlying tool directly. If a script is missing, add it.

## Avoid

- Mutating function arguments - take immutable inputs, return new values.
- Static state (singletons, statics for caching). Prefer dependency injection.
