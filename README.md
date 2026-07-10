# Meilisearch Plugin for Sylius

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Integrate [Meilisearch](https://www.meilisearch.com/) — the lightning-fast, open-source search engine — into your [Sylius](https://sylius.com/) store.

## Features

- **Automatic indexing** — entities are indexed when they change (via Doctrine lifecycle events) and can be (re)indexed in bulk with a console command
- **Search page** — a ready-made, faceted search experience for your shop, including taxon pages backed by Meilisearch
- **Autocomplete** — an instant-search widget powered by Meilisearch's official autocomplete library
- **Facets & filters** — declare facets, filters, and sortable fields with PHP attributes on a plain document class
- **Active filters** — applied filters show as removable chips on the search page, with a one-click "Reset all filters" link
- **Synonyms** — manage search synonyms in the Sylius admin; they are synced to Meilisearch automatically
- **Extensible by design** — data mappers, URL generators, entity filters, index scopes, and facet sorters are all pluggable services

## Requirements

- PHP `>= 8.1`
- Sylius `1.14` on Symfony `6.4` (the versions the plugin is built and tested against)
- A running Meilisearch instance

## Installation

### 1. Require the plugin

```shell
composer require setono/sylius-meilisearch-plugin
```

### 2. Register the plugin

> **No Symfony Flex recipe ships yet** ([#74](https://github.com/Setono/sylius-meilisearch-plugin/issues/74) tracks adding one), so every step in this installation guide is manual — Flex will not register the bundle, configure it, or import the routing for you.

Add the plugin to your `config/bundles.php` before `SyliusGridBundle`:

```php
Setono\SyliusMeilisearchPlugin\SetonoSyliusMeilisearchPlugin::class => ['all' => true],
```

### 3. Configure the plugin

```yaml
# config/packages/setono_sylius_meilisearch.yaml
setono_sylius_meilisearch:
    indexes:
        products:
            document: 'Setono\SyliusMeilisearchPlugin\Document\Product'
            entities: [ 'App\Entity\Product\Product' ]
    search:
        enabled: true
        index: products
```

Add your Meilisearch credentials to `.env.local`:

```dotenv
###> setono/sylius-meilisearch-plugin ###
MEILISEARCH_URL=http://localhost:7700       # required; server-side URL the PHP SDK connects to
MEILISEARCH_MASTER_KEY=YOUR_MASTER_KEY       # required; used for indexing and settings
MEILISEARCH_SEARCH_KEY=YOUR_SEARCH_KEY       # required for search/autocomplete (search-only key)
MEILISEARCH_PUBLIC_URL=                       # optional; browser-facing URL for the autocomplete widget when MEILISEARCH_URL is an internal hostname. Falls back to MEILISEARCH_URL when empty
MEILISEARCH_PREFIX=                           # optional; useful when developers share a Meilisearch instance
###< setono/sylius-meilisearch-plugin ###
```

Index names are automatically prefixed with the kernel environment (and your optional `MEILISEARCH_PREFIX`), so `dev`, `test`, and `prod` never collide.

The full list of options is always available via:

```shell
php bin/console config:dump-reference setono_sylius_meilisearch
```

### 4. Import routing

```yaml
# config/routes/setono_sylius_meilisearch.yaml
setono_sylius_meilisearch:
    resource: "@SetonoSyliusMeilisearchPlugin/Resources/config/routes.yaml"
```

or if your app doesn't use locales:

```yaml
# config/routes/setono_sylius_meilisearch.yaml
setono_sylius_meilisearch:
    resource: "@SetonoSyliusMeilisearchPlugin/Resources/config/routes_no_locale.yaml"
```

This registers the shop search page (`/search`), the taxon search page (`/taxons/{slug}`), the search widget endpoint, and the synonym CRUD in the admin.

### 5. Implement `IndexableInterface` in your entities

Every entity you index must implement `Setono\SyliusMeilisearchPlugin\Model\IndexableInterface`. The `IndexableAwareTrait` provides a default implementation that uses the entity id as the document identifier:

```php
<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements IndexableInterface
{
    use IndexableAwareTrait;
}
```

> **Tip:** The document identifier must be unique across the whole index. If you mix entity types (e.g. products and taxons) in one index, override `getDocumentIdentifier()` to prefix the id.

### 6. Update your database schema

The plugin ships a `Synonym` resource, so create and run a migration:

```shell
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate -n
```

### 7. Install assets

```shell
php bin/console assets:install
```

### 8. Populate the indexes

```shell
php bin/console setono:sylius-meilisearch:index          # index everything
php bin/console setono:sylius-meilisearch:index --wait   # ...and wait for Meilisearch to finish processing
```

After the initial population, the plugin keeps the index up to date as entities change — including related changes such as channel prices and variant stock (via tagged `IndexableEntityResolver` services).

## Operational model

Read this before going to production — it covers how indexing actually runs, when index settings are pushed, and a deploy checklist.

### Asynchronous indexing (strongly recommended)

Out of the box every save of an indexable entity indexes **synchronously inside the request**: the plugin dispatches its messages on a dedicated `setono_sylius_meilisearch.command_bus`, which — with no transport routing — runs the handlers inline, issuing one blocking Meilisearch HTTP call per index scope. That makes admin product saves slower the more channels/locales/currencies you have. (A failed index update never breaks the save — the plugin catches and logs it — but it does add latency.)

Route the plugin's messages to an async transport instead. All plugin messages implement `Setono\SyliusMeilisearchPlugin\Message\Command\CommandInterface`, so a single routing entry covers everything:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            setono_sylius_meilisearch: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Setono\SyliusMeilisearchPlugin\Message\Command\CommandInterface': setono_sylius_meilisearch
```

Then run a worker (supervised, e.g. with Supervisor or systemd) and configure a failure transport:

```shell
php bin/console messenger:consume setono_sylius_meilisearch --time-limit=3600
```

### How settings sync works

Meilisearch index settings — `filterableAttributes` (from `#[Facetable]`), `sortableAttributes` (from `#[Sortable]`), `searchableAttributes` (from `#[Searchable]`), synonyms, etc. — are pushed **only** by the full `setono:sylius-meilisearch:index` command. Incremental Doctrine-event indexing updates documents, never settings.

> **After changing document attributes** (adding/removing a `#[Facetable]`, `#[Sortable]`, `#[Searchable]`, changing a priority, …) **run `setono:sylius-meilisearch:index`.** Waiting for auto-indexing leaves the new facet/sort silently non-functional because its setting was never applied.

### Production checklist

- **Run the index command on deploy** so document/settings changes take effect: `php bin/console setono:sylius-meilisearch:index --wait`.
- **Reindex on a schedule.** Incremental indexing keeps documents fresh under normal operation, but a nightly reindex guarantees the index converges with the database (e.g. after bulk SQL / fixtures that bypass Doctrine events). One line in cron:
  ```
  0 3 * * *  php /path/to/app/bin/console setono:sylius-meilisearch:index --wait
  ```
  (`--wait` makes the command block until Meilisearch has finished processing this run's tasks.)
- **Supervise the worker(s)** running `messenger:consume` (see above) and monitor the failure transport.
- **Environment variables:** `MEILISEARCH_URL` and `MEILISEARCH_MASTER_KEY` are required; `MEILISEARCH_SEARCH_KEY` is required for search/autocomplete; `MEILISEARCH_PUBLIC_URL` and `MEILISEARCH_PREFIX` are optional.

### Reference configuration

The plugin's [test application config](tests/Application/config/packages/setono_sylius_meilisearch.yaml) is the best worked example of a multi-index setup (a product index and a custom `taxons` index).

## Customizing what gets indexed

### The document

A *document* is a plain PHP class describing what a record in a Meilisearch index looks like. The bundled `Setono\SyliusMeilisearchPlugin\Document\Product` is a good starting point — extend it or create your own by extending `Document`. Index behaviour is declared with PHP attributes on properties (and even getters):

```php
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Facetable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Filterable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Image;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductAttribute;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\MapProductOption;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Searchable;
use Setono\SyliusMeilisearchPlugin\Document\Attribute\Sortable;

class Product extends Document
{
    #[Searchable]                               // full-text searchable in Meilisearch
    public ?string $name = null;

    #[Facetable]                                // shown as a facet/filter on the search page
    #[Sortable(direction: Sortable::ASC)]       // offered as a sort option
    public ?float $price = null;

    #[Filterable]                               // filterable in queries, but not shown as a facet
    public array $taxonCodes = [];

    #[Image(filterSet: 'sylius_shop_product_thumbnail')]  // resolved through Liip Imagine
    public ?string $image = null;

    #[MapProductAttribute('brand')]             // populated from a Sylius product attribute
    public ?string $brand = null;

    #[MapProductOption('size')]                 // populated from a Sylius product option
    public array $size = [];

    #[Facetable]                                // getters work too — great for computed facets
    public function isOnSale(): bool
    {
        return null !== $this->originalPrice && null !== $this->price && $this->price < $this->originalPrice;
    }
}
```

> **Point the config at your subclass.** `indexes.<name>.document` must reference **your** document class, not the built-in `Setono\SyliusMeilisearchPlugin\Document\Product`. It's easy to add a property to a subclass that is never indexed because the config still points at the base document.

### Data mappers

Data mappers move data from your entities onto the document. Implement `Setono\SyliusMeilisearchPlugin\DataMapper\DataMapperInterface` and the service is picked up automatically through autoconfiguration. The plugin ships mappers for the basics (name, url, image, prices, taxons, product attributes/options, popularity).

### Filtering entities out of the index

There are two hooks, and you can combine them:

**1. At the database level (most efficient)** — listen to `QueryBuilderForDataProvisionCreated` and restrict the query:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class FilterDisabledEntitiesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            QueryBuilderForDataProvisionCreated::class => 'filter',
        ];
    }

    public function filter(QueryBuilderForDataProvisionCreated $event): void
    {
        if (!is_a($event->entity, ToggleableInterface::class, true)) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere($alias . '.enabled = true');
    }
}
```

**2. Per entity during indexing** — implement `FilterableInterface`:

```php
class Product extends BaseProduct implements IndexableInterface, FilterableInterface
{
    use IndexableAwareTrait;

    public function filter(): bool
    {
        return $this->isEnabled();
    }
}
```

The plugin also ships default entity filters (e.g. an `enabled` filter for `ToggleableInterface` entities and a channel filter for channel-aware entities). Toggle them per index under `indexes.<name>.default_filters`, or add your own by implementing `Filter\Entity\EntityFilterInterface`.

### Reacting to related-entity changes

Incremental indexing reacts to changes on the indexed entity and its translations. To also reindex when an **associated** entity changes (a custom brand entity, a supplier, …), implement `Setono\SyliusMeilisearchPlugin\Resolver\Indexable\IndexableEntityResolverInterface` and tag it with `setono_sylius_meilisearch.indexable_entity_resolver` (autoconfiguration adds the tag). It receives every changed object and returns the indexable entities that should be reindexed. The plugin ships resolvers for `ChannelPricing` → product (price changes) and `ProductVariant` → product (stock changes).

## Indexing a custom entity

Indexing something other than products is the plugin's flagship extensibility story. Here is the whole path, mirroring the built-in **taxon** index (which the test application configures end to end).

**1. Configure the index.** Add it under `indexes`, pointing at a document class and the entity/entities that feed it:

```yaml
setono_sylius_meilisearch:
    indexes:
        taxons:
            document: 'Setono\SyliusMeilisearchPlugin\Document\Taxon'
            entities: [ 'App\Entity\Taxonomy\Taxon' ]
```

**2. Make the entity indexable.** Implement `IndexableInterface` on the entity (the `IndexableAwareTrait` gives you the default id-based document identifier), exactly as for products.

**3. Create a document** (or reuse the shipped `Document\Taxon`) with the attributes describing the record — see [The document](#the-document).

**4. Provide a URL generator** so search results link somewhere. Implement `Setono\SyliusMeilisearchPlugin\UrlGenerator\EntityUrlGeneratorInterface` (extend `AbstractEntityUrlGenerator` for the injected router) and tag it with `setono_sylius_meilisearch.url_generator` (autoconfigured):

```php
final class TaxonUrlGenerator extends AbstractEntityUrlGenerator
{
    public function generate(IndexableInterface $entity, array $context = []): string
    {
        Assert::true($this->supports($entity, $context));
        // build the URL for $entity (e.g. via $this->router->generate(...))
    }

    public function supports(IndexableInterface $entity, array $context = []): bool
    {
        return $entity instanceof TaxonInterface;
    }
}
```

**5. (Optional) Provide a scope provider.** If your index doesn't need the full channel/locale/currency matrix that products use, implement `Provider\IndexScope\IndexScopeProviderInterface` and tag it with `setono_sylius_meilisearch.index_scope_provider`; otherwise the `DefaultIndexScopeProvider` is used.

Then reindex (`setono:sylius-meilisearch:index`) and your entity is searchable with clickable results.

### Custom facet widgets

Facets are rendered by tagged `Setono\SyliusMeilisearchPlugin\Form\Builder\FilterFormBuilderInterface` services. The shipped builders cover checkbox (boolean), multi-choice (array) and range (numeric) facets. To render a facet differently, implement the interface and tag the service with `setono_sylius_meilisearch.filter_form_builder` (autoconfigured). `supports()` decides whether your builder handles a given facet; `build()` adds the form child (named after the facet). Builders are tried in tag-priority order, so give a more specific builder a higher priority than the shipped ones.

## The search widget

The search widget is automatically injected into the shop header via the `sylius.shop.layout.header.grid` Sylius UI event. If your theme doesn't render that event — or you want the widget somewhere else — render it manually:

```twig
{{ ssm_search_widget() }}
```

Depending on your configuration it renders either the javascript based autocomplete widget or a plain search form, and it outputs nothing when both search and autocomplete are disabled.

## Autocomplete

Enable the instant-search widget and point it at one or more indexes:

```yaml
setono_sylius_meilisearch:
    autocomplete:
        enabled: true
        indexes: [ products ]
        container: '#autocomplete'   # CSS selector for the element the widget mounts on
        limit: 5                     # max suggestions to fetch per source
```

The widget talks to Meilisearch directly from the browser using the (read-only) `MEILISEARCH_SEARCH_KEY`, so make sure that key is a search-only key.

> [!WARNING]
> The search key is embedded in the public page source of every shop page and the browser queries Meilisearch directly with it. **Never** set `MEILISEARCH_SEARCH_KEY` to the master key — that would publish full read/write/key-management access to the world. The plugin guards against this: with autocomplete enabled, the container fails to compile when the search key resolves to the same non-empty value as the master key. Ideally, scope the search key to exactly the indexes used by autocomplete (Meilisearch key `indexes` patterns + `actions: ["search"]`), since anything it can reach is fully queryable — including fields hidden in the UI (prices etc. are extractable via `facetStats`/filters even when excluded from `displayedAttributes`).

Because the browser connects to Meilisearch directly, it needs a URL it can actually reach. In containerized setups (Docker Swarm/Kubernetes) `MEILISEARCH_URL` is often an internal hostname like `http://meilisearch:7700` that browsers can't resolve. Configure the public, browser-facing URL separately in that case:

```yaml
setono_sylius_meilisearch:
    server:
        public_url: '%env(MEILISEARCH_PUBLIC_URL)%' # falls back to server.url when null/empty
```

The server-side indexing and search keep using `server.url`; only the autocomplete widget uses `server.public_url`.

### Per-source item template

Each autocomplete source is resolved by `Meilisearch\Autocomplete\SourceResolverInterface`. The default resolver renders `@SetonoSyliusMeilisearchPlugin/autocomplete/templates/{indexName}/item.html.twig` when that template exists, and otherwise falls back to the shared `@SetonoSyliusMeilisearchPlugin/autocomplete/templates/item.html.twig`. So with multiple indexes (e.g. `products` and `taxons`) you can give each its own template just by creating `templates/taxons/item.html.twig` in your app. Decorate the resolver if you need to control the `urlAttribute` (or anything else) per index.

## Customizing the JavaScript

The plugin ships two first-party scripts, both served as plain browser JavaScript (no build step). You customize them by defining a global options object **before** the script runs — put the `<script>` that sets it above the plugin's scripts, or in a `javascripts` block that renders earlier. The plugin's scripts are loaded with `defer`, so a normal inline `<script>` in `<head>` or the body runs first.

### The search results page (`search.js`)

Set `window.ssmSearch` to override any option. Your values are merged over the defaults (the `loader` object is merged one level deep, so you can override just `show` or just `hide`):

```html
<script>
    window.ssmSearch = {
        form: '#search-form',             // CSS selector of the search form (must be a selector, not an element)
        contentSelector: '#search-form',  // the markup replaced on each search
        loader: {
            selector: '#ssm-overlay',
            show(selector) { document.querySelector(selector).style.display = 'block'; },
            hide(selector) { document.querySelector(selector).style.display = 'none'; },
        },
        // Callbacks receive the field that changed (onSubmit receives nothing); `this` is the manager.
        onFilterChange(field) { /* ... */ },
        onPageChange(field) { /* ... */ },
        onSortChange(field) { /* ... */ },
        onSubmit() { this.submit(); },
    };
</script>
```

> **Note:** `form` must be a **selector string**, not a DOM element — the form node is replaced on every search, so a stored element reference would go stale.

The created instance is exposed as `window.ssmSearchManager`, with a small public API:

- `window.ssmSearchManager.form` — the current results `<form>`, or `null` when the "no results" block is shown.
- `window.ssmSearchManager.submit()` — runs the background search immediately; returns a `Promise`.

As the form is used, it emits these bubbling events (all fire on the changed field / new content):

| Event | When |
| --- | --- |
| `search:form-changed` | any filter/page/sort field changes |
| `search:filter-changed` | a filter field changes |
| `search:page-changed` | the page field changes |
| `search:sort-changed` | the sort field changes |
| `search:content-updated` | the results markup was swapped in (AJAX **or** back/forward) |

Use `search:content-updated` to re-initialize your own widgets after the results are replaced:

```html
<script>
    document.addEventListener('search:content-updated', (event) => {
        // event.detail.content is the freshly inserted element
        myLazyImages.observe(event.detail.content);
    });
</script>
```

### The autocomplete widget (`autocomplete.js`)

Set `window.ssmAutocomplete` to override any [autocomplete-js](https://www.algolia.com/doc/ui-libraries/autocomplete/api/) option. **Your options win** over the plugin's:

```html
<script>
    window.ssmAutocomplete = {
        placeholder: 'Search the shop…',
        // Supplying your own getSources replaces the default Meilisearch sources entirely.
        // getSources({ query }) { return [...]; },
    };
</script>
```

> **Content Security Policy:** the default item templates are compiled with `new Function`, which requires `script-src 'unsafe-eval'`. If your CSP forbids that, supply your own `getSources` via `window.ssmAutocomplete` — that path never compiles a template, so no `'unsafe-eval'` is needed.

## Synonyms

Synonyms are managed in the Sylius admin (the plugin adds its own menu section) and synced to Meilisearch automatically when created, updated, or removed. A synonym can be scoped to specific channels and a locale.

## Contributing / Development

```shell
git clone git@github.com:Setono/sylius-meilisearch-plugin.git
cd sylius-meilisearch-plugin
composer install
```

Quality checks (all enforced by CI, against both lowest and highest dependencies on PHP 8.1–8.3):

```shell
composer analyse          # PHPStan (level max)
composer check-style      # ECS (fix with: composer fix-style)
composer phpunit          # Unit test suite — needs no external services
vendor/bin/rector process --dry-run
```

### Running the functional tests

The Functional suite needs MySQL/MariaDB and a Meilisearch instance on `localhost:7700` (master key `aSampleMasterKey`, matching the defaults in `tests/Application/.env`). Start Meilisearch with the bundled compose file:

```shell
cd tests/Application && docker compose up -d --wait   # `docker compose down` resets it to a clean state
```

Without Docker, `tests/Application/meilisearch.sh` downloads and runs the Meilisearch binary instead.

Then set up the test application and run the suite:

```shell
cd tests/Application
export APP_ENV=test
bin/console doctrine:database:create
bin/console doctrine:schema:create
bin/console sylius:fixtures:load -n
bin/console setono:sylius-meilisearch:index --wait
cd ../..
vendor/bin/phpunit --testsuite Functional
```

### Running the test application in a browser

```shell
cd tests/Application
yarn install && yarn build          # Node 20, see .nvmrc
bin/console assets:install
bin/console doctrine:database:create
bin/console doctrine:schema:create
bin/console sylius:fixtures:load -n
bin/console setono:sylius-meilisearch:index --wait
symfony serve
```

### Running the end-to-end tests

The Playwright suite drives the shop search page and the autocomplete widget in a real browser. It needs the same MySQL/MariaDB + Meilisearch + fixtures + index setup as the Functional suite, plus built assets and the [Symfony CLI](https://symfony.com/download):

```shell
cd tests/Application
docker compose up -d --wait
yarn install && yarn build
bin/console assets:install                       # APP_ENV=test for all console commands
bin/console doctrine:database:create && bin/console doctrine:schema:create
bin/console sylius:fixtures:load -n
bin/console setono:sylius-meilisearch:index --wait
npx playwright install chromium                  # first time only
yarn e2e                                          # or: yarn e2e:ui
```

`yarn e2e` starts the app itself via `e2e/serve.sh` (which runs `symfony serve` on `127.0.0.1:8080` with `APP_ENV=test` and resolves the Meilisearch search key the autocomplete widget needs), so you don't start a server yourself.

### Updating the vendored JavaScript libraries

`src/Resources/public/js/` contains two vendored third-party bundles (their file headers record the exact version, source, and reproduction command):

- `algolia.autocomplete.js` — [`@algolia/autocomplete-js`](https://www.jsdelivr.com/package/npm/@algolia/autocomplete-js) UMD production build, downloaded from jsDelivr.
- `meilisearch.autocomplete.js` — [`@meilisearch/autocomplete-client`](https://www.npmjs.com/package/@meilisearch/autocomplete-client). This package ships ES modules only, so the vendored file is a single self-contained browser IIFE produced by bundling it once with esbuild. That is a one-off step at update time — no build tooling is added to the plugin or to consuming applications; only the produced file is committed.

To update one, follow the command in its header (bump the version), overwrite everything below the banner comment, and run the end-to-end suite. `autocomplete.js` resolves the Meilisearch client's global name defensively, so a version that renames it still works.

See [CLAUDE.md](CLAUDE.md) for a deeper tour of the architecture and conventions.

[ico-version]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-meilisearch-plugin
[link-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin
