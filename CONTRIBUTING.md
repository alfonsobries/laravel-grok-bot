# Contributing

Contributions are welcome through pull requests on [GitHub](https://github.com/alfonsobries/laravel-grok-bot).

## Pull requests

- **Tests.** Every change ships with a test; a bug fix with one that fails without it.
- **One change per pull request.** Send unrelated changes separately.
- **Docs.** `README.md`, `website/index.html` and `resources/boost/` describe the same API. Change them together.
- **Versioning.** The project follows [SemVer](https://semver.org). Public API changes go in `CHANGELOG.md`.
- **No secrets.** A webhook key must never appear in an exception, a log line, an event or output.

## Checks

```bash
composer test            # Pest
composer test:coverage   # 100% line coverage (needs PCOV or Xdebug)
composer analyse         # PHPStan at max level
composer format          # Pint
```

CI runs the same checks on PHP 8.2 to 8.5 with Laravel 12 and 13, at the lowest and the newest dependencies.
