<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AddMenuSubscriber implements EventSubscriberInterface
{
    public const MENU_ITEM_KEY = 'setono_sylius_meilisearch';

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.menu.admin.main' => 'add',
        ];
    }

    public function add(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $header = $this->getHeader($menu);

        $header
            ->addChild('synonyms', [
                'route' => 'setono_sylius_meilisearch_admin_synonym_index',
            ])
            ->setLabel('setono_sylius_meilisearch.menu.admin.main.meilisearch.synonyms')
            ->setLabelAttribute('icon', 'exchange')
        ;

        $order = ['catalog', 'sales', 'customers', 'marketing', self::MENU_ITEM_KEY];
        $rest = array_diff(array_keys($menu->getChildren()), $order);

        try {
            $event->getMenu()->reorderChildren(array_merge($order, $rest));
        } catch (\InvalidArgumentException) {
        }
    }

    private function getHeader(ItemInterface $menu): ItemInterface
    {
        $header = $menu->getChild(self::MENU_ITEM_KEY);
        if (null !== $header) {
            return $header;
        }

        return $menu->addChild(self::MENU_ITEM_KEY)
            ->setLabel('setono_sylius_meilisearch.menu.admin.main.meilisearch.header');
    }
}
