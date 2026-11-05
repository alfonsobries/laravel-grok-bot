# Conventions

- Support every Laravel version in `composer.json` (11, 12, 13) and PHP 8.2+. Use no API
  newer than the oldest supported version; CI runs the full matrix.
- The webhook key never appears in an exception, log line, event or output. Routines are
  named by their config name, or by the URL path when built at runtime.
- `README.md`, `website/index.html` and `resources/boost/` describe the same API. Change
  them together.
