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

If you don't use Symfony Flex, add the plugin to your `config/bundles.php` before `SyliusGridBundle`:

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
MEILISEARCH_URL=http://localhost:7700
MEILISEARCH_MASTER_KEY=YOUR_MASTER_KEY
MEILISEARCH_SEARCH_KEY=YOUR_SEARCH_KEY
MEILISEARCH_PREFIX= # optional; useful when developers share a Meilisearch instance
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

After the initial population, the plugin keeps the index up to date as entities change. Changes in *associations* are not detected, though, so consider reindexing periodically (e.g. a nightly cron).

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

## Autocomplete

Enable the instant-search widget and point it at one or more indexes:

```yaml
setono_sylius_meilisearch:
    autocomplete:
        enabled: true
        indexes: [ products ]
        container: '#autocomplete'   # CSS selector for the element the widget mounts on
```

The widget talks to Meilisearch directly from the browser using the (read-only) `MEILISEARCH_SEARCH_KEY`, so make sure that key is a search-only key.

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

See [CLAUDE.md](CLAUDE.md) for a deeper tour of the architecture and conventions.

[ico-version]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-meilisearch-plugin
[link-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin
