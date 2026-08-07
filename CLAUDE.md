# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius plugin (`setono/sylius-meilisearch-plugin`) integrating the Meilisearch search engine into Sylius stores. Targets Sylius 1.14, Symfony ^6.4, PHP >=8.1. The dev toolchain (PHPStan, ECS, Rector, Infection, PHPUnit, sylius/sylius itself) comes via `setono/sylius-plugin-pack`. A full Sylius test application lives in `tests/Application`.

## Commands

```shell
composer analyse            # PHPStan, level max, no baseline
composer check-style        # ECS
composer fix-style          # ECS with --fix
composer phpunit            # Unit test suite only (no external services needed)
vendor/bin/rector process --dry-run   # must be clean; CI enforces without continue-on-error
composer validate --strict && composer normalize --dry-run   # CI enforces both
vendor/bin/phpunit --testsuite Unit --filter testItSortsSizes   # single test
```

After changing code, run `composer fix-style`, `composer analyse`, and `(cd tests/Application && bin/console lint:container)`.

### Functional tests

The Functional suite needs MariaDB/MySQL and Meilisearch with master key `aSampleMasterKey`. Start Meilisearch with `cd tests/Application && docker compose up -d --wait` (or `tests/Application/meilisearch.sh` without Docker; the CI service container is in `.github/workflows/build.yaml`). Keep the compose image version in sync with the CI service containers.

**The compose stack binds Meilisearch to a random host port**, so it can never collide with another Meilisearch you happen to run — a fixed `:7700` meant a neighbouring project's container would answer instead, and the suite would silently index into and search *that* instance. The Symfony CLI resolves the port automatically: because the compose service is named `meilisearch`, it injects `MEILISEARCH_URL` (plus `MEILISEARCH_HOST`/`MEILISEARCH_PORT`) pointing at the published port, which overrides the `MEILISEARCH_URL` in `tests/Application/.env`. The CLI reports the scheme as `tcp://` for a Docker service on a port it does not recognise; `SetonoSyliusMeilisearchExtension::normalizeServerUrl()` already coerces that to `http`, so nothing extra is needed. The `.env` value (`:7700`) stays as the fallback for CI's service container and `meilisearch.sh`.

**This means every command that talks to Meilisearch must run through the Symfony CLI** (`symfony console`, `symfony php`) — plain `bin/console`/`vendor/bin/phpunit` fall back to `:7700` and will miss the container. The CLI matches containers by Docker Compose project name and resolves the project from the *script's* location, so it only finds the stack when the compose file is in scope: `vendor/bin/phpunit` lives at the repo root, so it has to be run from `tests/Application` (`symfony php ../../vendor/bin/phpunit -c ../..`). `composer phpunit-functional` wraps exactly that. Setup chain (from `tests/Application`, with `APP_ENV=test` — the test env ignores `.env.local`, which may hold real cloud credentials):

```shell
symfony console doctrine:database:create
symfony console doctrine:schema:create
symfony console sylius:fixtures:load -n
symfony console cache:clear                          # see the port caveat below
symfony console setono:sylius-meilisearch:index --wait
cd ../.. && composer phpunit-functional
```

Note `APP_ENV`: under the Symfony CLI the value comes from the CLI's own dotenv handling, so PHPUnit's `<env name="APP_ENV">` in `phpunit.xml.dist` does *not* win — pass `APP_ENV=test` explicitly (the composer script does).

**The port changes whenever the container is recreated, and the plugin bakes the URL into the compiled container** (the extension calls `resolveEnvPlaceholders()` at compile time), so run `symfony console cache:clear` after `docker compose up`, or the app keeps calling the previous port. A stale cache fails loudly with a connection error rather than silently hitting another instance.

Note: repeated fixture loads accumulate stale documents in a long-lived local Meilisearch (indexes are only added to, never purged), which can make index-vs-database comparisons drift. `docker compose down && docker compose up -d` resets the instance (the compose service has no volume) — remember it comes back on a new port and empty, so clear the cache and re-run the index command. CI uses a fresh container per run.

### E2E tests (Playwright)

Browser tests for the shop search page and autocomplete widget live in `tests/Application/e2e/*.spec.ts` and run with `(cd tests/Application && yarn e2e)` (single-cell `e2e-tests` job in `build.yaml`). Prerequisites are the same DB/fixtures/index chain as Functional, plus `yarn install && yarn build && bin/console assets:install` and the Symfony CLI. `yarn e2e` auto-starts the app via `e2e/serve.sh`, which resolves `MEILISEARCH_SEARCH_KEY` from the running Meilisearch (the browser queries Meilisearch directly for autocomplete) and runs `symfony serve` in `APP_ENV=test`. The search form is AJAX-driven (`src/Resources/public/js/search.js` swaps the `#search-form` node + `history.pushState`), so specs wait on `toHaveURL(...)` after each interaction. Note: the untracked `.mcp.json` configures the Playwright MCP server for agent-driven browsing — unrelated to this suite.

### Running the test app locally

