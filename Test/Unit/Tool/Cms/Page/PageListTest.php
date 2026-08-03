<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Page;

use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCmsTools\Model\Search\PageSearchCriteriaBuilder;
use Magebit\McpCmsTools\Tool\Cms\Page\PageList;
use Magento\Cms\Api\PageRepositoryInterface;
use PHPUnit\Framework\TestCase;

class PageListTest extends TestCase
{
    private PageList $tool;

    protected function setUp(): void
    {
        $this->tool = new PageList(
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(PageSearchCriteriaBuilder::class),
            $this->createMock(ResolverPipeline::class),
            []
        );
    }

    public function testSchemaDeclaresTypedFilterKeys(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $filters = $schema['properties']['filters'];
        $this->assertIsArray($filters);
        $this->assertTrue($filters['additionalProperties']);
        $props = $filters['properties'];
        $this->assertIsArray($props);

        $expected = [
            'identifier' => ['string', 'array'],
            'title' => 'string',
            'is_active' => 'boolean',
            'store_id' => ['integer', 'array'],
            'website_id' => ['integer', 'array'],
            'created_at_from' => 'string',
            'created_at_to' => 'string',
            'update_time_from' => 'string',
            'update_time_to' => 'string',
        ];
        $this->assertSame(array_keys($expected), array_keys($props));
        foreach ($expected as $key => $type) {
            $prop = $props[$key];
            $this->assertIsArray($prop);
            $this->assertSame($type, $prop['type'], $key);
            $this->assertNotEmpty($prop['description'], $key);
        }
    }
}
