<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Page;

use Magebit\McpCmsTools\Tool\Cms\Page\PageFieldApplier;
use Magento\Cms\Api\Data\PageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageFieldApplierTest extends TestCase
{
    /**
     * @var PageInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private PageInterface&MockObject $page;

    protected function setUp(): void
    {
        $this->page = $this->createMock(PageInterface::class);
    }

    public function testApplyOptionalReturnsKeysActuallyApplied(): void
    {
        $this->page->expects($this->once())->method('setIdentifier')->with('home');
        $this->page->expects($this->once())->method('setTitle')->with('Home');
        $this->page->expects($this->once())->method('setContent')->with('<p>Hi</p>');

        $applied = (new PageFieldApplier())->applyOptional($this->page, [
            'identifier' => 'home',
            'title' => 'Home',
            'content' => '<p>Hi</p>',
        ]);

        $this->assertSame(['identifier', 'title', 'content'], $applied);
    }

    public function testApplyOptionalSkipsTypeMismatchAndExcludesKeyFromResult(): void
    {
        $this->page->expects($this->never())->method('setIdentifier');
        $this->page->expects($this->once())->method('setTitle')->with('Home');

        $applied = (new PageFieldApplier())->applyOptional($this->page, [
            'identifier' => 123,
            'title' => 'Home',
        ]);

        $this->assertSame(['title'], $applied);
    }

    public function testApplyOptionalSkipsNonBooleanIsActive(): void
    {
        $this->page->expects($this->never())->method('setIsActive');

        $applied = (new PageFieldApplier())->applyOptional($this->page, ['is_active' => 1]);

        $this->assertSame([], $applied);
    }

    public function testApplyOptionalAppliesBooleanIsActive(): void
    {
        $this->page->expects($this->once())->method('setIsActive')->with(true);

        $applied = (new PageFieldApplier())->applyOptional($this->page, ['is_active' => true]);

        $this->assertSame(['is_active'], $applied);
    }

    public function testApplyOptionalSortOrderCoercedToString(): void
    {
        $this->page->expects($this->once())->method('setSortOrder')->with('5');

        $applied = (new PageFieldApplier())->applyOptional($this->page, ['sort_order' => 5]);

        $this->assertSame(['sort_order'], $applied);
    }

    public function testApplyOptionalEmptyPayloadAppliesNothing(): void
    {
        $this->page->expects($this->never())->method($this->anything());

        $applied = (new PageFieldApplier())->applyOptional($this->page, []);

        $this->assertSame([], $applied);
    }

    public function testExtractStoresReturnsNullWhenAbsent(): void
    {
        $this->assertNull((new PageFieldApplier())->extractStores([]));
    }

    public function testExtractStoresReturnsEmptyWhenNonArray(): void
    {
        $this->assertNull((new PageFieldApplier())->extractStores(['stores' => 'nope']));
    }

    public function testExtractStoresDedupesAndCoerces(): void
    {
        $this->assertSame([0, 1, 2], (new PageFieldApplier())->extractStores([
            'stores' => [0, '1', 2, 1, '2'],
        ]));
    }

    public function testExtractStoresDropsNegativeAndNonNumeric(): void
    {
        $this->assertSame([3], (new PageFieldApplier())->extractStores([
            'stores' => [-1, 'foo', 3],
        ]));
    }
}
