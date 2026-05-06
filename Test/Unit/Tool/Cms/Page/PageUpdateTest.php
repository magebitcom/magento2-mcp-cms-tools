<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Page;

use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magebit\McpCmsTools\Tool\Cms\Page\PageFieldApplier;
use Magebit\McpCmsTools\Tool\Cms\Page\PageUpdate;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page as PageModel;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageUpdateTest extends TestCase
{
    /**
     * @var EntityFinderInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private EntityFinderInterface&MockObject $entityFinder;

    /**
     * @var PageRepositoryInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private PageRepositoryInterface&MockObject $pageRepository;

    protected function setUp(): void
    {
        $this->entityFinder = $this->createMock(EntityFinderInterface::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
    }

    public function testNewIdentifierBecomesIdentifierInPatch(): void
    {
        $page = $this->makeConcretePageMock();
        $this->entityFinder->method('pageFrom')->willReturn($page);
        $page->expects($this->once())->method('setIdentifier')->with('about-us-v2');

        $saved = $this->makeSavedPage(7, 'about-us-v2', 'About Us', true);
        $this->pageRepository->expects($this->once())->method('save')->willReturn($saved);

        $result = $this->tool()->execute([
            'identifier' => 'about-us',
            'new_identifier' => 'about-us-v2',
        ]);

        $audit = $result->getAuditSummary();
        $this->assertSame('about-us-v2', $audit['identifier']);
        $fields = $audit['fields_changed'];
        $this->assertIsArray($fields);
        $this->assertContains('identifier', $fields);
    }

    public function testIdentifierLookupKeyStrippedFromPatchWhenNotRenaming(): void
    {
        $page = $this->makeConcretePageMock();
        $this->entityFinder->method('pageFrom')->willReturn($page);
        $page->expects($this->never())->method('setIdentifier');
        $page->expects($this->once())->method('setTitle')->with('Renamed');

        $saved = $this->makeSavedPage(3, 'about', 'Renamed', true);
        $this->pageRepository->method('save')->willReturn($saved);

        $result = $this->tool()->execute([
            'identifier' => 'about',
            'title' => 'Renamed',
        ]);

        $this->assertSame(['title'], $result->getAuditSummary()['fields_changed']);
    }

    public function testStoresOmittedDoesNotTouchAssignment(): void
    {
        $page = $this->makeConcretePageMock();
        $this->entityFinder->method('pageFrom')->willReturn($page);
        $page->expects($this->never())->method('setStores');

        $this->pageRepository->method('save')->willReturn($this->makeSavedPage(1, 'home', 'Home', true));

        $this->tool()->execute(['id' => 1, 'title' => 'Home']);
    }

    public function testStoresProvidedAppliesAssignment(): void
    {
        $page = $this->makeConcretePageMock();
        $this->entityFinder->method('pageFrom')->willReturn($page);
        $page->expects($this->once())->method('setStores')->with([0, 1]);

        $this->pageRepository->method('save')->willReturn($this->makeSavedPage(1, 'home', 'Home', true));

        $audit = $this->tool()->execute([
            'id' => 1,
            'stores' => [0, 1],
        ])->getAuditSummary();

        $fields = $audit['fields_changed'];
        $this->assertIsArray($fields);
        $this->assertContains('stores', $fields);
    }

    public function testStoresProvidedButNonConcretePageThrows(): void
    {
        $bare = $this->createMock(PageInterface::class);
        $this->entityFinder->method('pageFrom')->willReturn($bare);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Cannot apply store assignment/');

        $this->tool()->execute(['id' => 1, 'stores' => [0]]);
    }

    public function testFieldsChangedReportsOnlyAppliedKeys(): void
    {
        $page = $this->makeConcretePageMock();
        $this->entityFinder->method('pageFrom')->willReturn($page);

        // The schema would reject this upstream; the applier must still not lie.
        $page->expects($this->never())->method('setIdentifier');
        $page->expects($this->once())->method('setTitle')->with('Ok');

        $this->pageRepository->method('save')->willReturn($this->makeSavedPage(1, 'home', 'Ok', true));

        $audit = $this->tool()->execute([
            'id' => 1,
            'new_identifier' => 999, // type mismatch, must be silently skipped
            'title' => 'Ok',
        ])->getAuditSummary();

        $this->assertSame(['title'], $audit['fields_changed']);
    }

    private function tool(): PageUpdate
    {
        return new PageUpdate($this->entityFinder, $this->pageRepository, new PageFieldApplier());
    }

    /**
     * @return PageModel&MockObject
     */
    private function makeConcretePageMock(): PageModel&MockObject
    {
        // setStores is a magic accessor on AbstractModel — needs addMethods.
        return $this->getMockBuilder(PageModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setIdentifier', 'setTitle', 'setContent',
                'setIsActive',
                'getId', 'getIdentifier', 'getTitle', 'isActive',
            ])
            ->addMethods(['setStores'])
            ->getMock();
    }

    /**
     * @param int $id
     * @param string $identifier
     * @param string $title
     * @param bool $active
     * @return PageInterface&MockObject
     */
    private function makeSavedPage(int $id, string $identifier, string $title, bool $active): PageInterface&MockObject
    {
        $saved = $this->createMock(PageInterface::class);
        $saved->method('getId')->willReturn((string) $id);
        $saved->method('getIdentifier')->willReturn($identifier);
        $saved->method('getTitle')->willReturn($title);
        $saved->method('isActive')->willReturn($active);
        return $saved;
    }
}
