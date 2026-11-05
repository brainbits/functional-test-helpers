# CLAUDE.md

Guidance for working on `brainbits/functional-test-helpers`, a PHP library (Composer type `library`)
that ships PHPUnit test-helper traits for Symfony projects.

## Commands

```bash
composer update                 # no composer.lock is committed (it's gitignored)
vendor/bin/phpcs                # code style (Brainbits standard, src + tests)
vendor/bin/phpcbf               # auto-fix code style
vendor/bin/phpstan analyze      # static analysis, level 6, src + tests
vendor/bin/phpunit              # full test suite
vendor/bin/phpunit tests/HttpClientMock/MockRequestBuilderTest.php
vendor/bin/phpunit --filter testMockRequest
```

CI (`.github/workflows/build.yml`) runs phpcs → phpstan → phpunit against both `--prefer-lowest`
and highest dependencies. A change must work with the lowest versions allowed in `composer.json`,
not just the installed ones. The 7z tests need the `7z` binary on the PATH.

## Layout

- `src/<Feature>/` holds one feature per directory, namespace `Brainbits\FunctionalTestHelpers\<Feature>`.
- `tests/<Feature>/` mirrors it, namespace `Brainbits\FunctionalTestHelpers\Tests\<Feature>`.
- `README.<feature>-trait.md` contains the user-facing docs for each trait. Update it when public behavior changes.
- `UPGRADE-x.0.md` records BC breaks for major versions.

| Feature | Entry point | Notes |
|---|---|---|
| Console | `Console/CommandTrait`, `ApplicationTrait` | Symfony console command testing |
| HttpClientMock | `HttpClientMock/HttpClientMockTrait` | Largest module: `MockRequestBuilder` / `MockResponseBuilder`, `MockRequestMatcher` combining `Matcher/*` (each returns `Hit`/`Mismatch`/`Missing`), `MockRequestResolver`, `CallStack` |
| Request | `Request/RequestTrait`, `RequestBuilder` | Symfony `KernelBrowser` requests, auth/login, multipart |
| Schema | `Schema/SchemaTrait`, `SchemaBuilder`, `DataBuilder` | Doctrine DBAL fixtures. `Strategy/*` handles SQLite (memory or file) and MySQL, each with or without DAMA |
| Snapshot | `Snapshot/SnapshotTrait` | Array/JSON/JSON-LD/XML/HTML/text snapshots in `__snapshots__` dirs |
| Uuid | `Uuid/UuidTrait` | Deterministic UUIDs, UUID assertions |
| ZipContents / SevenZipContents | `*ContentsTrait` | Archive inspection and assertions |

## Conventions

- Every file starts with `declare(strict_types=1);`. Classes are `final` (usually `final readonly`
  for value objects and matchers), and exceptions are small `final` classes per module, under `Exception/` where one exists.
- Use the `Safe\` function variants (`thecodingmachine/safe`) and import functions and constants explicitly
  (`use function ...;`, `use const ...;`). phpcs enforces this.
- Traits carry a `/** @mixin TestCase */` (or `WebTestCase`) docblock and mark their helper methods `final protected`.
  Exception: a helper method added in a minor release is `protected` without `final`, so a test class that already
  has a method with the same name keeps working. Make it `final` in the next major release.
- Traits hook into the test lifecycle through PHPUnit attributes, and **the priorities matter**:
  `RequestTrait` uses `#[Before(12)]` (creates the client/kernel first), and `HttpClientMockTrait`
  uses `#[Before(-12)]` (needs the container). Keep this ordering when adding hooks.
- Only the tests use `#[CoversClass]`, and never on traits.
- Tests of container-dependent traits don't boot a kernel. They override `getContainer()` with a
  hand-built `Symfony\Component\DependencyInjection\Container` (see `tests/HttpClientMock/HttpClientMockTraitTest.php`).
- Most runtime dependencies are optional (`require-dev` + `suggest`). A new optional
  dependency belongs in `require-dev` and `suggest`, with a note saying which trait needs it.
  Don't add hard `require` entries without good reason.
- `src/HttpClientMock/CallbackHandler.php` is excluded from phpstan on purpose (Monolog 2/3 compatibility).
- PHPUnit is not a runtime requirement, so the library runs with whatever PHPUnit version the user's project
  has installed. `conflict` in `composer.json` sets the supported floor: sebastian/diff 8.3, which has the
  `StrictUnifiedDiffOutputBuilder` options used in `Mismatch`. In practice that means PHPUnit 13+, since
  PHPUnit 11 and 12 require diff ^6 and ^7. If the library starts using newer PHPUnit APIs, add a conflict for PHPUnit itself.
- Symfony 8 is the minimum: `conflict` lists every Symfony package used in `src` with `<8.0`, and
  `require-dev` uses `^8.0`. Add a new conflict entry when `src` starts using another Symfony component.

## Environment variables read by the library

- `UPDATE_SNAPSHOTS=1` rewrites snapshot files. `CREATE_SNAPSHOTS=0` fails a test whose snapshot is missing. `SKIP_SNAPSHOT_TESTS` skips snapshot assertions.
- `USE_PRE_INITIALIZED_SCHEMA` skips applying the schema in `SchemaTrait`.

## Commits & releases

- Use Conventional Commits (`feat:`, `fix:`, `chore(deps):`, `test:`, `ci:`, `docs:`). semantic-release
  (`.releaserc`) derives versions from them and releases from `main` once the Build workflow succeeded on it (`release.yaml`, triggered via `workflow_run`), with tags without a `v` prefix.
  `feat`/`fix` trigger a release, and `BREAKING CHANGE` triggers a major release (add an `UPGRADE-x.0.md` entry).
- Dependency updates are handled by Dependabot (`.github/dependabot.yml`), weekly for Composer (`versioning-strategy: widen`, so
  OR constraints like `^2.0 || ^3.0` are extended, not replaced) and GitHub Actions. Its commit prefixes fit semantic-release: runtime deps use `fix` (patch release), dev deps use `chore`, and Actions use `ci`.
- Workflow actions are pinned to a commit SHA with the version as a comment (`uses: "actions/checkout@<sha>" # v4.4.0`).
  Pin new actions the same way. Dependabot updates the SHA and the comment.
