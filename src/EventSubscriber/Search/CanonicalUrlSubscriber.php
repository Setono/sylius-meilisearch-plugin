<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\EventSubscriber\Search;

use Setono\SyliusMeilisearchPlugin\Event\Search\SearchResponseCreated;
use Setono\SyliusMeilisearchPlugin\UrlGenerator\CanonicalUrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

final class CanonicalUrlSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CanonicalUrlGeneratorInterface $canonicalUrlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SearchResponseCreated::class => 'onSearchResponseCreated',
        ];
    }

    public function onSearchResponseCreated(SearchResponseCreated $event): void
    {
        $link = new Link('canonical', $this->canonicalUrlGenerator->generate($event->request));

        /** @var GenericLinkProvider|null $linkProvider */
        $linkProvider = $event->request->attributes->get('_links');

        if (null === $linkProvider) {
            $event->request->attributes->set('_links', new GenericLinkProvider([$link]));

            return;
        }

        $event->request->attributes->set('_links', $linkProvider->withLink($link));
    }
}
