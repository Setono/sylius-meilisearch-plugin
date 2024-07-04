# Meilisearch Plugin for Sylius

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Use Meilisearch in your Sylius store.

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
            document: 'Setono\SyliusMeilisearchPlugin\Tests\Application\Document\Product'
            entities: [ 'App\Entity\Product\Product' ]
    search:
        indexes:
            - 'products'

```

In your `.env.local` add your parameters: 

```dotenv
###> setono/sylius-meilisearch-plugin ###
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_MASTER=YOUR_MASTER_KEY
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

[ico-version]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-meilisearch-plugin/license
[ico-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-meilisearch-plugin
[link-github-actions]: https://github.com/Setono/sylius-meilisearch-plugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/sylius-meilisearch-plugin
