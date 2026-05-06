<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Block;

use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magebit\McpCmsTools\Tool\Cms\Block\BlockFieldApplier;
use Magebit\McpCmsTools\Tool\Cms\Block\BlockUpdate;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\Block as BlockModel;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockUpdateTest extends TestCase
{
    /**
     * @var EntityFinderInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private EntityFinderInterface&MockObject $entityFinder;

    /**
     * @var BlockRepositoryInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private BlockRepositoryInterface&MockObject $blockRepository;

    protected function setUp(): void
    {
        $this->entityFinder = $this->createMock(EntityFinderInterface::class);
        $this->blockRepository = $this->createMock(BlockRepositoryInterface::class);
    }

    public function testNewIdentifierRenamesBlock(): void
    {
        $block = $this->makeConcreteBlockMock();
        $this->entityFinder->method('blockFrom')->willReturn($block);
        $block->expects($this->once())->method('setIdentifier')->with('footer-v2');

        $this->blockRepository->method('save')->willReturn(
            $this->makeSavedBlock(5, 'footer-v2', 'Footer', true)
        );

        $audit = $this->tool()->execute([
            'identifier' => 'footer',
            'new_identifier' => 'footer-v2',
        ])->getAuditSummary();

        $fields = $audit['fields_changed'];
        $this->assertIsArray($fields);
        $this->assertContains('identifier', $fields);
    }

    public function testStoresOmittedPreservesAssignment(): void
    {
        $block = $this->makeConcreteBlockMock();
        $this->entityFinder->method('blockFrom')->willReturn($block);
        $block->expects($this->never())->method('setStores');

        $this->blockRepository->method('save')->willReturn(
            $this->makeSavedBlock(5, 'footer', 'Footer', true)
        );

        $this->tool()->execute(['id' => 5, 'title' => 'Footer']);
    }

    public function testStoresOnNonConcreteBlockThrows(): void
    {
        $bare = $this->createMock(BlockInterface::class);
        $this->entityFinder->method('blockFrom')->willReturn($bare);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Cannot apply store assignment/');

        $this->tool()->execute(['id' => 5, 'stores' => [1]]);
    }

    public function testAuditOnlyListsAppliedFields(): void
    {
        $block = $this->makeConcreteBlockMock();
        $this->entityFinder->method('blockFrom')->willReturn($block);
        $block->expects($this->once())->method('setTitle')->with('Renamed');
        $block->expects($this->never())->method('setContent');

        $this->blockRepository->method('save')->willReturn(
            $this->makeSavedBlock(5, 'footer', 'Renamed', true)
        );

        $audit = $this->tool()->execute([
            'id' => 5,
            'title' => 'Renamed',
            'content' => 42, // type mismatch; applier silently skips
        ])->getAuditSummary();

        $this->assertSame(['title'], $audit['fields_changed']);
    }

    private function tool(): BlockUpdate
    {
        return new BlockUpdate($this->entityFinder, $this->blockRepository, new BlockFieldApplier());
    }

    /**
     * @return BlockModel&MockObject
     */
    private function makeConcreteBlockMock(): BlockModel&MockObject
    {
        // setStores is a magic accessor on AbstractModel — needs addMethods.
        return $this->getMockBuilder(BlockModel::class)
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
     * @return BlockInterface&MockObject
     */
    private function makeSavedBlock(int $id, string $identifier, string $title, bool $active): BlockInterface&MockObject
    {
        $saved = $this->createMock(BlockInterface::class);
        $saved->method('getId')->willReturn((string) $id);
        $saved->method('getIdentifier')->willReturn($identifier);
        $saved->method('getTitle')->willReturn($title);
        $saved->method('isActive')->willReturn($active);
        return $saved;
    }
}
