<?php

declare(strict_types=1);

namespace Setono\SyliusMeilisearchPlugin\Tests\Functional\Admin;

use Setono\SyliusMeilisearchPlugin\Tests\Functional\FunctionalTestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @covers \Setono\SyliusMeilisearchPlugin\Controller\Action\Admin\StatusAction
 */
final class StatusPageTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_renders_the_status_page(): void
    {
        $this->logInAdminUser();

        self::$client->request('GET', '/admin/meilisearch/status');
        self::assertResponseIsSuccessful();

        $content = (string) self::$client->getResponse()->getContent();

        // the server panel
        self::assertStringContainsString('Server available', $content);
        self::assertStringContainsString('Version', $content);

        // the grid with its filters
        self::assertStringContainsString('Enqueued at', $content);
        self::assertStringContainsString('criteria[status]', $content);
        self::assertStringContainsString('criteria[indexUid]', $content);

        // the index command runs before this suite (see the CI workflow), so document tasks exist
        self::assertStringContainsString('documentAdditionOrUpdate', $content);
    }

    /**
     * @test
     */
    public function it_filters_tasks(): void
    {
        $this->logInAdminUser();

        self::$client->request('GET', '/admin/meilisearch/status', [
            'criteria' => [
                'status' => 'succeeded',
                'indexUid' => '',
            ],
            'page' => 1,
        ]);

        self::assertResponseIsSuccessful();
    }

    private function logInAdminUser(): void
    {
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        self::assertInstanceOf(RepositoryInterface::class, $repository);

        $adminUser = $repository->findOneBy([]);
        self::assertInstanceOf(AdminUserInterface::class, $adminUser);
        self::assertInstanceOf(UserInterface::class, $adminUser);

        self::$client->loginUser($adminUser, 'admin');
    }
}