Serve the app with `symfony serve` from `tests/Application` — not PHP's built-in server (`php -S` can hide real env vars from Symfony Dotenv depending on `variables_order`, so `.env.local` silently wins over exported overrides). Running through the Symfony CLI is also what injects `MEILISEARCH_URL` for the containerized Meilisearch on its random port (see Functional tests above). `e2e/serve.sh` wraps `symfony serve` for the e2e suite; it `eval`s `symfony var:export` so its own health check and `MEILISEARCH_SEARCH_KEY` lookup hit the same instance the app will use (rewriting the CLI's `tcp://` scheme, which curl cannot use).

### Dependency extremes

CI tests both `lowest` and `highest` deps (PHP 8.1/8.2/8.3 × Symfony ~6.4.0). Before pushing dependency-related changes, sanity-check lowest: `composer update --prefer-lowest && composer analyse && composer phpunit`, then restore with `composer update`. Version floors that only matter for this repo's toolchain belong in `require-dev` — never add `conflict` entries for them, since conflicts constrain end users.

### Test app frontend

`cd tests/Application && yarn install && yarn build` (Node 20, see `.nvmrc`). Assets come from `@sylius-ui/frontend`; webpack entries point directly into `vendor/sylius/sylius`.

## Architecture

### Plugin wiring

`SetonoSyliusMeilisearchPlugin` registers seven `CompositeCompilerPass` instances (from `setono/composite-compiler-pass`): tagged services are injected into composite implementations for data mappers, URL generators, index scope providers, settings providers, entity filters, filter builders, and filter form builders. To extend any of these, implement the interface and tag the service (autoconfiguration adds the tags — see `registerForAutoconfiguration` calls in the extension). Service definitions are XML under `src/Resources/config/services/`.

`SetonoSyliusMeilisearchExtension::prepend()` registers a scoped `framework.http_client` PSR-18 client for the Meilisearch SDK — this is why `nyholm/psr7` and `symfony/http-client` are production dependencies. `getConfiguration()` passes `kernel.debug` into `Configuration` (metadata cache defaults to `!$debug`), mirroring FrameworkBundle/DoctrineBundle.

### Indexing pipeline

`setono_sylius_meilisearch.indexes` config becomes `Config\Index` value objects (name, Document class, entity classes, per-index service locator) collected in `Config\IndexRegistry`. Indexing is triggered by the `setono:sylius-meilisearch:index` command or by Doctrine lifecycle events (`EventListener\Doctrine\EntityListener`), both dispatching messenger commands (`Message\Command\*`). `Indexer\DefaultIndexer` batches entity ids through `IndexBuffer`, loads entities, and runs each through the composite `DataMapper` chain, which populates `Document\Document` subclasses (attribute mapping is driven by PHP attributes like `#[Facetable]` on document properties, read via `Document\Metadata\MetadataFactory`). Documents are serialized and pushed to Meilisearch; the index uid is resolved per scope (channel/locale/currency + `%env(MEILISEARCH_PREFIX)%` and kernel environment) via `Resolver\IndexUid` and `Provider\IndexScope`.

### Search side

`Engine\SearchEngine` runs Meilisearch multi-search queries (one for hits, one per facet for full facet distribution) and produces `SearchResult`/`FacetDistribution`/`FacetValues`. Filter forms are built by `Form\Builder\SearchFormBuilder` plus per-attribute `FilterFormBuilder` implementations; facet value ordering is pluggable via `Form\Builder\Sorter` (e.g. `SizeSorter`). The autocomplete UI is wired through `Twig\AutocompleteExtension`/`AutocompleteRuntime` using the search-only API key.

### Static analysis specifics

PHPStan runs at level max with no baseline. `phpstan.neon` boots the test-app kernel through `tests/PHPStan/{console_application,object_manager}.php` (they load the test app's Dotenv bootstrap; `tests/Application/config/packages/setono_sylius_meilisearch.yaml` provides `env()` fallbacks so the container compiles without a `.env`). PSR-11 `get()` is typed by `stubs/Psr/Container/ContainerInterface.stub`. Noise-class errors are ignored by identifier in `phpstan.neon` (`missingType.*`, `doctrine.columnType`, `doctrine.associationType`, `trait.unused`) — do not add value-free phpdocs (`FormBuilderInterface<mixed>`, `@param mixed $x`, `array<string, mixed>`) to silence them, and prefer identifier/path `ignoreErrors` entries over inline `@phpstan-ignore` comments.

## Conventions

- Tests use Prophecy for mocks and `self::assertX()` (not `$this->assertX()` — PHPStan strict rules flag dynamic calls to static assertions).
- Translations live in `src/Resources/translations`; English is primary, translated into Danish, German, French, Dutch, Norwegian, Polish, Swedish, Italian, Spanish, Romanian, Lithuanian. Keys are sorted alphabetically.
- YAML files use the `.yaml` extension.
- `composer.json` must stay normalized (`composer normalize`).
- CI mirrors `Setono/SyliusPluginSkeleton` (branch `1.14.x`); when touching workflows, use the newest major versions of actions.
