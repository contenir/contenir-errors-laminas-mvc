# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Infection mutation testing in CI, MSI 100%.

## [2.0.0] - Unreleased

The major version marks the move to PHP 8.3+ and the php-db QA toolchain
shared by all Contenir 2.x packages. See [UPGRADE-2.0.md](UPGRADE-2.0.md)
for the BC breaks.

### Changed

- `LICENSE` names Contenir as the copyright holder, in line with the other
  Contenir packages, and uses the standard MIT wording.
- Requires PHP 8.3, 8.4 or 8.5. PHP 8.1 and 8.2 are no longer supported.
- `contenir/errors` constraint is `^0.1 || ^2.0`.
- Minimum Laminas versions raised to the first releases supporting PHP 8.3:
  laminas-view 2.32, laminas-http 2.19, laminas-eventmanager 3.11 and
  laminas-servicemanager 3.22; laminas-mvc 3.8, because 3.7 raises PHP 8.4
  deprecations.
  `psr/container` is now required explicitly.
- `Module` is `final`. Its constants, and those of `ConfigProvider` and
  `ErrorListener`, are typed.
- `ConfigProvider`, `ErrorListenerFactory` and `ErrorListener` are
  `readonly` classes (all were already `final`).
- The URI in log messages is read only from a `Laminas\Http\Request`;
  other request types log an empty URI, as requests without `getUri()`
  already did.

### Fixed

- `ErrorListenerFactory` threw a `TypeError` when `config[errors]` (or the
  `config` service) was not an array. It now falls back to the defaults.
- A `null` or empty `errors.view_template` produced a `ViewModel` with an
  empty template name, which the renderer cannot resolve. It now falls back
  to `contenir/errors/fault`.
- A non-scalar `title` or `body` in `errors.pages` raised "Array to string
  conversion" (or threw, for an object). It now reads as an empty string.
- The README said `errors.file` was required and that both events used
  priority 100. Neither has been true since 0.1.1 and 0.3.1 respectively.

### Added

- Continuous integration on PHP 8.3, 8.4 and 8.5 against lowest, locked and
  latest dependencies, with coverage reported to Codecov.
- Separate unit and integration test suites, with 100% line and branch
  coverage, plus integration tests for the `ExceptionStrategy` ordering and
  for the shipped `fault.phtml` template.

### Removed

- `squizlabs/php_codesniffer` and `phpcs.xml`, replaced by Mago via
  `php-db/phpdb-qa-tools`.
- The local path and VCS repository entries in `composer.json`;
  `contenir/errors` resolves from Packagist.

## [0.3.1]

- Attach to `EVENT_RENDER_ERROR` at priority -100, after Laminas's
  `ExceptionStrategy` has set the 500.

## [0.3.0]

- Seed built-in 403, 404 and 500 pages (`ConfigProvider::DEFAULT_PAGES`)
  and refresh the bundled `fault.phtml`.

## [0.2.0]

- Trigger `pagecache.disable` when the listener engages on a 4xx/5xx.

## [0.1.1]

- `ErrorListenerFactory` builds the repository from the merged
  `config[errors][pages]`; `FileRepositoryFactory` and `errors.file` were
  removed.

## [0.1.0]

- Initial release: `Module`, `ConfigProvider`, `ErrorListener` and its
  factory, and the `contenir/errors/fault` template.
