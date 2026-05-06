<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Block;

use Magebit\McpCmsTools\Tool\Cms\Block\BlockFieldApplier;
use Magento\Cms\Api\Data\BlockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockFieldApplierTest extends TestCase
{
    /**
     * @var BlockInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private BlockInterface&MockObject $block;

    protected function setUp(): void
    {
        $this->block = $this->createMock(BlockInterface::class);
    }

    public function testApplyOptionalReturnsAppliedKeys(): void
    {
        $this->block->expects($this->once())->method('setIdentifier')->with('footer_links');
        $this->block->expects($this->once())->method('setTitle')->with('Footer');
        $this->block->expects($this->once())->method('setContent')->with('<ul></ul>');
        $this->block->expects($this->once())->method('setIsActive')->with(true);

        $applied = (new BlockFieldApplier())->applyOptional($this->block, [
            'identifier' => 'footer_links',
            'title' => 'Footer',
            'content' => '<ul></ul>',
            'is_active' => true,
        ]);

        $this->assertSame(['identifier', 'title', 'content', 'is_active'], $applied);
    }

    public function testApplyOptionalSkipsTypeMismatch(): void
    {
        $this->block->expects($this->never())->method('setIdentifier');
        $this->block->expects($this->never())->method('setIsActive');

        $applied = (new BlockFieldApplier())->applyOptional($this->block, [
            'identifier' => 99,
            'is_active' => 'yes',
        ]);

        $this->assertSame([], $applied);
    }

    public function testExtractStoresReturnsNullWhenAbsent(): void
    {
        $this->assertNull((new BlockFieldApplier())->extractStores([]));
    }

    public function testExtractStoresDedupesAndCoerces(): void
    {
        $this->assertSame([0, 1], (new BlockFieldApplier())->extractStores([
            'stores' => [0, '1', 0, 1],
        ]));
    }
}
