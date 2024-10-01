# Meilisearch Plugin for Sylius

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

[Meilisearch](https://github.com/meilisearch/meilisearch) is an open-source search engine written in Rust, designed to create lightning-fast and hyper-relevant search experiences out of the box.

## Installation

```shell
composer require setono/sylius-meilisearch-plugin
```

### Import configuration

```yaml
# config/packages/setono_sylius_meilisearch.yaml
setono_sylius_meilisearch:
    indexes:
        products:
            document: 'Setono\SyliusMeilisearchPlugin\Document\Product'
            entities: [ 'App\Entity\Product\Product' ]
    search:
        index: products

```

In your `.env.local` add your parameters: 

```dotenv
###> setono/sylius-meilisearch-plugin ###
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_MASTER_KEY=YOUR_MASTER_KEY
###< setono/sylius-meilisearch-plugin ###
```

### Import routing

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

### Implement the `IndexableInterface` in your entities

The entities you've configured for indexing has to implement the `Setono\SyliusMeilisearchPlugin\Model\IndexableInterface`.

In a typical Sylius application for the `Product` entity it could look like this:

```php
<?php
declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Product as BaseProduct;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_product")
 */
class Product extends BaseProduct implements IndexableInterface
{
    public function getDocumentIdentifier(): ?string
    {
        return (string) $this->getId();
    }
}
```

## Filter entities to index

When indexing entities, most likely there are some of the entities that you don't want included in the index.
There are two ways you can do this. 1) When data is fetched from the database or 2) when data is traversed during indexing.
Obviously, the first option is the most efficient, but let's look at both.

### Filtering when fetching data from the database

Here you will listen to the `\Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated` event and modify the query builder accordingly.
Here is an example where we filter out disabled products:

```php
<?php
    
namespace App\EventSubscriber;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusMeilisearchPlugin\Event\QueryBuilderForDataProvisionCreated;
use Sylius\Component\Resource\Model\ToggleableInterface;use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FilterDisabledEntitiesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            QueryBuilderForDataProvisionCreated::class => 'filter',
        ];
    }
    
    public function filter(QueryBuilderForDataProvisionCreated $event): void
    {
        if(!is_a($event->entity, ToggleableInterface::class, true)) {
            return;
        }
        
        $queryBuilder = $event->getQueryBuilder();
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere($alias . '.enabled = true');
    }
}
```

### Filtering when traversing data

Here you will implement the `\Setono\SyliusMeilisearchPlugin\Model\FilterableInterface` in your entity and implement the `filter` method.
The example below is the same as the previous example, but this time we filter out disabled products when traversing the data:

```php

<?php

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

use Setono\SyliusMeilisearchPlugin\Model\FilterableInterface;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Product as BaseProduct;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_product")
 */
class Product extends BaseProduct implements IndexableInterface, FilterableInterface
{
    public function getDocumentIdentifier(): ?string
    {
        return (string) $this->getId();
    }
    
    public function filter(): bool
    {
        return $this->isEnabled();
    }
}
```

## Testing

To run the functional tests in the plugin, here are the steps:

1. Ensure you have Meilisearch running and that you've set the required environment variables in `.env.test.local`.

2. Create the test database
   
    ```shell
    php bin/console doctrine:database:create --env=test
    ```

3. Update the test database schema

    ```shell
    php bin/console doctrine:schema:update --env=test --force
    ```

4. Load fixtures

    ```shell
    php bin/console sylius:fixtures:load -n --env=test
    ```

5. Populate the index

    ```shell
    php bin/console setono:sylius-meilisearch:index --env=test
    ```

6. Run the tests

    ```shell
    vendor/bin/phpunit --testsuite Functional
    ```

[ico-version]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-meilisearch-plugin
[link-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin
