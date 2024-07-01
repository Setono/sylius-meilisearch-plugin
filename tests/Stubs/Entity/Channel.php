<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Stubs\Entity;

use Setono\SyliusMeilisearchPlugin\Model\IndexableAwareTrait;
use Setono\SyliusMeilisearchPlugin\Model\IndexableInterface;
use Sylius\Component\Core\Model\Channel as BaseChannel;

class Channel extends BaseChannel implements IndexableInterface
{
    use IndexableAwareTrait;
}
